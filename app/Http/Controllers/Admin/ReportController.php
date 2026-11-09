<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\PlatformRequest;
use App\Models\User;
use App\Models\WebsiteInquiry;
use App\Services\Audit;
use App\Services\Entitlements;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/** Owner-level exports (CSV/XLSX). Account and operational metadata only; never secrets or document data. */
class ReportController extends Controller
{
    public const TYPES = ['organizations' => 'Organizations & subscriptions', 'users' => 'Users', 'usage' => 'Usage by organization', 'inquiries' => 'Website inquiries', 'approvals' => 'Approval requests', 'audit' => 'Platform audit events', 'revenue' => 'Revenue & billing events'];

    public function index()
    {
        return ['types' => self::TYPES];
    }

    public function export(Request $r, string $type)
    {
        abort_unless(isset(self::TYPES[$type]), 404);
        $d = $r->validate(['format' => ['required', Rule::in(['csv', 'xlsx'])], 'from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from']);
        $from = isset($d['from']) ? Carbon::parse($d['from'])->startOfDay() : null;
        $to = isset($d['to']) ? Carbon::parse($d['to'])->endOfDay() : null;
        $rows = $this->{$type}($from, $to);
        Audit::record('admin.report.exported', null, null, ['report' => $type, 'format' => $d['format'], 'from' => $from?->toDateString(), 'to' => $to?->toDateString(), 'rows' => count($rows) - 1]);
        $name = 'mortgagelens-'.$type.'-'.now()->format('Ymd-His').'.'.$d['format'];

        return $d['format'] === 'csv' ? $this->csv($rows, $name) : $this->xlsx($rows, $name, self::TYPES[$type]);
    }

    private function range($q, ?Carbon $from, ?Carbon $to, string $col = 'created_at')
    {
        return $q->when($from, fn ($x) => $x->where($col, '>=', $from))->when($to, fn ($x) => $x->where($col, '<=', $to));
    }

    private function organizations($from, $to): array
    {
        $rows = [['ID', 'Name', 'Plan', 'Subscription status', 'Approval', 'Suspended at', 'Trial ends', 'Members', 'Loan files', 'Created']];
        foreach ($this->range(Organization::with('plan:id,name')->withCount('memberships')->orderBy('id'), $from, $to)->get() as $o) {
            $rows[] = [$o->id, $o->name, $o->plan?->name, $o->subscription_status, $o->approval_status, $o->suspended_at?->toDateTimeString(), $o->trial_ends_at?->toDateTimeString(), $o->memberships_count, DB::table('loan_files')->where('organization_id', $o->id)->count(), $o->created_at?->toDateTimeString()];
        }

        return $rows;
    }

    private function users($from, $to): array
    {
        $rows = [['ID', 'Name', 'Email', 'Verified', 'Two-factor', 'Platform admin', 'Disabled at', 'Organizations', 'Last sign-in', 'Created']];
        foreach ($this->range(User::with('memberships.organization:id,name')->orderBy('id'), $from, $to)->get() as $u) {
            $rows[] = [$u->id, $u->name, $u->email, $u->email_verified_at ? 'Yes' : 'No', $u->hasConfirmedTwoFactor() ? 'Yes' : 'No', $u->platform_admin ? 'Yes' : 'No', $u->disabled_at?->toDateTimeString(), $u->memberships->map(fn ($m) => $m->organization?->name.' ('.$m->role.')')->implode('; '), $u->last_login_at?->toDateTimeString(), $u->created_at?->toDateTimeString()];
        }

        return $rows;
    }

    private function usage($from, $to): array
    {
        $rows = [['Organization ID', 'Organization', 'Plan', 'Members', 'Member limit', 'Loan files (in range)', 'Loan files (total)', 'File limit', 'Storage used (bytes)', 'Storage limit (bytes)', 'Bank statement runs (in range)', 'Appraisal review runs (in range)', 'Sample analyses (in range)']];
        foreach (Organization::with('plan')->withCount('memberships')->orderBy('id')->get() as $o) {
            $count = fn ($t) => $this->range(DB::table($t)->where('organization_id', $o->id), $from, $to)->count();
            $rows[] = [$o->id, $o->name, $o->plan?->name, $o->memberships_count, $o->memberLimit(), $count('loan_files'), DB::table('loan_files')->where('organization_id', $o->id)->count(), $o->fileLimit(), Entitlements::storageUsed($o->id), $o->storageLimitBytes(), $count('bank_runs'), $count('review_runs'), $count('analysis_runs')];
        }

        return $rows;
    }

    private function inquiries($from, $to): array
    {
        $rows = [['ID', 'Received', 'Name', 'Work email', 'Company', 'Role', 'Type', 'Status', 'Spam', 'Converted organization ID', 'Notification']];
        foreach ($this->range(WebsiteInquiry::orderBy('id'), $from, $to)->get() as $i) {
            $rows[] = [$i->id, $i->created_at?->toDateTimeString(), $i->name, $i->email, $i->company, $i->role, $i->inquiry_type, $i->status, $i->is_spam ? 'Yes' : 'No', $i->converted_organization_id, $i->notification_status];
        }

        return $rows;
    }

    private function approvals($from, $to): array
    {
        $rows = [['ID', 'Type', 'Organization', 'Requested by', 'Requested plan ID', 'Status', 'Decided by', 'Decided at', 'Reason', 'Created']];
        foreach ($this->range(PlatformRequest::with('organization', 'requester', 'decider')->orderBy('id'), $from, $to)->get() as $p) {
            $rows[] = [$p->id, $p->type, $p->organization?->name, $p->requester?->email, $p->payload['plan_id'] ?? null, $p->status, $p->decider?->name, $p->decided_at?->toDateTimeString(), $p->decision_reason, $p->created_at?->toDateTimeString()];
        }

        return $rows;
    }

    private function audit($from, $to): array
    {
        $rows = [['ID', 'When', 'Action', 'Actor', 'Actor email', 'Organization', 'Details']];
        $f = ['from' => $from?->toDateString(), 'to' => $to?->toDateString()];
        foreach (AuditController::query($f)->limit(50000)->get() as $e) {
            $rows[] = [$e->id, (string) $e->created_at, $e->action, $e->actor_name, $e->actor_email, $e->organization, $e->metadata ? json_encode($e->metadata, JSON_UNESCAPED_SLASHES) : ''];
        }

        return $rows;
    }

    private function revenue($from, $to): array
    {
        $test = config('billing.provider') === 'fake';
        $catalog = config('billing.catalog');
        $rows = [['Record', 'Organization', 'Provider', 'Status', 'Detail', 'When', 'Data label']];
        $rows[] = ['Pricing', '', (string) config('billing.provider'), $catalog === [] ? 'Pricing not configured' : 'Approved catalog configured', $catalog === [] ? 'MRR/ARR not calculated: no approved plan prices' : '', now()->toDateTimeString(), ''];
        foreach ($this->range(DB::table('billing_subscriptions as s')->join('organizations as o', 'o.id', '=', 's.organization_id')->orderBy('s.id'), $from, $to, 's.created_at')->get(['o.name', 's.provider', 's.status', 's.created_at']) as $s) {
            $rows[] = ['Subscription', $s->name, $s->provider, $s->status, '', (string) $s->created_at, $s->provider === 'fake' ? 'Test provider data' : ''];
        }
        foreach ($this->range(DB::table('billing_events as e')->join('organizations as o', 'o.id', '=', 'e.organization_id')->orderBy('e.id'), $from, $to, 'e.created_at')->get(['o.name', 'e.operation_key', 'e.status', 'e.data', 'e.created_at']) as $e) {
            $data = json_decode($e->data, true) ?: [];
            $rows[] = ['Billing event', $e->name, (string) config('billing.provider'), $e->status, trim(($data['action'] ?? '').' '.(isset($data['plan_id']) ? 'plan '.$data['plan_id'] : '')), (string) $e->created_at, $test ? 'Test provider data' : ''];
        }

        return $rows;
    }

    /** Neutralise spreadsheet formula injection in exported text cells. */
    private static function cell($v)
    {
        if (is_string($v) && $v !== '' && in_array($v[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$v;
        }

        return $v;
    }

    private function csv(array $rows, string $name)
    {
        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            foreach ($rows as $row) {
                fputcsv($out, array_map([self::class, 'cell'], $row), ',', '"', '');
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }

    private function xlsx(array $rows, string $name, string $title)
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->setTitle(substr(preg_replace('/[^A-Za-z0-9 ]/', '', $title), 0, 31));
        foreach ($rows as $i => $row) {
            foreach (array_values($row) as $j => $v) {
                $sheet->setCellValueExplicit([$j + 1, $i + 1], (string) (self::cell($v) ?? ''), is_int($v) || is_float($v) ? DataType::TYPE_NUMERIC : DataType::TYPE_STRING);
            }
        }
        $sheet->getStyle('1:1')->getFont()->setBold(true);
        $sheet->freezePane('A2');
        foreach (range(1, max(1, count($rows[0]))) as $c) {
            $sheet->getColumnDimensionByColumn($c)->setAutoSize(true);
        }

        return response()->streamDownload(function () use ($book) {
            (new Xlsx($book))->save('php://output');
        }, $name, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Cache-Control' => 'no-store']);
    }
}
