<?php

namespace App\Http\Controllers\Admin;

use App\Billing\PageCredits;
use App\Http\Controllers\Controller;
use App\Models\DemoRequest;
use App\Models\LoanFile;
use App\Models\Organization;
use App\Models\PaymentRequest;
use App\Models\Plan;
use App\Models\PlatformRequest;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\WebsiteInquiry;
use App\Services\Entitlements;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Owner console read models. Operational metadata and counts only: no documents, document content,
 * borrower details, analysis results, findings, or loan-file details beyond counts.
 */
class ConsoleController extends Controller
{
    public const PRIVACY_NOTICE = 'The owner console shows account and operational metadata only. It never lists customer documents, borrower details, analysis results or findings. Customer data can only be seen inside an audited, time-limited support session.';

    public function shell()
    {
        return view('admin');
    }

    public function me(Request $r)
    {
        $u = $r->user();

        return ['user' => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'two_factor' => $u->hasConfirmedTwoFactor()], 'privacy_notice' => self::PRIVACY_NOTICE, 'impersonation' => ['enabled' => (bool) config('platform.impersonation.enabled'), 'minutes' => (int) config('platform.impersonation.minutes'), 'allowed' => (bool) config('platform.impersonation.enabled') && $u->hasConfirmedTwoFactor()], 'pending_approvals' => PlatformRequest::where('status', 'pending')->count(), 'new_inquiries' => WebsiteInquiry::where('status', 'new')->where('is_spam', false)->count(), 'pending_payment_requests' => PaymentRequest::where('status', 'pending')->count(), 'new_demo_requests' => DemoRequest::where('status', 'new')->where('is_spam', false)->count(), 'open_tickets' => SupportTicket::where('status', 'open')->count()];
    }

    public function overview()
    {
        $statuses = Organization::query()->selectRaw('subscription_status, count(*) as n')->groupBy('subscription_status')->pluck('n', 'subscription_status');

        return [
            'counts' => [
                'organizations' => Organization::count(),
                'users' => User::count(),
                'loan_files' => LoanFile::count(),
                'suspended_organizations' => Organization::whereNotNull('suspended_at')->count(),
                'pending_approvals' => PlatformRequest::where('status', 'pending')->count(),
                'new_inquiries' => WebsiteInquiry::where('status', 'new')->where('is_spam', false)->count(),
                'disabled_users' => User::whereNotNull('disabled_at')->count(),
            ],
            'subscription_statuses' => collect(Organization::STATUSES)->map(fn ($label, $key) => ['key' => $key, 'label' => $label, 'count' => (int) ($statuses[$key] ?? 0)])->values(),
            'recent_signups' => User::latest('id')->limit(8)->get(['id', 'name', 'email', 'email_verified_at', 'created_at']),
            'recent_organizations' => Organization::with('plan:id,name')->latest('id')->limit(6)->get(['id', 'name', 'plan_id', 'subscription_status', 'approval_status', 'created_at']),
            'recent_inquiries' => WebsiteInquiry::where('is_spam', false)->latest('id')->limit(6)->get(['id', 'name', 'company', 'inquiry_type', 'status', 'created_at']),
        ];
    }

    public function plans()
    {
        $counts = Organization::query()->selectRaw('plan_id, count(*) as n')->groupBy('plan_id')->pluck('n', 'plan_id');

        return [
            'plans' => Plan::orderBy('id')->get()->map(fn (Plan $p) => app(PageCredits::class)->planPayload($p) + ['organizations' => (int) ($counts[$p->id] ?? 0), 'pricing_status' => 'Monthly price used for offline payment requests.']),
            'billing' => $this->billingStatus(),
            'notice' => 'These prices are what customers see and what payment requests use. No card is charged from this screen.',
        ];
    }

    private function billingStatus(): array
    {
        return [
            'label' => $this->billingLabel(),
            'notice' => 'Customers request a plan or a page pack. An administrator records the offline payment and approves the request. No card is charged in the product.',
        ];
    }

    private function billingLabel(): string
    {
        return (string) config('billing.provider') === 'stripe'
            ? 'Stripe'
            : 'Offline payments (manual approval)';
    }

    public function revenue()
    {
        $plans = Plan::query()->get()->keyBy('id');
        $active = Organization::query()->where('subscription_status', 'active')->whereNull('suspended_at')->get(['id', 'plan_id']);
        $mrr = round($active->sum(fn ($org) => (float) ($plans[$org->plan_id]->provisional_monthly_price ?? 0)), 2);
        $breakdown = $active->groupBy('plan_id')->map(function ($orgs, $planId) use ($plans) {
            $plan = $plans[$planId] ?? null;
            $price = round((float) ($plan->provisional_monthly_price ?? 0), 2);

            return [
                'plan' => $plan->name ?? 'Plan',
                'organizations' => $orgs->count(),
                'monthly_price' => $price,
                'mrr' => round($price * $orgs->count(), 2),
            ];
        })->sortBy('plan')->values();
        $monthStart = now()->startOfMonth();
        $collected = PaymentRequest::query()->where('status', 'approved')->where(function ($q) use ($monthStart) {
            $q->where('paid_offline_at', '>=', $monthStart)
                ->orWhere(fn ($q) => $q->whereNull('paid_offline_at')->where('decided_at', '>=', $monthStart));
        });
        $collectedPlans = round((float) (clone $collected)->where('kind', 'plan')->sum('amount'), 2);
        $collectedPacks = round((float) (clone $collected)->where('kind', 'pack')->sum('amount'), 2);
        $payments = PaymentRequest::with(['organization:id,name', 'plan:id,name'])
            ->whereIn('status', ['pending', 'approved'])
            ->latest('id')
            ->limit(100)
            ->get()
            ->map(fn (PaymentRequest $row) => [
                'id' => $row->id,
                'organization_id' => $row->organization_id,
                'organization' => $row->organization?->name,
                'kind' => PaymentRequest::KINDS[$row->kind] ?? $row->kind,
                'detail' => $row->kind === 'plan'
                    ? ($row->plan?->name ?? 'Plan change')
                    : ($row->pack_label ?: (($row->pack_pages ?? 0).' pages')),
                'amount' => number_format((float) $row->amount, 2, '.', ''),
                'status' => $row->status,
                'created_at' => $row->created_at,
                'decided_at' => $row->decided_at,
            ]);

        return [
            'billing' => $this->billingStatus(),
            'mrr' => $mrr,
            'arr' => round($mrr * 12, 2),
            'active_subscriptions' => $active->count(),
            'active_plans' => $active->pluck('plan_id')->unique()->count(),
            'pending_payment_requests' => PaymentRequest::where('status', 'pending')->count(),
            'collected_this_month' => round($collectedPlans + $collectedPacks, 2),
            'collected_plans_this_month' => $collectedPlans,
            'collected_packs_this_month' => $collectedPacks,
            'month_label' => now()->format('F Y'),
            'plan_breakdown' => $breakdown,
            'payments' => $payments,
        ];
    }

    public function system()
    {
        $mailer = (string) config('mail.default');
        $mailConfigured = match ($mailer) {
            'smtp' => filled(config('mail.mailers.smtp.host')),
            'log', 'array' => false,
            default => $mailer !== '',
        };

        return ['items' => [
            ['group' => 'Application', 'label' => 'Environment', 'value' => app()->environment()],
            ['group' => 'Application', 'label' => 'Debug mode', 'value' => config('app.debug') ? 'On' : 'Off', 'warn' => config('app.debug') && app()->environment('production')],
            ['group' => 'Application', 'label' => 'Version', 'value' => $this->version()],
            ['group' => 'Queue', 'label' => 'Queue connection', 'value' => (string) config('queue.default')],
            ['group' => 'Queue', 'label' => 'Horizon dashboard', 'value' => '/horizon', 'link' => '/horizon'],
            ['group' => 'Email', 'label' => 'Outgoing mail configured', 'value' => $mailConfigured ? 'Yes' : 'No', 'warn' => ! $mailConfigured],
            ['group' => 'Email', 'label' => 'Mail transport', 'value' => $mailer],
            ['group' => 'Email', 'label' => 'Inquiry notification inbox set', 'value' => filled(config('website.inquiry_notify_email')) ? 'Yes' : 'No'],
            ['group' => 'Document processing', 'label' => 'Malware scanner command set', 'value' => filled(config('bank.clamav')) ? 'Yes' : 'No'],
            ['group' => 'Document processing', 'label' => 'Practice-file scan bypass', 'value' => config('bank.fictional_scan_bypass') ? 'On' : 'Off', 'warn' => (bool) config('bank.fictional_scan_bypass')],
            ['group' => 'Document processing', 'label' => 'Bank extractor mode', 'value' => (string) config('bank.extractor')],
            ['group' => 'Providers', 'label' => 'OpenAI extraction approved', 'value' => config('bank.openai.approved') ? 'Yes' : 'No'],
            ['group' => 'Providers', 'label' => 'OpenAI key present', 'value' => filled(config('bank.openai.key')) ? 'Yes' : 'No'],
            ['group' => 'Providers', 'label' => 'Appraisal vision approved', 'value' => config('review.vision_approved') ? 'Yes' : 'No'],
            ['group' => 'Providers', 'label' => 'Integrity provider approved', 'value' => config('bank.integrity.approved') ? 'Yes' : 'No'],
            ['group' => 'Providers', 'label' => 'Billing', 'value' => $this->billingLabel()],
            ['group' => 'Owner console', 'label' => 'Two-factor required for console', 'value' => config('platform.console_require_2fa') ? 'Yes' : 'No', 'warn' => ! config('platform.console_require_2fa') && app()->environment('production')],
            ['group' => 'Owner console', 'label' => 'Signup approval required', 'value' => config('platform.signup_approval') ? 'Yes' : 'No'],
            ['group' => 'Owner console', 'label' => 'Support sessions enabled', 'value' => config('platform.impersonation.enabled') ? 'Yes' : 'No'],
            ['group' => 'Owner console', 'label' => 'Support session length', 'value' => config('platform.impersonation.minutes').' minutes'],
            ['group' => 'Owner console', 'label' => 'Document downloads in support sessions', 'value' => config('platform.impersonation.allow_document_download') ? 'Allowed' : 'Blocked'],
        ], 'notice' => 'Configuration presence only. Secret values are never shown.'];
    }

    private function version(): string
    {
        if (filled(config('platform.version'))) {
            return (string) config('platform.version');
        }
        $head = base_path('.git/HEAD');
        if (is_readable($head)) {
            $ref = trim((string) file_get_contents($head));
            if (str_starts_with($ref, 'ref: ')) {
                $file = base_path('.git/'.substr($ref, 5));
                $ref = is_readable($file) ? trim((string) file_get_contents($file)) : '';
            }
            if (preg_match('/^[0-9a-f]{40}$/', $ref)) {
                return substr($ref, 0, 7);
            }
        }

        return 'Not available';
    }

    public function analytics(Request $r)
    {
        $d = $r->validate(['from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from']);
        $to = isset($d['to']) ? Carbon::parse($d['to'])->endOfDay() : now()->endOfDay();
        $from = isset($d['from']) ? Carbon::parse($d['from'])->startOfDay() : $to->copy()->subMonths(5)->startOfMonth();
        abort_if($from->diffInDays($to) > 1100, 422, 'Choose a range of three years or less.');
        $days = $from->diffInDays($to);
        $unit = $days <= 31 ? 'day' : ($days <= 182 ? 'week' : 'month');
        $buckets = [];
        for ($c = $from->copy()->startOf($unit === 'week' ? 'week' : $unit); $c <= $to; $c->add(1, $unit)) {
            $buckets[] = $c->format('Y-m-d');
        }
        $series = function (string $table, ?string $where = null, array $bindings = []) use ($unit, $from, $to, $buckets) {
            $rows = DB::table($table)->selectRaw("to_char(date_trunc('$unit', created_at), 'YYYY-MM-DD') as b, count(*) as n")->whereBetween('created_at', [$from, $to])->when($where, fn ($q) => $q->whereRaw($where, $bindings))->groupBy('b')->pluck('n', 'b');

            return array_map(fn ($b) => ['bucket' => $b, 'count' => (int) ($rows[$b] ?? 0)], $buckets);
        };
        $modules = LoanFile::query()->whereBetween('created_at', [$from, $to])->distinct()->pluck('module')->all();
        $files = [];
        foreach ($modules as $m) {
            $files[] = ['module' => $m, 'series' => $series('loan_files', 'module = ?', [$m])];
        }
        $active = DB::table('audit_events')->whereBetween('created_at', [$from, $to])->whereNotNull('organization_id')->whereNotNull('actor_id')->distinct()->count('organization_id');
        $storage = Organization::orderBy('id')->get(['id', 'name'])->map(fn ($o) => ['organization' => $o->name, 'bytes' => Entitlements::storageUsed($o->id)])->filter(fn ($x) => $x['bytes'] > 0)->sortByDesc('bytes')->take(10)->values();
        $inquiryStatus = WebsiteInquiry::whereBetween('created_at', [$from, $to])->where('is_spam', false)->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

        return [
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'unit' => $unit],
            'signups' => $series('users'),
            'organizations' => $series('organizations'),
            'loan_files_by_module' => $files,
            'analyses' => [
                ['module' => 'Bank statement runs', 'series' => $series('bank_runs')],
                ['module' => 'Appraisal review runs', 'series' => $series('review_runs')],
                ['module' => 'Practice sample analyses', 'series' => $series('analysis_runs')],
            ],
            'active_organizations' => $active,
            'active_definition' => 'Organizations with at least one signed-in user action recorded in the audit log during this range.',
            'storage_top' => $storage,
            'inquiries' => $series('website_inquiries', 'is_spam = false'),
            'inquiry_status' => collect(WebsiteInquiry::STATUSES)->map(fn ($l, $k) => ['label' => $l, 'count' => (int) ($inquiryStatus[$k] ?? 0)])->values(),
            'inquiries_converted' => WebsiteInquiry::whereBetween('created_at', [$from, $to])->whereNotNull('converted_at')->count(),
            'plan_distribution' => Plan::orderBy('id')->get(['id', 'name'])->map(fn ($p) => ['label' => $p->name, 'count' => Organization::where('plan_id', $p->id)->count()]),
        ];
    }
}
