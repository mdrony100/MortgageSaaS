<?php

namespace App\Http\Controllers;

use App\Bank\BankStatus;
use App\Bank\CalculateIncome;
use App\Bank\Ledger;
use App\Bank\Money;
use App\Bank\Reports\Export;
use App\Bank\Reports\ReportService;
use App\Bank\ReviewState;
use App\Bank\RuleProfile;
use App\Bank\TransferEngine;
use App\Billing\Usage;
use App\Jobs\ProcessBankRun;
use App\Models\Bank\Run;
use App\Models\DocumentVersion;
use App\Models\LoanFile;
use App\Models\User;
use App\Services\Access;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BankController extends Controller
{
    private function run(LoanFile $file, string $id): Run
    {
        return Run::where('organization_id', $file->organization_id)->where('loan_file_id', $file->id)->findOrFail($id);
    }

    private function reviewer(LoanFile $file): void
    {
        Gate::authorize('review', $file);
    }

    public function show(Request $r, LoanFile $file)
    {
        Gate::authorize('view', $file);
        if (! Gate::allows('review', $file)) {
            $runs = Run::where('organization_id', $file->organization_id)->where('loan_file_id', $file->id)->pluck('id');
            $reports = DB::table('bank_reports')->where('organization_id', $file->organization_id)->where('loan_file_id', $file->id)->whereIn('run_id', $runs)->orderByDesc('id')->get()->map(fn ($x) => Ledger::decode($x))->filter(fn ($x) => $x['data']['audience'] === 'broker' && $x['data']['status'] === 'final')->values();
            $latest = $reports->first();

            return response()->json(['audience' => 'broker', 'final_income' => $latest ? $latest['data'] : null, 'reports' => $reports->map(fn ($x) => ['id' => $x['id'], 'run_id' => $x['run_id'], 'version' => $x['data']['report_version'], 'issued_at' => $x['data']['issued_at']]), 'conditions' => $this->publicRows($file, 'bank_conditions'), 'messages' => $this->publicRows($file, 'bank_messages'), 'notice' => 'Only issued final summaries and deliberately public conditions/explanations are available.'])->header('Cache-Control', 'private, no-store');
        }
        $runs = Run::where('organization_id', $file->organization_id)->where('loan_file_id', $file->id)->latest('created_at')->get(['id', 'status', 'stage', 'attempts', 'error_code', 'created_at', 'completed_at']);
        $id = $r->string('run_id')->toString() ?: $runs->first()?->id;
        $run = $id ? $this->run($file, $id) : null;
        $data = ['audience' => 'internal', 'runs' => $runs, 'rules' => DB::table('bank_rules')->where('organization_id', $file->organization_id)->get()->map(fn ($x) => ['id' => $x->id, 'name' => $x->name, 'version' => $x->version, 'status' => $x->status, 'policy' => json_decode($x->policy, true)]), 'reason_codes' => config('bank.reason_codes'), 'categories' => config('bank.categories')];
        if ($run) {
            $data += ['run' => $run, 'inputs_fresh' => BankStatus::freshness($run), 'integrity' => BankStatus::integrity($run), 'scans' => DB::table('bank_scans')->where('organization_id', $run->organization_id)->whereIn('document_version_id', Ledger::query('bank_inputs', $run)->pluck('document_version_id'))->orderByDesc('id')->get(['document_version_id', 'status', 'adapter', 'created_at']), 'provider_usage' => Ledger::query('bank_provider_calls', $run)->get()->map(fn ($call) => ['provider' => $call->provider, 'version' => $call->version, 'status' => $call->status, 'reserved_micro_usd' => $call->reserved_micro_usd, 'usage' => json_decode($call->data, true)['usage'] ?? null]), 'statements' => ReviewState::statements($run), 'accounts' => array_values(ReviewState::accounts($run)), 'transactions' => ReviewState::transactions($run), 'transfers' => ReviewState::transfers($run), 'findings' => ReviewState::findings($run), 'calculations' => Ledger::rows('bank_calculations', $run), 'reports' => Ledger::rows('bank_reports', $run), 'conditions' => $this->publicRows($file, 'bank_conditions'), 'messages' => $this->publicRows($file, 'bank_messages'), 'condition_history' => Ledger::rows('bank_conditions', $run), 'review_history' => array_merge(Ledger::rows('bank_reviews', $run), Ledger::rows('bank_finding_reviews', $run), Ledger::rows('bank_transfer_reviews', $run))];
        }

        return response()->json($data)->header('Cache-Control', 'private, no-store');
    }

    private function publicRows(LoanFile $file, string $table): array
    {
        if ($table === 'bank_conditions') {
            return BankStatus::conditions($file);
        }

        return DB::table($table)->where('organization_id', $file->organization_id)->where('loan_file_id', $file->id)->orderBy('id')->get()->map(function ($x) use ($table) {
            $d = Ledger::decode($x);

            return ['id' => $d['id'], 'text' => $d['data']['text'], 'status' => $d['data']['status'] ?? 'submitted', 'created_at' => $d['created_at']] + ($table === 'bank_messages' ? ['kind' => $d['data']['kind']] : []);
        })->all();
    }

    public function start(Request $r, LoanFile $file)
    {
        $this->reviewer($file);
        abort_if($file->archived_at, 422, 'Archived file');
        $d = $r->validate(['idempotency_key' => 'required|string|max:100', 'document_version_ids' => 'required|array|min:1|max:120', 'document_version_ids.*' => 'required|integer|distinct', 'rule_id' => ['nullable', 'integer', Rule::exists('bank_rules', 'id')->where('organization_id', $file->organization_id)], 'workflow' => ['required', Rule::in(['personal', 'business'])], 'months' => ['required', Rule::in([12, 24])], 'end_month' => 'required|date_format:Y-m', 'ownership_percent' => 'nullable|decimal:0,4|min:0|max:100', 'remaining_percent' => 'nullable|decimal:0,4|min:0|max:100', 'account_keys' => 'sometimes|array|max:30', 'account_keys.*' => 'string|size:64']);
        $ids = $d['document_version_ids'];
        sort($ids);
        $versions = DocumentVersion::whereIn('id', $ids)->where('organization_id', $file->organization_id)->with('document')->get();
        abort_unless($versions->count() === count($ids), 404);
        abort_if($versions->sum('bytes') > config('bank.max_run_bytes'), 422, 'Selected originals exceed the per-run 100 MiB limit');
        foreach ($versions as $v) {
            abort_unless($v->document->loan_file_id === $file->id && $v->document->category !== 'fixture', 404);
            Gate::authorize('view', $v->document);
        }
        $key = Ledger::hash([$file->organization_id, $file->id, $d['idempotency_key']]);
        $options = array_diff_key($d, array_flip(['document_version_ids', 'idempotency_key', 'rule_id']));
        $options['months'] = (int) $options['months'];
        $options['transfer_window_days'] = min(30, max(0, (int) config('bank.transfer_window_days', 3)));
        $options['source_inventory'] = BankStatus::inventory($file);
        $run = DB::transaction(function () use ($file, $r, $key, $options, $versions, $d) {
            LoanFile::whereKey($file->id)->lockForUpdate()->first();
            $old = Run::where('idempotency_key', $key)->first();
            if ($old) {
                abort_unless(Ledger::hash($old->options) === Ledger::hash($options) && $old->rule_id === ($d['rule_id'] ?? null) && $old->input_hash === Ledger::hash($versions->sortBy('id')->map(fn ($v) => [$v->id, $v->sha256])->values()->all()), 409, 'Idempotency key is already bound to different inputs; use a new key');

                return $old;
            }
            app(Usage::class)->reserve($file->organization_id, 'bank:'.$key);
            $run = Run::create(['id' => (string) Str::uuid(), 'organization_id' => $file->organization_id, 'loan_file_id' => $file->id, 'requested_by' => $r->user()->id, 'rule_id' => $d['rule_id'] ?? null, 'idempotency_key' => $key, 'status' => 'Queued', 'stage' => 'Queued', 'options' => $options, 'input_hash' => Ledger::hash($versions->sortBy('id')->map(fn ($v) => [$v->id, $v->sha256])->values()->all())]);
            foreach ($versions as $v) {
                DB::table('bank_inputs')->insert(Ledger::scope($run) + ['document_version_id' => $v->id, 'sha256' => $v->sha256, 'created_at' => now()]);
            }
            ProcessBankRun::dispatch($run->id)->afterCommit();

            return $run;
        });
        Audit::record('bank.analysis_requested', $file->organization_id, $file->id, ['run_id' => $run->id], 'internal');

        return response()->json(['run_id' => $run->id, 'status' => $run->status], 202);
    }

    public function control(Request $r, LoanFile $file, string $runId, string $action)
    {
        $this->reviewer($file);
        $run = $this->run($file, $runId);
        if ($action === 'cancel') {
            $run->update(['cancel_requested' => true]);
            app(Usage::class)->finish($run->organization_id, 'bank:'.$run->idempotency_key, 'cancelled');
            if (in_array($run->status, ['Queued', 'Failed'], true)) {
                $run->update(['status' => 'Cancelled', 'stage' => 'Cancelled', 'completed_at' => now()]);
            }
        } elseif ($action === 'retry') {
            abort_unless(in_array($run->status, ['Failed', 'PartiallyCompleted'], true) && ! $run->cancel_requested && $run->attempts < 6, 422, 'Run is not retryable or retry limit reached');
            app(Usage::class)->reserve($run->organization_id, 'bank:'.$run->idempotency_key);
            $run->update(['status' => 'Queued', 'stage' => 'Queued', 'error_code' => null]);
            ProcessBankRun::dispatch($run->id)->afterCommit();
        } else {
            abort(404);
        }

        return response()->json(['status' => $run->fresh()->status], 202);
    }

    public function rule(Request $r, LoanFile $file)
    {
        $this->reviewer($file);
        $d = $r->validate(['name' => 'required|string|max:120', 'status' => 'required|in:draft,approved,example', 'policy' => 'required|array']);
        if ($d['status'] === 'approved') {
            abort_unless(Access::admin($r->user(), $file->organization_id), 403);
        }
        if ($d['status'] === 'example') {
            abort_unless(app()->environment(['local', 'testing']) && $file->is_demo, 422, 'Examples are restricted to fictional development files');
        }
        $policy = RuleProfile::validate($d['policy']);
        if ($d['status'] !== 'approved') {
            $policy['approved_thresholds'] = false;
        }if ($d['status'] === 'approved') {
            abort_unless(! empty($policy['approval_reference']), 422, 'An actual policy approval reference is required');
        }
        $row = DB::transaction(function () use ($r, $file, $d, $policy) {
            DB::table('organizations')->where('id', $file->organization_id)->lockForUpdate()->first();
            $version = DB::table('bank_rules')->where('organization_id', $file->organization_id)->where('name', $d['name'])->max('version') + 1;

            return DB::table('bank_rules')->insertGetId(['organization_id' => $file->organization_id, 'name' => $d['name'], 'version' => $version, 'status' => $d['status'], 'policy' => Ledger::json($policy), 'created_by' => $r->user()->id, 'created_at' => now()]);
        });
        Audit::record('bank.rule_version_created', $file->organization_id, $file->id, ['rule_id' => $row], 'internal');

        return response()->json(['id' => $row], 201);
    }

    private function reason(Request $r): array
    {
        return $r->validate(['reason_code' => ['required', Rule::in(config('bank.reason_codes'))], 'notes' => 'required_if:reason_code,other|nullable|string|max:4000']);
    }

    public function review(Request $r, LoanFile $file, string $runId)
    {
        $this->reviewer($file);
        $run = $this->run($file, $runId);
        $reason = $this->reason($r);
        $d = $r->validate(['kind' => 'required|in:transaction,account,statement', 'transaction_ids' => 'required_if:kind,transaction|array|max:500', 'transaction_ids.*' => 'integer|distinct', 'category' => ['required_if:kind,transaction', 'nullable', Rule::in(config('bank.categories'))], 'include' => 'nullable|boolean', 'account_key' => 'required_if:kind,account|string|size:64', 'designation' => 'required_if:kind,account|in:personal,business', 'verified' => 'required_if:kind,account|boolean',
            'correction' => 'sometimes|array:posting_date,credit,debit,running_balance', 'correction.posting_date' => 'nullable|date_format:Y-m-d', 'correction.credit' => 'nullable|decimal:0,2|min:0|max:999999999999', 'correction.debit' => 'nullable|decimal:0,2|min:0|max:999999999999', 'correction.running_balance' => 'nullable|decimal:0,2|min:-999999999999|max:999999999999',
            'statement_id' => 'required_if:kind,statement|integer', 'daily_reconstruction_verified' => 'sometimes|boolean', 'canonical_account_key' => 'sometimes|string|size:64', 'statement_correction' => 'sometimes|array:institution,holder,masked_account,currency,period_start,period_end,opening_balance,closing_balance,reported_credits,reported_debits,reported_fees,fees_separate_from_debits,complete',
            'statement_correction.institution' => 'nullable|string|max:200', 'statement_correction.holder' => 'nullable|string|max:200', 'statement_correction.masked_account' => 'nullable|string|max:80', 'statement_correction.currency' => 'nullable|string|size:3',
            'statement_correction.period_start' => 'nullable|date_format:Y-m-d', 'statement_correction.period_end' => 'nullable|date_format:Y-m-d', 'statement_correction.fees_separate_from_debits' => 'nullable|boolean', 'statement_correction.complete' => 'sometimes|boolean',
            'statement_correction.opening_balance' => 'nullable|decimal:0,2|min:-999999999999|max:999999999999', 'statement_correction.closing_balance' => 'nullable|decimal:0,2|min:-999999999999|max:999999999999',
            'statement_correction.reported_credits' => 'nullable|decimal:0,2|min:0|max:999999999999', 'statement_correction.reported_debits' => 'nullable|decimal:0,2|min:0|max:999999999999', 'statement_correction.reported_fees' => 'nullable|decimal:0,2|min:0|max:999999999999']);
        if (isset($d['statement_correction']['masked_account'])) {
            $d['statement_correction']['masked_account'] = '****'.substr(preg_replace('/\D/', '', $d['statement_correction']['masked_account']), -4);
        }foreach (['institution', 'holder'] as $field) {
            if (isset($d['statement_correction'][$field])) {
                $d['statement_correction'][$field] = Ledger::redact($d['statement_correction'][$field]);
            }
        }
        if (! empty($d['correction'])) {
            abort_unless($d['kind'] === 'transaction' && count($d['transaction_ids']) === 1 && trim($reason['notes'] ?? '') !== '', 422, 'Financial corrections require a single transaction and explanatory source notes');
        }
        if ($d['kind'] === 'statement') {
            abort_unless(trim($reason['notes'] ?? '') !== '', 422, 'Statement/daily reconstruction verification requires source review notes');
        }
        DB::transaction(function () use ($run, $d, $r, $reason) {
            Run::whereKey($run->id)->lockForUpdate()->first();
            if ($d['kind'] === 'account') {
                abort_unless(isset(ReviewState::accounts($run)[$d['account_key']]), 404);
                $targets = ['account:'.$d['account_key']];
            } elseif ($d['kind'] === 'statement') {
                abort_unless(Ledger::query('bank_statements', $run)->where('id', $d['statement_id'])->exists(), 404);
                if (isset($d['canonical_account_key'])) {
                    abort_unless(ReviewState::accounts($run)[$d['canonical_account_key']]['verified'] ?? false, 422, 'Canonical identity must already be verified in this same run/file');
                }$targets = ['statement:'.$d['statement_id']];
            } else {
                $ids = $d['transaction_ids'];
                abort_unless(count($ids) > 0 && Ledger::query('bank_transactions', $run)->whereIn('id', $ids)->count() === count($ids), 404);
                $targets = array_map(fn ($id) => 'transaction:'.$id, $ids);
            }
            if (! empty($d['correction'])) {
                $current = array_column(ReviewState::transactions($run), null, 'id')[$d['transaction_ids'][0]];
                $credit = array_key_exists('credit', $d['correction']) ? Money::minor($d['correction']['credit']) : $current['credit_minor'];
                $debit = array_key_exists('debit', $d['correction']) ? Money::minor($d['correction']['debit']) : $current['debit_minor'];
                abort_if(($credit ?? 0) > 0 && ($debit ?? 0) > 0, 422, 'A source row cannot simultaneously be a credit and debit');
                $allocatedCredit = 0;
                $allocatedDebit = 0;
                foreach (ReviewState::transfers($run) as $m) {
                    if ($m['status'] === 'confirmed') {
                        if ($m['data']['source_id'] === $current['id']) {
                            $allocatedDebit += $m['allocated_minor'];
                        }if ($m['data']['destination_id'] === $current['id']) {
                            $allocatedCredit += $m['allocated_minor'];
                        }
                    }
                }
                abort_if($allocatedCredit > ($credit ?? 0) || $allocatedDebit > ($debit ?? 0) || (($allocatedCredit + $allocatedDebit) > 0 && array_key_exists('posting_date', $d['correction']) && $d['correction']['posting_date'] !== $current['posting_date']), 422, 'Reject existing transfer allocations before reducing allocated funds or changing their dates');
            }
            foreach ($targets as $target) {
                Ledger::append('bank_reviews', $run, (string) Str::uuid(), $d + $reason + ['target' => $target, 'actor_id' => $r->user()->id, 'reason_version' => config('bank.reason_version')]);
            }
        });
        app(TransferEngine::class)->match($run);
        Audit::record('bank.review_recorded', $file->organization_id, $file->id, [], 'internal');

        return response()->json(['message' => 'Original suggestion preserved; recalculate to create a new snapshot'], 201);
    }

    public function relationship(Request $r, LoanFile $file, string $runId)
    {
        $this->reviewer($file);
        $run = $this->run($file, $runId);
        $reason = $this->reason($r);
        $d = $r->validate(['account_a' => 'required|string|size:64', 'account_b' => 'required|string|size:64|different:account_a', 'verified' => 'required|boolean']);
        $accounts = ReviewState::accounts($run);
        abort_unless(isset($accounts[$d['account_a']],$accounts[$d['account_b']]) && $accounts[$d['account_a']]['verified'] && $accounts[$d['account_b']]['verified'], 422, 'Verify both account identities first');
        $pair = [$d['account_a'], $d['account_b']];
        sort($pair);
        Ledger::append('bank_relationships', $run, (string) Str::uuid(), $d + $reason + ['pair' => implode(':', $pair), 'actor_id' => $r->user()->id, 'reason_version' => config('bank.reason_version')]);
        app(TransferEngine::class)->match($run);

        return response()->json(['message' => 'Relationship reviewed; matching remains confined to this tenant/file/run'], 201);
    }

    public function transfer(Request $r, LoanFile $file, string $runId, int $matchId)
    {
        $this->reviewer($file);
        $run = $this->run($file, $runId);
        $reason = $this->reason($r);
        $d = $r->validate(['status' => 'required|in:confirmed,rejected', 'amount_minor' => 'required_if:status,confirmed|nullable|integer|min:1']);
        DB::transaction(function () use ($run, $r, $matchId, $reason, $d) {
            Run::whereKey($run->id)->lockForUpdate()->first();
            $match = Ledger::query('bank_transfer_matches', $run)->find($matchId);
            abort_unless($match, 404);
            $m = Ledger::decode($match)['data'];
            if ($d['status'] === 'confirmed') {
                $entries = array_column(ReviewState::transactions($run), null, 'id');
                $from = (object) $entries[$m['source_id']];
                $to = (object) $entries[$m['destination_id']];
                $accounts = ReviewState::accounts($run);
                abort_unless($accounts[$from->account_key]['verified'] && $accounts[$to->account_key]['verified'], 422, 'Both account identities must remain verified');
                $sourceAllocated = 0;
                $destinationAllocated = 0;
                foreach (ReviewState::transfers($run) as $x) {
                    if ($x['id'] !== $matchId && $x['status'] === 'confirmed') {
                        if ($x['data']['source_id'] === $from->id) {
                            $sourceAllocated += $x['allocated_minor'];
                        }if ($x['data']['destination_id'] === $to->id) {
                            $destinationAllocated += $x['allocated_minor'];
                        }
                    }
                }
                abort_if($sourceAllocated + $d['amount_minor'] > $from->debit_minor || $destinationAllocated + $d['amount_minor'] > $to->credit_minor, 422, 'Allocation exceeds available transaction amount');
                if (! ($m['evidence']['shared_reference'] ?? false) && ! ($m['evidence']['account_description'] ?? false)) {
                    abort_unless(trim($reason['notes'] ?? '') !== '', 422, 'Equal amounts alone are not proof; explicit source evidence notes are required');
                }
                $d['reviewed_source'] = ['transaction_id' => $from->id, 'posting_date' => $from->posting_date, 'debit_minor' => $from->debit_minor];
                $d['reviewed_destination'] = ['transaction_id' => $to->id, 'posting_date' => $to->posting_date, 'credit_minor' => $to->credit_minor];
                $pair = [$from->account_key, $to->account_key];
                sort($pair);
                $rel = Ledger::latestDecisions('bank_relationships', $run, 'pair')[implode(':', $pair)] ?? null;
                abort_unless($rel && $rel['data']['verified'], 422, 'Verified relationship required');
            }
            Ledger::append('bank_transfer_reviews', $run, (string) Str::uuid(), $d + $reason + ['match_id' => $matchId, 'actor_id' => $r->user()->id, 'reason_version' => config('bank.reason_version')]);
        });

        return response()->json(['message' => 'Allocation saved; recalculate income'], 201);
    }

    public function finding(Request $r, LoanFile $file, string $runId, int $findingId)
    {
        $this->reviewer($file);
        $run = $this->run($file, $runId);
        $reason = $this->reason($r);
        abort_unless(Ledger::query('bank_findings', $run)->where('id', $findingId)->exists(), 404);
        $d = $r->validate(['status' => 'required|in:reviewed,explanation_requested,resolved,rejected,escalated,reopened', 'assignee' => 'nullable|integer']);
        if (isset($d['assignee'])) {
            $u = User::findOrFail($d['assignee']);
            abort_unless(Gate::forUser($u)->allows('review', $file), 422, 'Assignee must have reviewer role AND file access');
        }
        if (in_array($d['status'], ['resolved', 'rejected'], true)) {
            abort_unless($reason['reason_code'] && trim($reason['notes'] ?? '') !== '', 422, 'Resolution/rejection requires an explanation');
        }
        Ledger::append('bank_finding_reviews', $run, (string) Str::uuid(), $d + $reason + ['finding_id' => $findingId, 'actor_id' => $r->user()->id, 'reason_version' => config('bank.reason_version')]);

        return response()->json(['message' => 'Finding history preserved'], 201);
    }

    public function calculate(Request $r, LoanFile $file, string $runId)
    {
        $this->reviewer($file);
        $run = $this->run($file, $runId);
        abort_unless(in_array($run->status, ['Completed', 'PartiallyCompleted', 'NeedsReview'], true), 422, 'Wait for extraction/reconciliation to finish');
        $d = $r->validate(['account_keys' => 'required|array|min:1|max:30', 'account_keys.*' => 'string|size:64|distinct', 'ownership_percent' => 'nullable|decimal:0,4|min:0|max:100', 'remaining_percent' => 'nullable|decimal:0,4|min:0|max:100', 'preview' => 'sometimes|boolean', 'preview_transactions' => 'sometimes|array|max:500', 'preview_transactions.*.id' => 'required|integer', 'preview_transactions.*.category' => ['required', Rule::in(config('bank.categories'))], 'preview_transactions.*.include' => 'nullable|boolean']);
        foreach ($d['account_keys'] as $key) {
            abort_unless(isset(ReviewState::accounts($run)[$key]), 422, 'Unknown selected account');
        }$preview = $d['preview'] ?? false;
        unset($d['preview']);
        abort_if(! $preview && ! empty($d['preview_transactions']), 422, 'Pending decisions are preview only');
        foreach ($d['preview_transactions'] ?? [] as $t) {
            abort_unless(Ledger::query('bank_transactions', $run)->where('id', $t['id'])->exists(), 404);
        }

        if (! $preview) {
            $reason = $this->reason($r);
            abort_unless(trim($reason['notes'] ?? '') !== '', 422, 'Confirmed financial inputs require source/evidence notes');
            $review = Ledger::append('bank_reviews', $run, 'calculation-inputs:'.Ledger::hash([$d, $reason, $r->user()->id]), ['kind' => 'calculation_inputs', 'target' => 'calculation-inputs', 'inputs' => $d, 'actor_id' => $r->user()->id, 'reason_version' => config('bank.reason_version')] + $reason);
            $d['financial_input_review_id'] = $review['id'];
        }

        return response()->json(app(CalculateIncome::class)->calculate($run, $d, ! $preview), $preview ? 200 : 201);
    }

    public function condition(Request $r, LoanFile $file, string $runId)
    {
        $this->reviewer($file);
        $run = $this->run($file, $runId);
        $d = $r->validate(['condition_id' => 'nullable|integer', 'text' => 'required|string|max:4000', 'status' => 'required|in:open,satisfied', 'public_acknowledgement' => 'required|accepted']);
        if (isset($d['condition_id'])) {
            $original = DB::table('bank_conditions')->where('organization_id', $file->organization_id)->where('loan_file_id', $file->id)->find($d['condition_id']);
            abort_unless($original, 404);
            $d['condition_id'] = Ledger::decode($original)['data']['condition_id'] ?? $original->id;
        }

        return response()->json(Ledger::append('bank_conditions', $run, (string) Str::uuid(), ['text' => Ledger::redact($d['text']), 'status' => $d['status']] + (isset($d['condition_id']) ? ['condition_id' => $d['condition_id']] : []) + ['actor_id' => $r->user()->id, 'deliberately_public' => true]), 201);
    }

    public function message(Request $r, LoanFile $file)
    {
        Gate::authorize('view', $file);
        abort_if(Access::role($r->user(), $file->organization_id) === 'viewer', 403);
        $d = $r->validate(['text' => 'required|string|max:4000', 'kind' => 'required|in:explanation,review_request']);
        $run = Run::where('organization_id', $file->organization_id)->where('loan_file_id', $file->id)->latest('created_at')->firstOrFail();
        Ledger::append('bank_messages', $run, (string) Str::uuid(), array_replace($d, ['text' => Ledger::redact($d['text'])]) + ['actor_id' => $r->user()->id, 'status' => 'submitted']);

        return response()->json(['message' => 'Submitted to authorized file reviewers'], 201);
    }

    public function issue(Request $r, LoanFile $file, string $runId)
    {
        $this->reviewer($file);
        $run = $this->run($file, $runId);
        $d = $r->validate(['calculation_id' => 'required|integer', 'final' => 'required|boolean', 'public_explanation' => 'nullable|string|max:4000']);

        return response()->json(app(ReportService::class)->issue($run, $d['calculation_id'], $d['final'], $r->user()->id, $d['public_explanation'] ?? ''), 201);
    }

    public function download(Request $r, LoanFile $file, string $runId, int $reportId, string $format)
    {
        Gate::authorize('view', $file);
        abort_unless(in_array($format, ['xlsx', 'csv', 'pdf'], true), 404);
        $run = $this->run($file, $runId);
        $row = Ledger::query('bank_reports', $run)->find($reportId);
        abort_unless($row, 404);
        $report = Ledger::decode($row)['data'];
        if (! Gate::allows('review', $file)) {
            abort_unless($report['audience'] === 'broker' && $report['status'] === 'final', 403);
        }
        [$bytes,$mime] = app(Export::class)->generate($report, $format);
        Audit::record('bank.report_downloaded', $file->organization_id, $file->id, ['report_id' => $reportId], 'internal');

        return response($bytes, 200, ['Content-Type' => $mime, 'Content-Disposition' => 'attachment; filename="income-report-'.$reportId.'.'.$format.'"', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
