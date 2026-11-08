<?php

namespace App\Services;

use App\Models\AnalysisRun;
use App\Models\AnalysisSnapshot;
use App\Models\FileShare;
use App\Models\LoanFile;
use App\Models\Note;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class Present
{
    public static function file(User $user, LoanFile $file): array
    {
        $restricted = Gate::forUser($user)->allows('restricted', $file);

        return ['id' => $file->id, 'organization_id' => $file->organization_id, 'name' => $file->name, 'reference' => $file->reference, 'module' => $file->module, 'status' => $file->status, 'is_demo' => $file->is_demo, 'archived_at' => $file->archived_at, 'borrower' => $file->borrower, 'draft_inputs' => $restricted ? $file->draft_inputs : array_intersect_key($file->draft_inputs ?? [], array_flip(['appraisal_form', 'units', 'occupancy', 'rent', 'pitia', 'loan_amount', 'value'])), 'permissions' => ['edit' => Gate::forUser($user)->allows('update', $file), 'upload' => Gate::forUser($user)->allows('upload', $file), 'share' => Gate::forUser($user)->allows('share', $file), 'review' => Gate::forUser($user)->allows('review', $file), 'restricted' => $restricted], 'documents' => $file->documents()->with('versions')->get()->filter(fn ($d) => Gate::forUser($user)->allows('view', $d))->map(function ($d) use ($user) {
            if (Access::broker($user, $d->organization_id)) {
                $d->setRelation('versions', $d->versions->where('uploaded_by', $user->id)->values());
            }

            return $d;
        })->values(), 'shares' => Gate::forUser($user)->allows('share', $file) ? FileShare::where('loan_file_id', $file->id)->get() : [], 'notes' => Note::where('loan_file_id', $file->id)->where('organization_id', $file->organization_id)->when(! $restricted, fn ($q) => $q->where('visibility', 'shared'))->get(['id', 'user_id', 'body', 'visibility', 'created_at']), 'runs' => $restricted ? AnalysisRun::where('loan_file_id', $file->id)->get(['id', 'module', 'stage', 'status', 'is_demo', 'attempts', 'completed_at', 'error']) : []];
    }

    public static function analysis(User $user, LoanFile $file): ?array
    {
        $run = AnalysisRun::where('loan_file_id', $file->id)->where('status', 'completed')->latest('created_at')->first();
        if (! $run) {
            return null;
        } $s = AnalysisSnapshot::where('analysis_run_id', $run->id)->first();
        if (! $s) {
            return null;
        } $d = $s->data;
        // Allowlist nonrestricted surfaces: never serialize the complete internal snapshot to Brokers/processors/viewers.
        if (! Gate::forUser($user)->allows('restricted', $file)) {
            $d = ['label' => $d['label'], 'source' => $d['source'], 'bank' => ['overview' => array_intersect_key($d['bank']['overview'], array_flip(['account', 'statement_months', 'account_type', 'qualifying_income', 'currency', 'coverage', 'request'])), 'transactions' => array_map(fn ($t) => array_intersect_key($t, array_flip(['id', 'date', 'description', 'amount', 'classification', 'source'])), $d['bank']['transactions']), 'income' => $d['bank']['income'], 'transfers' => $d['bank']['transfers'], 'nsf' => $d['bank']['nsf']], 'appraisal' => $d['appraisal'], 'dscr' => $d['dscr']];
        }

        if (! Gate::forUser($user)->allows('review', $file)) {
            $d['bank'] = ['notice' => 'No issued final income report. Internal bank sample review is not available to this role.'];
        }

        return ['run_id' => $run->id, 'ruleset_version' => 1, 'is_demo' => true, 'data' => $d];
    }
}
