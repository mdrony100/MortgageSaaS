<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\Organization;
use App\Services\Access;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkspaceOverviewController extends Controller
{
    private const PROCESSING = ['Queued', 'Validating', 'Extracting', 'Reconciling', 'Analyzing'];

    private const NEEDS_REVIEW = ['NeedsReview', 'PartiallyCompleted'];

    private const ACTIONS = [
        'file.created' => 'Loan file created',
        'file.updated' => 'Loan file updated',
        'file.shared' => 'Loan file shared',
        'file.share_revoked' => 'Share removed',
        'document.uploaded' => 'Document uploaded',
        'document.version_added' => 'New document version',
        'document.downloaded' => 'Document downloaded',
        'note.added' => 'Note added',
        'borrower.created' => 'Borrower added',
        'borrower.updated' => 'Borrower updated',
    ];

    public function show(Request $request, Organization $organization)
    {
        $user = $request->user();
        abort_unless(Access::membership($user, $organization->id), 403);
        $files = Access::visible($user)->where('organization_id', $organization->id)->with('borrower:id,name')->get();
        $active = $files->whereNull('archived_at')->values();
        $ids = $active->pluck('id');
        $bank = $ids->isEmpty() ? collect() : DB::table('bank_runs')->where('organization_id', $organization->id)->whereIn('loan_file_id', $ids)->orderByDesc('created_at')->get(['loan_file_id', 'status'])->unique('loan_file_id')->keyBy(fn ($row) => (int) $row->loan_file_id);
        $review = $ids->isEmpty() ? collect() : DB::table('review_runs')->where('organization_id', $organization->id)->whereIn('loan_file_id', $ids)->orderByDesc('created_at')->get(['loan_file_id', 'status'])->unique('loan_file_id')->keyBy(fn ($row) => (int) $row->loan_file_id);
        $reported = $ids->isEmpty() ? collect() : DB::table('bank_reports')->where('organization_id', $organization->id)->whereIn('loan_file_id', $ids)->pluck('loan_file_id')->merge(DB::table('review_records')->where('organization_id', $organization->id)->whereIn('loan_file_id', $ids)->where('kind', 'report')->pluck('loan_file_id'))->map(fn ($id) => (int) $id)->unique();

        $inProgress = 0;
        $needing = 0;
        $completed = 0;
        $uploadId = null;
        foreach ($active as $file) {
            $run = $file->module === 'bank' ? ($bank->get($file->id)->status ?? null) : ($review->get($file->id)->status ?? null);
            if (in_array($run, self::PROCESSING, true)) {
                $inProgress++;
            }
            if ($file->status === 'in_review' || in_array($run, self::NEEDS_REVIEW, true)) {
                $needing++;
            }
            if ($file->status === 'complete' || $run === 'Completed' || $reported->contains($file->id)) {
                $completed++;
            }
            if ($uploadId === null) {
                $uploadId = $file->id;
            }
        }

        $documents = $ids->isEmpty() ? 0 : (int) DB::table('document_versions')->join('documents', 'documents.id', '=', 'document_versions.document_id')->where('documents.organization_id', $organization->id)->where('document_versions.organization_id', $organization->id)->whereIn('documents.loan_file_id', $ids)->where('documents.category', '!=', 'fixture')->count();
        $flags = 0;
        if ($ids->isNotEmpty()) {
            $flags += DB::table('bank_findings')->where('organization_id', $organization->id)->whereIn('loan_file_id', $ids)->count();
            $flags += DB::table('fraud_findings')->where('organization_id', $organization->id)->whereIn('loan_file_id', $ids)->count();
            $flags += DB::table('review_records')->where('organization_id', $organization->id)->whereIn('loan_file_id', $ids)->where('kind', 'finding')->count();
        }

        $role = Access::role($user, $organization->id);
        $names = $files->keyBy('id');

        return [
            'organization' => ['id' => $organization->id, 'name' => $organization->name],
            'can_create' => in_array($role, ['owner', 'admin', 'underwriter', 'processor'], true),
            'can_upload' => in_array($role, ['owner', 'admin', 'underwriter', 'processor', 'broker'], true),
            'upload_file_id' => $uploadId,
            'stats' => [
                'loan_files' => $active->count(),
                'needing_review' => $needing,
                'in_progress' => $inProgress,
                'completed' => $completed,
                'documents' => $documents,
                'flags' => $flags,
                'archived' => $files->whereNotNull('archived_at')->count(),
            ],
            'modules' => [
                'bank' => $active->where('module', 'bank')->count(),
                'appraisal' => $active->where('module', 'appraisal')->count(),
                'dscr' => $active->where('module', 'dscr')->count(),
            ],
            'appraisals' => $this->documents($organization->id, $ids, ['appraisal']),
            'statements' => $this->documents($organization->id, $ids, ['bank']),
            'activity' => $this->activity($user, $organization, $names),
        ];
    }

    private function documents(int $orgId, $ids, array $modules): array
    {
        if ($ids->isEmpty()) {
            return [];
        }

        return DB::table('document_versions')
            ->join('documents', 'documents.id', '=', 'document_versions.document_id')
            ->join('loan_files', 'loan_files.id', '=', 'documents.loan_file_id')
            ->join('borrowers', 'borrowers.id', '=', 'loan_files.borrower_id')
            ->where('documents.organization_id', $orgId)
            ->where('document_versions.organization_id', $orgId)
            ->where('loan_files.organization_id', $orgId)
            ->whereIn('documents.loan_file_id', $ids)
            ->where('documents.category', '!=', 'fixture')
            ->whereIn('loan_files.module', $modules)
            ->orderByDesc('document_versions.id')
            ->limit(6)
            ->get([
                'document_versions.id',
                'documents.title',
                'document_versions.original_name',
                'document_versions.scan_status',
                'document_versions.created_at',
                'loan_files.id as loan_file_id',
                'loan_files.name as loan_file',
                'borrowers.name as borrower',
            ])->map(fn ($row) => [
                'id' => (int) $row->id,
                'title' => $row->title,
                'name' => $row->original_name,
                'status' => $row->scan_status,
                'created_at' => $row->created_at,
                'loan_file_id' => (int) $row->loan_file_id,
                'loan_file' => $row->loan_file,
                'borrower' => $row->borrower,
            ])->all();
    }

    private function activity($user, Organization $organization, $files): array
    {
        $ids = $files->pluck('id');
        if ($ids->isEmpty()) {
            return [];
        }
        $query = AuditEvent::query()->where('organization_id', $organization->id)->whereIn('loan_file_id', $ids)->where('action', '!=', 'document.previewed');
        if (! Access::reviewer($user, $organization->id)) {
            $query->where('visibility', 'shared');
        }

        return $query->latest('id')->limit(8)->get(['id', 'action', 'loan_file_id', 'created_at', 'visibility'])->map(function ($event) use ($files) {
            $file = $files->get($event->loan_file_id);

            return [
                'id' => $event->id,
                'action' => $event->action,
                'label' => self::ACTIONS[$event->action] ?? str_replace('.', ' ', $event->action),
                'loan_file_id' => $event->loan_file_id,
                'loan_file' => $file?->name,
                'created_at' => $event->created_at,
            ];
        })->all();
    }
}
