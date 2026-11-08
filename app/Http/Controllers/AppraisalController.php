<?php

namespace App\Http\Controllers;

use App\Appraisal\Calculator;
use App\Appraisal\Guidelines;
use App\Appraisal\Pipeline;
use App\Appraisal\Reports;
use App\Appraisal\Store;
use App\Bank\Ledger;
use App\Bank\Money;
use App\Billing\Usage;
use App\Jobs\ProcessAppraisal;
use App\Models\DocumentVersion;
use App\Models\LoanFile;
use App\Models\Review\Run;
use App\Services\Access;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AppraisalController extends Controller
{
    private function run(LoanFile $f, string $id): Run
    {
        return Run::where('organization_id', $f->organization_id)->where('loan_file_id', $f->id)->findOrFail($id);
    }

    public function show(Request $q, LoanFile $file)
    {
        Gate::authorize('view', $file);
        if (! Gate::allows('review', $file)) {
            return response()->json(['audience' => 'restricted', 'notice' => 'Appraisal internal review requires an authorized reviewer; sharing does not elevate role. Use shared notes/uploads to request review.'])->header('Cache-Control', 'private, no-store');
        }$runs = Run::where('organization_id', $file->organization_id)->where('loan_file_id', $file->id)->latest()->get();
        $r = $q->input('run_id') ? $this->run($file, $q->input('run_id')) : $runs->first();
        $data = ['runs' => $runs, 'run' => $r, 'guidelines' => DB::table('guideline_versions')->where('organization_id', $file->organization_id)->orderByDesc('id')->get()->map(fn ($x) => array_replace((array) $x, ['data' => json_decode($x->data, true)])), 'baseline' => Guidelines::baseline()];
        if ($r) {
            $rows = Store::rows($r);
            foreach ($rows as &$row) {
                unset($row['data']['storage_key'],$row['data']['disk']);
            }unset($row);
            $data += ['records' => $rows, 'facts' => Store::facts($r), 'findings' => Store::findings($r), 'calculation' => Store::latest($r, 'calculation'), 'inputs' => Store::latest($r, 'inputs')];
        }

        return response()->json($data)->header('Cache-Control', 'private,no-store');
    }

    public function start(Request $q, LoanFile $file)
    {
        Gate::authorize('review', $file);
        abort_if($file->archived_at, 422, 'Archived file');
        $d = $q->validate(['document_version_ids' => 'required|array|min:1|max:20', 'document_version_ids.*' => 'integer|distinct', 'idempotency_key' => 'required|string|max:100', 'parent_id' => 'nullable|uuid', 'guideline_id' => 'nullable|integer', 'context' => 'required|array:loan_occupancy,transaction,program,units', 'context.loan_occupancy' => 'required|in:primary,second_home,investment,unknown', 'context.transaction' => 'required|in:purchase,rate_term,cash_out,unknown', 'context.units' => 'required|integer|min:1|max:4', 'context.program' => 'nullable|string|max:200', 'classifications' => 'nullable|array|max:20', 'classifications.*' => ['string', Rule::in(['appraisal', 'sales_comparison', 'rental_schedule', 'operating_income', 'addendum', 'photo', 'sketch', 'map', 'contract', 'contract_amendment', 'lease', 'lease_amendment', 'rent_roll', 'rent_payment', 'repair_completion', 'revised_appraisal', 'unsupported', 'uncertain'])]]);
        $pins = [];
        foreach ($d['document_version_ids'] as $id) {
            $v = DocumentVersion::where('organization_id', $file->organization_id)->findOrFail($id);
            abort_unless($v->document->loan_file_id === $file->id, 404);
            Gate::authorize('view', $v->document);
            $pins[] = ['id' => $v->id, 'sha256' => $v->sha256, 'classification' => Ledger::redact((string) ($d['classifications'][$id] ?? 'uncertain'))];
        }if (! empty($d['parent_id'])) {
            $this->run($file, $d['parent_id']);
        }$g = Guidelines::baseline();
        if (! empty($d['guideline_id'])) {
            $row = DB::table('guideline_versions')->where('organization_id', $file->organization_id)->find($d['guideline_id']) ?? abort(404);
            $g = json_decode($row->data, true);
            abort_if(DB::table('guideline_versions')->where('organization_id', $file->organization_id)->where('status', 'retired')->where('data->supersedes_id', $row->id)->exists(), 422, 'This guideline version was retired; historical runs remain frozen');
            abort_if(($g['demo_only'] ?? false) && ! $file->is_demo, 422, 'Demo-only guideline cannot enter ordinary review');
            abort_if(($g['status'] ?? 'draft') === 'retired', 422, 'Select a current draft/approved guideline');
        }$r = DB::transaction(function () use ($q, $file, $d, $pins, $g) {
            DB::table('loan_files')->where('id', $file->id)->lockForUpdate()->first();
            $old = Run::where('organization_id', $file->organization_id)->where('loan_file_id', $file->id)->where('idempotency_key', $d['idempotency_key'])->first();
            if ($old) {
                abort_unless(Ledger::hash($old->inputs) === Ledger::hash($pins) && Ledger::hash($old->context) === Ledger::hash($d['context']) && $old->parent_id === ($d['parent_id'] ?? null) && Ledger::hash($old->checklist) === Ledger::hash($g), 409, 'Idempotency key is bound to different inputs');

                return $old;
            }$id = (string) Str::uuid();
            app(Usage::class)->reserve($file->organization_id, 'appraisal:'.$id);

            return Run::create(['id' => $id, 'organization_id' => $file->organization_id, 'loan_file_id' => $file->id, 'requested_by' => $q->user()->id, 'idempotency_key' => $d['idempotency_key'], 'parent_id' => $d['parent_id'] ?? null, 'context' => $d['context'], 'inputs' => $pins, 'checklist' => $g]);
        });
        ProcessAppraisal::dispatch($r->id)->afterCommit();
        Audit::record('appraisal.queued', $file->organization_id, $file->id, ['run_id' => $r->id], 'internal');

        return response()->json(['run_id' => $r->id, 'status' => $r->status], 202);
    }

    public function guideline(Request $q, LoanFile $file)
    {
        Gate::authorize('review', $file);
        abort_unless(Access::admin($q->user(), $file->organization_id), 403);
        $d = $q->validate(['status' => 'required|in:draft,approved,retired', 'previous_id' => 'nullable|integer', 'notes' => 'required|string|max:2000', 'demo_simulation' => 'sometimes|boolean']);
        $g = Guidelines::baseline();
        if (! empty($d['previous_id'])) {
            $p = DB::table('guideline_versions')->where('organization_id', $file->organization_id)->find($d['previous_id']) ?? abort(404);
            $g = json_decode($p->data, true);
        }if ($d['status'] === 'approved') {
            abort_unless(in_array((string) $q->user()->id, config('review.designated_reviewer_ids'), true) || ($file->is_demo && app()->environment('local', 'testing') && $q->boolean('demo_simulation')), 403, 'Designated client guideline reviewer required');
            $g['approver_id'] = $q->user()->id;
            $g['approved_at'] = now()->toIso8601String();
            $g['demo_only'] = $q->boolean('demo_simulation');
        }if (($g['demo_only'] ?? false) && ! $file->is_demo) {
            abort(422);
        }$g['version'] = (string) Str::uuid();
        $g['supersedes_id'] = $d['previous_id'] ?? null;
        $g['status'] = $d['status'];
        $g['approval_notes'] = Ledger::redact($d['notes']);
        $id = DB::table('guideline_versions')->insertGetId(['organization_id' => $file->organization_id, 'status' => $d['status'], 'data' => Ledger::json($g), 'created_by' => $q->user()->id, 'created_at' => now(), 'updated_at' => now()]);
        Audit::record('appraisal.guideline_version', $file->organization_id, $file->id, ['id' => $id], 'internal');

        return response()->json(['id' => $id], 201);
    }

    public function action(Request $q, LoanFile $file, string $runId, string $action)
    {
        Gate::authorize('review', $file);

        return DB::transaction(function () use ($q, $file, $runId, $action) {
            $r = $this->run($file, $runId);
            $r = Run::whereKey($r->id)->lockForUpdate()->firstOrFail();
            if ($action === 'control') {
                $d = $q->validate(['operation' => 'required|in:cancel,retry']);
                if ($d['operation'] === 'cancel') {
                    $r->update(['cancel_requested' => true, 'status' => 'Cancelled']);
                    app(Usage::class)->finish($r->organization_id, 'appraisal:'.$r->id, 'cancelled');
                } else {
                    abort_unless($r->status === 'Failed' && ($r->metrics['failure_attempts'] ?? 0) < 6 && ! $r->cancel_requested, 422);
                    app(Usage::class)->reserve($r->organization_id, 'appraisal:'.$r->id);
                    $r->update(['status' => 'Queued', 'error' => null]);
                    ProcessAppraisal::dispatch($r->id)->afterCommit();
                }

                return ['status' => $r->status];
            }abort_unless(in_array($r->status, ['NeedsReview', 'Completed', 'Failed'], true), 422, 'Wait for processing');
            $d = $q->validate(['notes' => 'required|string|max:4000']);
            $notes = Ledger::redact($d['notes']);
            $common = ['actor_id' => $q->user()->id, 'notes' => $notes, 'at' => now()->toIso8601String()];
            $key = (string) Str::uuid();
            switch ($action) {
                case 'baseline_observation':
                    $d = $q->validate(['field_id' => 'required|integer', 'rule_id' => 'required|string|max:100', 'applicability_confirmed' => 'required|accepted']);
                    abort_unless(($r->checklist['status'] ?? 'draft') === 'approved', 422, 'Approved frozen baseline required');
                    $fact = collect(Store::facts($r))->firstWhere('id', $d['field_id']);
                    abort_unless($fact && isset($fact['confirmation']), 422, 'Reviewer-verified source evidence required');
                    $rule = collect($r->checklist['rules'])->firstWhere('id', $d['rule_id']);
                    abort_unless($rule, 422);
                    $row = Store::add($r, 'finding', $key, $common + ['category' => 'baseline_review', 'priority' => 'review', 'observation' => 'Outside Fannie Mae Baseline: '.$notes, 'evidence' => ['field_id' => $fact['id'], 'document_version_id' => $fact['data']['document_version_id'], 'page' => $fact['data']['page']], 'guideline' => $rule, 'what_verify' => 'Human-confirmed applicable baseline only; does not disqualify Non-QM/DSCR', 'impact' => 'Review selected input; no automatic decision', 'status' => 'open']);
                    break;

                case 'confirm':$d = $q->validate(['field_id' => 'required|integer', 'value' => 'nullable|string|max:4000']);
                    $f = Store::query($r)->where('kind', 'field')->find($d['field_id']) ?? abort(404);
                    $row = Store::add($r, 'confirmation', $key, $common + ['field_key' => $f->record_key, 'value' => $d['value'] ?? null, 'status' => 'reviewer_confirmed']);
                    break;
                case 'finding':$d = $q->validate(['finding_id' => 'required|integer', 'status' => 'required|in:reviewed,resolved,needs_follow_up,reopened,dismissed']);
                    Store::query($r)->where('kind', 'finding')->find($d['finding_id']) ?? abort(404);
                    $row = Store::add($r, 'finding_review', $key, $common + $d);
                    break;
                case 'note':$row = Store::add($r, 'note', $key, $common);
                    break;
                case 'unit_match':$d = $q->validate(['document_version_id' => 'required|integer', 'unit' => 'required|string|max:50', 'address_evidence' => 'required|string|max:1000', 'status' => 'required|in:confirmed,rejected,uncertain']);
                    abort_unless(in_array($d['document_version_id'], array_column($r->inputs, 'id'), true), 404);
                    $document = collect(Store::rows($r, 'document'))->first(fn ($doc) => $doc['data']['document_version_id'] === $d['document_version_id']);
                    $class = collect(Store::rows($r, 'classification_review'))->filter(fn ($x) => $x['data']['document_version_id'] === $d['document_version_id'])->last()['data']['classification'] ?? ($document['data']['extracted_classification'] ?? null);
                    abort_unless(in_array($class, ['lease', 'lease_amendment', 'rent_roll'], true), 422, 'Confirm lease/rent-roll classification before unit matching');
                    $row = Store::add($r, 'unit_match', $key, $common + $d);
                    break;
                case 'classification':$d = $q->validate(['document_version_id' => 'required|integer', 'classification' => ['required', Rule::in(['appraisal', 'sales_comparison', 'rental_schedule', 'operating_income', 'addendum', 'photo', 'sketch', 'map', 'contract', 'contract_amendment', 'lease', 'lease_amendment', 'rent_roll', 'rent_payment', 'repair_completion', 'revised_appraisal', 'unsupported', 'uncertain'])]]);
                    abort_unless(in_array($d['document_version_id'], array_column($r->inputs, 'id'), true), 404);
                    $row = Store::add($r, 'classification_review', $key, $common + $d);
                    break;
                case 'inputs':$input = $this->validateInputs($q);
                    $row = Store::add($r, 'inputs', $key, $common + ['input' => $input]);
                    $calc = Calculator::calculate($input, $r->checklist);
                    $calc['input_review_id'] = $row['id'];
                    $row = Store::add($r, 'calculation', Ledger::hash($calc), $calc);
                    $this->rentalFindings($r, $input);
                    foreach (Store::facts($r) as $fact) {
                        if ($fact['data']['key'] === 'appraised_value' && isset($input['appraised_value']) && (string) $input['appraised_value'] !== (string) $fact['data']['normalized']) {
                            app(Pipeline::class)->finding($r, 'appraised-user-conflict:'.$fact['id'].':'.Ledger::hash($input), 'user_extraction_conflict', 'User-reported appraised value differs from extracted source; preserve both and verify, no automatic value-basis substitution', ['field_ids' => [$fact['id']], 'user_value' => $input['appraised_value'], 'extracted_value' => $fact['data']['normalized']]);
                        }
                    }
                    break;
                case 'report':$row = app(Reports::class)->snapshot($r, $q->user()->id);
                    break;
                default:abort(404);
            }Audit::record('appraisal.'.$action, $file->organization_id, $file->id, ['run_id' => $r->id, 'record_id' => $row['id']], 'internal');

            return response()->json($row, 201);
        });
    }

    private function validateInputs(Request $q): array
    {
        $money = ['nullable', 'string', 'regex:/^\d{1,12}(?:\.\d{1,2})?$/D'];
        $rules = ['input.units.*' => 'required|array:id,inspection_occupancy,current_occupancy,lease_status,rent_method,is_adu,market,qualifying', 'input.payment' => 'required|array:mode,total,components,interest_only,definition', 'input.payment.components' => 'nullable|array:payment,taxes,insurance,association,other', 'input' => 'required|array:units,payment,loan,value_basis,appraised_value', 'input.units' => 'required|array|min:1|max:4', 'input.units.*.id' => 'required|string|max:50|distinct', 'input.units.*.inspection_occupancy' => 'required|in:occupied,vacant,unknown', 'input.units.*.current_occupancy' => 'required|in:occupied,vacant,unknown', 'input.units.*.lease_status' => 'required|in:executed,expired_month_to_month,vacant,short_term,unknown', 'input.units.*.rent_method' => 'required|in:long_term,short_term,unknown', 'input.units.*.is_adu' => 'required|boolean', 'input.payment.mode' => 'required|in:total,components', 'input.payment.interest_only' => 'sometimes|boolean', 'input.payment.definition' => 'nullable|string|max:1000', 'input.appraised_value' => 'nullable|string|max:100'];
        foreach (['input.units.*.market', 'input.units.*.qualifying', 'input.payment.total', 'input.payment.components.payment', 'input.payment.components.taxes', 'input.payment.components.insurance', 'input.payment.components.association', 'input.payment.components.other', 'input.loan', 'input.value_basis'] as $prefix) {
            $rules[$prefix] = 'nullable|array:value,confirmed,source,basis';
            $rules[$prefix.'.value'] = $money;
            $rules[$prefix.'.confirmed'] = 'sometimes|boolean';
            $rules[$prefix.'.source'] = 'nullable|string|max:1000';
            $rules[$prefix.'.basis'] = 'nullable|string|max:1000';
        }$q->validate($rules);

        return $q->input('input');
    }

    private function rentalFindings(Run $r, array $i): void
    {
        $vacant = count(array_filter($i['units'], fn ($u) => $u['current_occupancy'] === 'vacant'));
        $occupied = count(array_filter($i['units'], fn ($u) => $u['current_occupancy'] === 'occupied'));
        $scope = $vacant === count($i['units']) ? 'fully_vacant' : ($vacant && $occupied ? 'partially_vacant' : 'unknown_or_no_confirmed_vacancy');
        Store::add($r, 'rental_scope', Ledger::hash($i), ['occupancy_scope' => $scope, 'confirmed_vacant_units' => $vacant, 'confirmed_occupied_units' => $occupied, 'transaction' => $r->context['transaction'], 'loan_occupancy' => $r->context['loan_occupancy'], 'notice' => 'Inspection/current/lease states remain separate; missing lease does not establish vacancy']);
        foreach ($i['units'] as $u) {
            $status = $u['current_occupancy'];
            $lease = $u['lease_status'];
            if ($status === 'vacant') {
                foreach (['vacancy_permission' => 'Verify whether the selected '.$r->context['transaction'].' / program permits this confirmed vacancy ('.$scope.')', 'market_rent_use' => 'Separately verify whether market rent may be selected for this vacant unit; do not substitute it automatically', 'vacancy_documentation' => 'Confirm required vacancy/market-rent support and supporting source documents'] as $question => $text) {
                    app(Pipeline::class)->finding($r, 'unit:'.$u['id'].':'.$question.':'.Ledger::hash($u), 'vacancy_verification', 'Unit '.$u['id'].': '.$text, ['unit' => $u['id'], 'what_verify' => 'Verify Against Program Guidelines; '.$text]);
                }
            }
            $message = match (true) {
                $status === 'vacant' => 'Confirmed vacancy: separately verify program/refinance vacancy permission, market-rent use and evidence. '.$r->context['transaction'],$lease === 'expired_month_to_month' => 'Expired lease: request continuation language/rent receipts; do not assume renewal',$lease === 'short_term' => 'Separate STR evidence and method required; no invented annualization',$status === 'occupied' => 'Occupied: verify current executed lease, amendments, unit match and supporting rent evidence',default => 'Unknown/conflicting occupancy: missing lease does not establish vacancy'
            };
            $sources = [];
            foreach (Store::facts($r) as $f) {
                $d = $f['data'];
                $key = $d['key'];
                if (! in_array($key, ['lease.rent', 'rent_roll.rent', 'market_rent', 'rental_schedule.market_rent', 'rent'], true)) {
                    continue;
                }
                $unit = $d['unit'] ?? null;
                if ($unit === null) {
                    $candidate = collect(Store::rows($r, 'lease_candidate'))->first(fn ($c) => $c['data']['document_version_id'] === $d['document_version_id']);
                    $unit = $candidate['data']['proposed_unit'] ?? null;
                }
                if ((string) $unit !== $u['id']) {
                    continue;
                }$sources[] = ['field_id' => $f['id'], 'key' => $key, 'value' => $d['normalized'], 'document_version_id' => $d['document_version_id'], 'page' => $d['page'], 'status' => 'extracted, not reviewer-selected'];
                if (preg_match('/^\d{1,12}(?:\.\d{1,2})?$/D', $d['normalized'] ?? '') && isset($u['qualifying']['value']) && Money::minor($d['normalized']) !== Money::minor($u['qualifying']['value'])) {
                    app(Pipeline::class)->finding($r, 'rent-source:'.$u['id'].':'.$f['id'].':'.Ledger::hash($u), 'rental_inconsistency', 'Unit '.$u['id'].': source '.$key.' differs from selected qualifying rent; preserve both and verify explicit basis', ['field_ids' => [$f['id']], 'unit' => $u['id'], 'selected' => $u['qualifying'], 'what_verify' => 'Compare lease/amendments, rent roll, appraisal schedule and reviewer selection; do not automatically use higher/lower/market rent']);
                }
            }
            Store::add($r, 'rent_comparison', $u['id'].':'.Ledger::hash($u), ['unit' => $u['id'], 'inspection_occupancy' => $u['inspection_occupancy'], 'current_occupancy' => $u['current_occupancy'], 'lease_status' => $lease, 'source_rents' => $sources, 'selected_qualifying' => $u['qualifying'], 'selected_market' => $u['market'], 'guideline_version' => $r->checklist['version'], 'notice' => 'Side-by-side sources only; no automatic rent choice or lease renewal']);
            app(Pipeline::class)->finding($r, 'unit:'.$u['id'].':'.Ledger::hash($u), 'rental_evidence', 'Unit '.$u['id'].': '.$message, ['unit' => $u['id']]);
        }
    }

    public function artifact(Request $q, LoanFile $file, string $runId, int $recordId)
    {
        Gate::authorize('review', $file);
        $r = $this->run($file, $runId);
        $a = Store::query($r)->whereIn('kind', ['page', 'image'])->find($recordId) ?? abort(404);
        $d = json_decode($a->data, true);
        $bytes = Storage::disk($d['disk'])->get($d['storage_key']);
        if (isset($d['artifact_sha256'])) {
            abort_unless(hash_equals($d['artifact_sha256'], hash('sha256', $bytes)), 409, 'Derived source integrity mismatch; original preserved');
        }

        return response($bytes, 200, ['Content-Type' => 'image/png', 'Cache-Control' => 'private,no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function download(Request $q, LoanFile $file, string $runId, int $recordId, string $format)
    {
        Gate::authorize('review', $file);
        abort_unless(in_array($format, ['pdf', 'xlsx'], true), 404);
        $r = $this->run($file, $runId);
        $row = Store::query($r)->where('kind', 'report')->find($recordId) ?? abort(404);
        $artifact = Store::query($r)->where('kind', 'report_artifact')->where('record_key', $recordId.':'.$format)->first();
        abort_unless($artifact, 409, 'Stored report artifact unavailable; issue a new version, never silently regenerate history');
        $d = json_decode($artifact->data, true);
        $bytes = Storage::disk($d['disk'])->get($d['storage_key']);
        $mime = $d['mime'];
        abort_unless(hash_equals($d['sha256'], hash('sha256', $bytes)), 409, 'Report integrity mismatch');
        Audit::record('appraisal.report_downloaded', $file->organization_id, $file->id, ['report_id' => $recordId], 'internal');

        return response($bytes, 200, ['Content-Type' => $mime, 'Content-Disposition' => 'attachment; filename="review-'.$recordId.'.'.$format.'"', 'Cache-Control' => 'private,no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
