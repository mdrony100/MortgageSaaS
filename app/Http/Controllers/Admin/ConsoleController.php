<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LoanFile;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\PlatformRequest;
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

        return ['user' => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'two_factor' => $u->hasConfirmedTwoFactor()], 'privacy_notice' => self::PRIVACY_NOTICE, 'impersonation' => ['enabled' => (bool) config('platform.impersonation.enabled'), 'minutes' => (int) config('platform.impersonation.minutes'), 'allowed' => (bool) config('platform.impersonation.enabled') && $u->hasConfirmedTwoFactor()], 'pending_approvals' => PlatformRequest::where('status', 'pending')->count(), 'new_inquiries' => WebsiteInquiry::where('status', 'new')->where('is_spam', false)->count()];
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
        $catalog = config('billing.catalog');
        $counts = Organization::query()->selectRaw('plan_id, count(*) as n')->groupBy('plan_id')->pluck('n', 'plan_id');

        return [
            'plans' => Plan::orderBy('id')->get()->map(fn (Plan $p) => ['id' => $p->id, 'name' => $p->name, 'file_limit' => $p->file_limit, 'member_limit' => $p->member_limit, 'storage_limit_bytes' => (int) $p->storage_limit_bytes, 'organizations' => (int) ($counts[$p->id] ?? 0), 'approved_price' => $catalog[$p->id]['monthly_price'] ?? null, 'pricing_status' => isset($catalog[$p->id]) ? 'Approved configuration' : 'Price not approved']),
            'billing' => $this->billingStatus(),
        ];
    }

    private function billingStatus(): array
    {
        $provider = (string) config('billing.provider');

        return ['provider' => $provider, 'test_only' => $provider === 'fake', 'fake_enabled' => (bool) config('billing.fake_enabled'), 'webhook_secret_configured' => filled(config('billing.webhook_secret')), 'live_provider_configured' => ! in_array($provider, ['fake', 'disabled', ''], true), 'notice' => $provider === 'fake' ? 'Test provider only. No real charges are made and no real revenue exists.' : 'No live billing provider is configured.'];
    }

    public function revenue()
    {
        $catalog = config('billing.catalog');
        $active = Organization::where('subscription_status', 'active')->whereNull('suspended_at')->get(['id', 'plan_id']);
        $priced = $active->every(fn ($o) => isset($catalog[$o->plan_id]['monthly_price']));
        $mrr = null;
        if ($catalog !== [] && $priced) {
            $mrr = round($active->sum(fn ($o) => (float) $catalog[$o->plan_id]['monthly_price']), 2);
        }
        $subs = DB::table('billing_subscriptions as s')->join('organizations as o', 'o.id', '=', 's.organization_id')->orderByDesc('s.id')->limit(100)->get(['s.id', 'o.id as organization_id', 'o.name as organization', 's.provider', 's.status', 's.created_at', 's.updated_at']);
        $events = DB::table('billing_events as e')->join('organizations as o', 'o.id', '=', 'e.organization_id')->orderByDesc('e.id')->limit(100)->get(['e.id', 'o.name as organization', 'e.operation_key', 'e.status', 'e.data', 'e.created_at'])->map(function ($e) {
            $d = json_decode($e->data, true) ?: [];

            return ['id' => $e->id, 'organization' => $e->organization, 'kind' => str_starts_with($e->operation_key, 'request:') ? 'Change request' : 'Provider event', 'status' => $e->status, 'action' => $d['action'] ?? ($d['type'] ?? null), 'plan_id' => $d['plan_id'] ?? null, 'created_at' => $e->created_at];
        });

        return [
            'billing' => $this->billingStatus(),
            'pricing_configured' => $catalog !== [] && $priced,
            'mrr' => $mrr,
            'arr' => $mrr === null ? null : round($mrr * 12, 2),
            'active_subscriptions' => $active->count(),
            'message' => $mrr === null ? 'Pricing not configured. Revenue cannot be calculated until approved plan prices are added to config/billing.php.' : null,
            'subscriptions' => $subs,
            'events' => $events,
            'test_data' => config('billing.provider') === 'fake' || $subs->contains(fn ($s) => $s->provider === 'fake'),
            'invoices_available' => false,
            'invoices_notice' => 'Invoices and payments are not stored by the current billing integration.',
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
            ['group' => 'Document processing', 'label' => 'Fictional scan bypass', 'value' => config('bank.fictional_scan_bypass') ? 'On' : 'Off', 'warn' => (bool) config('bank.fictional_scan_bypass')],
            ['group' => 'Document processing', 'label' => 'Bank extractor mode', 'value' => (string) config('bank.extractor')],
            ['group' => 'Providers', 'label' => 'OpenAI extraction approved', 'value' => config('bank.openai.approved') ? 'Yes' : 'No'],
            ['group' => 'Providers', 'label' => 'OpenAI key present', 'value' => filled(config('bank.openai.key')) ? 'Yes' : 'No'],
            ['group' => 'Providers', 'label' => 'Appraisal vision approved', 'value' => config('review.vision_approved') ? 'Yes' : 'No'],
            ['group' => 'Providers', 'label' => 'Integrity provider approved', 'value' => config('bank.integrity.approved') ? 'Yes' : 'No'],
            ['group' => 'Providers', 'label' => 'Billing provider', 'value' => (string) config('billing.provider')],
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
                ['module' => 'Sample analyses (fictional)', 'series' => $series('analysis_runs')],
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
