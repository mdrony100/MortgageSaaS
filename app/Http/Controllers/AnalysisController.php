<?php

namespace App\Http\Controllers;

use App\Jobs\LoadSampleAnalysis;
use App\Models\AnalysisRun;
use App\Models\LoanFile;
use App\Models\Ruleset;
use App\Services\Access;
use App\Services\Audit;
use App\Services\Present;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AnalysisController extends Controller
{
    public function load(Request $r, LoanFile $file)
    {
        Gate::authorize('update', $file);
        abort_unless($file->is_demo, 422, 'Use an explicitly fictional demo file. Uploaded documents do not trigger analysis.');
        $d = $r->validate(['idempotency_key' => 'required|string|max:80']);
        $key = hash('sha256', $file->organization_id.':'.$file->id.':sample:'.$d['idempotency_key']);
        $run = AnalysisRun::firstOrCreate(['idempotency_key' => $key], ['id' => (string) Str::uuid(), 'organization_id' => $file->organization_id, 'loan_file_id' => $file->id, 'module' => 'all', 'stage' => 'sample_fixture', 'status' => 'queued', 'is_demo' => true]);
        if (in_array($run->status, ['queued', 'failed'], true)) {
            LoadSampleAnalysis::dispatch($run->id, $r->user()->id);
        }

        return response()->json(['run_id' => $run->id, 'status' => $run->status, 'is_demo' => true], 202);
    }

    public function show(Request $r, LoanFile $file)
    {
        Gate::authorize('view', $file);

        return ['analysis' => Present::analysis($r->user(), $file)];
    }

    public function restricted(Request $r, LoanFile $file, string $section)
    {
        Gate::authorize('restricted', $file);
        abort_unless(in_array($section, ['integrity', 'sustainability'], true), 404);
        $a = Present::analysis($r->user(), $file);

        return ['is_demo' => true, 'data' => $a['data']['bank'][$section] ?? null];
    }

    public function override(Request $r, LoanFile $file)
    {
        Gate::authorize('review', $file);
        abort_unless($file->is_demo, 422, 'Real analysis integration pending.');
        $d = $r->validate(['target_id' => 'required|string|max:80', 'kind' => ['required', Rule::in(['classification', 'finding'])], 'value' => ['required', Rule::in($r->input('kind') === 'classification' ? ['eligible_deposit', 'excluded_transfer', 'excluded_non_income', 'bank_fee'] : ['needs_review', 'acknowledged', 'resolved', 'dismissed'])], 'reason' => 'required|string|min:5|max:2000']);
        $a = Present::analysis($r->user(), $file);
        abort_unless($a, 422, 'Load sample analysis first.');
        $valid = $d['kind'] === 'classification' ? array_column($a['data']['bank']['transactions'], 'id') : array_merge(array_column($a['data']['bank']['integrity'] ?? [], 'id'), array_column($a['data']['appraisal']['findings'], 'id'));
        abort_unless(in_array($d['target_id'], $valid, true), 422, 'Unknown sample target.');
        DB::table('reviewer_overrides')->insert(['organization_id' => $file->organization_id, 'loan_file_id' => $file->id, 'analysis_run_id' => $a['run_id'], 'external_key' => (string) Str::uuid(), 'data' => json_encode($d + ['actor_id' => $r->user()->id]), 'is_demo' => true, 'created_at' => now(), 'updated_at' => now()]);
        Audit::record('reviewer.override', $file->organization_id, $file->id, ['target_id' => $d['target_id'], 'kind' => $d['kind']], 'internal');

        return response()->json(['message' => 'Review decision saved; original evidence and fixture unchanged.'], 201);
    }

    public function history(Request $r, LoanFile $file)
    {
        Gate::authorize('review', $file);

        return DB::table('reviewer_overrides')->where('organization_id', $file->organization_id)->where('loan_file_id', $file->id)->orderByDesc('id')->get(['id', 'data', 'created_at']);
    }

    public function report(Request $r, LoanFile $file, string $module)
    {
        Gate::authorize('view', $file);
        abort_unless(in_array($module, ['bank', 'appraisal', 'dscr'], true), 404);
        $a = Present::analysis($r->user(), $file);
        abort_unless($a, 422, 'Load sample analysis first.');
        Audit::record('sample_report.previewed', $file->organization_id, $file->id, [], 'shared');

        return ['title' => 'SAMPLE '.strtoupper($module).' report', 'notice' => 'Fictional demonstration only. Not a loan decision or verified analysis. Open findings do not block this sample preview.', 'borrower' => $file->borrower->name, 'file' => $file->name, 'run_id' => $a['run_id'], 'ruleset_version' => 1, 'data' => $a['data'][$module], 'downloads' => 'Not available in Phase 1.'];
    }

    public function export(Request $r, LoanFile $file)
    {
        Gate::authorize('view', $file);

        return response()->json(['message' => 'Analysis report exports are not configured in Phase 1. Use the role-scoped sample report preview.'], 501);
    }

    public function rules(Request $r)
    {
        $org = $r->integer('organization_id');
        abort_unless(Access::membership($r->user(), $org), 403);

        return ['notice' => 'Fictional draft guidelines only. No approved investor rules or Fannie Mae checks configured.', 'rulesets' => Ruleset::where('organization_id', $org)->get()];
    }
}
