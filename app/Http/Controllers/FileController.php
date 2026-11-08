<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\Borrower;
use App\Models\FileShare;
use App\Models\LoanFile;
use App\Models\Note;
use App\Models\Organization;
use App\Services\Access;
use App\Services\Audit;
use App\Services\Entitlements;
use App\Services\Present;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class FileController extends Controller
{
    public function index(Request $r)
    {
        $q = Access::visible($r->user())->with('borrower')->orderByDesc('updated_at');
        if ($r->filled('organization_id')) {
            $q->where('organization_id', $r->integer('organization_id'));
        } if ($r->filled('q')) {
            $q->where('name', 'ilike', '%'.substr($r->string('q'), 0, 100).'%');
        }

        return $q->paginate(30);
    }

    public function borrowers(Request $r, Organization $organization)
    {
        abort_unless(Access::membership($r->user(), $organization->id), 403);
        $ids = Access::visible($r->user())->where('organization_id', $organization->id)->pluck('borrower_id');

        return Borrower::where('organization_id', $organization->id)->when(! Access::admin($r->user(), $organization->id), fn ($q) => $q->whereIn('id', $ids))->get();
    }

    public function borrower(Request $r, Organization $organization)
    {
        abort_unless(in_array(Access::role($r->user(), $organization->id), ['owner', 'admin', 'underwriter', 'processor'], true), 403);
        $d = $r->validate(['name' => 'required|string|max:120', 'email' => 'nullable|email|max:254', 'phone' => 'nullable|string|max:30']);
        $b = Borrower::create($d + ['organization_id' => $organization->id]);
        Audit::record('borrower.created', $organization->id);

        return response()->json($b, 201);
    }

    public function updateBorrower(Request $r, Organization $organization, Borrower $borrower)
    {
        abort_unless($borrower->organization_id === $organization->id, 404);
        abort_unless(Access::admin($r->user(), $organization->id) || Access::visible($r->user())->where('borrower_id', $borrower->id)->get()->contains(fn ($f) => Gate::allows('update', $f)), 403);
        $borrower->update($r->validate(['name' => 'required|string|max:120', 'email' => 'nullable|email|max:254', 'phone' => 'nullable|string|max:30']));
        Audit::record('borrower.updated', $organization->id);

        return $borrower;
    }

    public function store(Request $r, Organization $organization)
    {
        abort_unless(in_array(Access::role($r->user(), $organization->id), ['owner', 'admin', 'underwriter', 'processor'], true), 403);
        $d = $r->validate(['name' => 'required|string|max:150', 'reference' => 'nullable|string|max:80', 'borrower_id' => ['required', Rule::exists('borrowers', 'id')->where('organization_id', $organization->id)], 'module' => ['required', Rule::in(['bank', 'appraisal', 'dscr'])], 'is_demo' => 'required|boolean']);
        $f = DB::transaction(function () use ($r, $organization, $d) {
            $org = Organization::lockForUpdate()->findOrFail($organization->id);
            Entitlements::files($org);

            return LoanFile::create($d + ['organization_id' => $org->id, 'created_by' => $r->user()->id]);
        });
        Audit::record('file.created', $organization->id, $f->id, [], 'shared');

        return response()->json($f, 201);
    }

    public function show(Request $r, LoanFile $file)
    {
        Gate::authorize('view', $file);

        return Present::file($r->user(), $file);
    }

    public function update(Request $r, LoanFile $file)
    {
        Gate::authorize('update', $file);
        $d = $r->validate(['name' => 'sometimes|required|string|max:150', 'reference' => 'nullable|string|max:80', 'status' => ['sometimes', Rule::in(['draft', 'in_review', 'complete'])], 'archived' => 'sometimes|boolean', 'draft_inputs' => 'sometimes|array:statement_months,account_type,expense_method,expense_ratio,appraisal_form,units,occupancy,rent,pitia,loan_amount,value', 'draft_inputs.statement_months' => ['sometimes', Rule::in([12, 24])], 'draft_inputs.account_type' => ['sometimes', Rule::in(['personal', 'business'])], 'draft_inputs.expense_method' => ['sometimes', Rule::in(['standard', 'custom'])], 'draft_inputs.expense_ratio' => 'nullable|numeric|min:0|max:100', 'draft_inputs.appraisal_form' => ['sometimes', Rule::in(['1004', '1025', '1007', '216'])], 'draft_inputs.units' => 'sometimes|integer|min:1|max:4', 'draft_inputs.occupancy' => ['sometimes', Rule::in(['owner_occupied', 'second_home', 'investment'])], 'draft_inputs.rent' => 'nullable|decimal:0,2|min:0|max:999999999', 'draft_inputs.pitia' => 'nullable|decimal:0,2|min:0|max:999999999', 'draft_inputs.loan_amount' => 'nullable|decimal:0,2|min:0|max:999999999', 'draft_inputs.value' => 'nullable|decimal:0,2|min:0|max:999999999']);
        if (array_key_exists('archived', $d)) {
            $d['archived_at'] = $d['archived'] ? now() : null;
            unset($d['archived']);
        } $file->update($d);
        Audit::record('file.updated', $file->organization_id, $file->id, [], 'shared');

        return Present::file($r->user(), $file);
    }

    public function shareCandidates(Request $r, LoanFile $file)
    {
        Gate::authorize('share', $file);

        return Membership::where('organization_id', $file->organization_id)->with('user:id,name')->get(['id', 'user_id', 'role']);
    }

    public function share(Request $r, LoanFile $file)
    {
        Gate::authorize('share', $file);
        $d = $r->validate(['user_id' => ['required', 'integer', Rule::exists('memberships', 'user_id')->where('organization_id', $file->organization_id)]]);
        $s = FileShare::updateOrCreate(['loan_file_id' => $file->id, 'user_id' => $d['user_id']], ['organization_id' => $file->organization_id, 'shared_by' => $r->user()->id, 'revoked_at' => null]);
        Audit::record('file.shared', $file->organization_id, $file->id, ['user_id' => $s->user_id], 'shared');

        return $s;
    }

    public function revoke(Request $r, LoanFile $file, FileShare $share)
    {
        Gate::authorize('share', $file);
        abort_unless($share->loan_file_id === $file->id && $share->organization_id === $file->organization_id, 404);
        $share->update(['revoked_at' => now()]);
        Audit::record('file.share_revoked', $file->organization_id, $file->id, [], 'shared');

        return response()->noContent();
    }

    public function note(Request $r, LoanFile $file)
    {
        Gate::authorize('view', $file);
        abort_if(Access::role($r->user(), $file->organization_id) === 'viewer', 403);
        $d = $r->validate(['body' => 'required|string|max:5000', 'visibility' => ['required', Rule::in(['shared', 'internal'])]]);
        if ($d['visibility'] === 'internal') {
            Gate::authorize('restricted', $file);
        } $n = Note::create($d + ['organization_id' => $file->organization_id, 'loan_file_id' => $file->id, 'user_id' => $r->user()->id]);
        Audit::record('note.added', $file->organization_id, $file->id, [], $d['visibility']);

        return response()->json($n, 201);
    }

    public function activity(Request $r, LoanFile $file)
    {
        Gate::authorize('view', $file);
        $q = AuditEvent::where('organization_id', $file->organization_id)->where('loan_file_id', $file->id);
        if (! Gate::allows('restricted', $file)) {
            $q->where('visibility', 'shared');
        }

        return $q->latest('id')->limit(100)->get(['id', 'action', 'actor_id', 'created_at']);
    }
}
