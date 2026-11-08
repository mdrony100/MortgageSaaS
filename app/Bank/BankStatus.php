<?php

namespace App\Bank;

use App\Models\Bank\Run;
use App\Models\LoanFile;
use Illuminate\Support\Facades\DB;

class BankStatus
{
    public static function freshness(Run $r): bool
    {
        $current = self::inventory(LoanFile::findOrFail($r->loan_file_id));
        $pinned = $r->options['source_inventory'] ?? Ledger::query('bank_inputs', $r)->pluck('document_version_id')->map(fn ($x) => (int) $x)->sort()->values()->all();

        return $current === $pinned;
    }

    public static function inventory(LoanFile $file): array
    {
        return DB::table('document_versions as v')->join('documents as d', 'd.id', '=', 'v.document_id')->where('d.organization_id', $file->organization_id)->where('d.loan_file_id', $file->id)->where('d.category', '!=', 'fixture')->selectRaw('max(v.id) as id')->groupBy('d.id')->pluck('id')->map(fn ($x) => (int) $x)->sort()->values()->all();
    }

    public static function conditions(LoanFile $file): array
    {
        $out = [];
        foreach (DB::table('bank_conditions')->where('organization_id', $file->organization_id)->where('loan_file_id', $file->id)->orderBy('id')->get() as $row) {
            $d = Ledger::decode($row);
            $root = $d['data']['condition_id'] ?? $d['id'];
            $out[$root] = ['id' => $root, 'text' => $d['data']['text'], 'status' => $d['data']['status'], 'created_at' => $d['created_at']];
        }

        return array_values($out);
    }

    public static function integrity(Run $r): array
    {
        $statuses = [];
        foreach (Ledger::rows('bank_findings', $r) as $f) {
            if ($f['data']['category'] === 'provider_status') {
                $statuses[$f['data']['document_version_id']] = $f['data']['external']['status'];
            }
        }
        $inputCount = Ledger::query('bank_inputs', $r)->count();
        $extracted = array_unique(array_column(array_column(Ledger::rows('bank_statements', $r), 'data'), 'document_version_id'));
        $clean = [];
        foreach (DB::table('bank_scans')->where('organization_id', $r->organization_id)->whereIn('document_version_id', Ledger::query('bank_inputs', $r)->pluck('document_version_id'))->orderBy('id')->get() as $scan) {
            $clean[$scan->document_version_id] = $scan->status;
        }
        $terminal = in_array($r->fresh()->status, ['Completed', 'PartiallyCompleted', 'NeedsReview'], true);
        $internal = $terminal && count($extracted) === $inputCount && count($clean) === $inputCount && ! array_filter($clean, fn ($s) => $s !== 'Clean') ? 'Completed' : ($r->status === 'Queued' ? 'Pending' : 'Incomplete');

        return ['internal' => $internal, 'external_by_document' => $statuses, 'external_complete' => count($statuses) === $inputCount && ! array_filter($statuses, fn ($s) => $s !== 'Completed'), 'notice' => 'Disabled, unavailable, failed and pending are NOT no fraud detected'];
    }
}
