<?php

namespace App\Http\Controllers;

use App\Bank\Ledger;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\LoanFile;
use App\Models\Organization;
use App\Services\Access;
use App\Services\Audit;
use App\Services\Entitlements;
use App\Services\PrivateStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DocumentController extends Controller
{
    public function store(Request $r, LoanFile $file)
    {
        Gate::authorize('upload', $file);

        return $this->save($r, $file);
    }

    public function replace(Request $r, Document $document)
    {
        Gate::authorize('update', $document);

        return $this->save($r, $document->loanFile, $document);
    }

    private function save(Request $r, LoanFile $file, ?Document $document = null)
    {
        abort_if($file->archived_at, 422, 'Archived file is read-only for uploads.');
        $r->validate(['file' => 'required|file|max:20480|mimes:pdf,png,jpg,jpeg', 'fictional_sample' => 'sometimes|boolean', 'title' => 'nullable|string|max:150']);
        $upload = $r->file('file');
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($upload->getRealPath());
        $ext = strtolower($upload->getClientOriginalExtension());
        $allowed = ['pdf' => ['application/pdf'], 'png' => ['image/png'], 'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg']];
        if (! isset($allowed[$ext]) || ! in_array($mime, $allowed[$ext], true)) {
            throw ValidationException::withMessages(['file' => 'File content and extension must agree (PDF, PNG, JPG).']);
        }
        $bytes = $upload->getSize();
        $hash = hash_file('sha256', $upload->getRealPath());
        $storage = app(PrivateStorage::class);
        $key = 'quarantine/'.Str::uuid().'/'.Str::uuid();
        $scan = ['status' => 'Pending', 'clean' => null]; // Real scanning is performed by the pinned asynchronous pipeline.
        abort_if(($scan['clean'] ?? null) === false, 422, 'Document quarantined.');
        $version = DB::transaction(function () use ($r, $file, $document, $upload, $mime, $bytes, $hash, $storage, $key, $scan) {
            $org = Organization::lockForUpdate()->findOrFail($file->organization_id);
            Entitlements::storage($org, $bytes);
            if ($document) {
                $document = Document::lockForUpdate()->findOrFail($document->id);
            } else {
                $document = Document::create(['organization_id' => $file->organization_id, 'loan_file_id' => $file->id, 'title' => Ledger::redact($r->input('title') ?: 'Bank statement / explanation source')]);
            } $n = ($document->versions()->max('version') ?? 0) + 1;
            $stream = fopen($upload->getRealPath(), 'rb');
            try {
                $storage->put($key, $stream);
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }

            return DocumentVersion::create(['organization_id' => $file->organization_id, 'document_id' => $document->id, 'uploaded_by' => $r->user()->id, 'version' => $n, 'disk' => $storage->disk(), 'storage_key' => $key, 'original_name' => Ledger::redact(mb_substr(basename($upload->getClientOriginalName()), 0, 200)), 'mime' => $mime, 'bytes' => $bytes, 'sha256' => $hash, 'scan_status' => $scan['status'], 'validation_status' => $r->boolean('fictional_sample') ? 'fictional' : 'accepted_quarantine']);
        });
        DB::table('bank_scans')->insert(['organization_id' => $file->organization_id, 'document_version_id' => $version->id, 'sha256' => $hash, 'status' => 'Pending', 'adapter' => 'pending', 'data' => '{}', 'created_at' => now()]);
        Audit::record($document ? 'document.version_added' : 'document.uploaded', $file->organization_id, $file->id, ['document_id' => $version->document_id, 'version' => $version->version, 'sha256' => $hash], 'shared');

        return response()->json(['document_id' => $version->document_id, 'version' => $version, 'message' => 'Original preserved in quarantine. Start a pinned bank analysis to scan and extract.', 'warning' => 'Processing is blocked unless malware scan is Clean. Unavailable never means clean.'], 201);
    }

    public function content(Request $r, DocumentVersion $version)
    {
        $document = $version->document;
        Gate::authorize('view', $document);
        abort_if(Access::broker($r->user(), $document->organization_id) && $version->uploaded_by !== $r->user()->id, 403);
        abort_unless($document->organization_id === $version->organization_id, 404);
        abort_if(in_array($version->scan_status, ['infected', 'failed'], true), 423);
        $verdict = DB::table('bank_scans')->where('document_version_id', $version->id)->where('organization_id', $version->organization_id)->latest('id')->first();
        $legacyDemo = $document->category === 'fixture' || ($document->loanFile->is_demo && $version->validation_status === 'accepted_sample');
        abort_unless(($verdict && $verdict->status === 'Clean') || (! $verdict && $legacyDemo), 423, 'Original is quarantined pending a successful scan.');
        $download = $r->boolean('download');
        Audit::record($download ? 'document.downloaded' : 'document.previewed', $document->organization_id, $document->loan_file_id, ['version_id' => $version->id], 'shared');
        $stream = Storage::disk($version->disk)->readStream($version->storage_key);
        abort_unless(is_resource($stream), 404);

        return response()->stream(function () use ($stream) {
            fpassthru($stream);
            fclose($stream);
        }, 200, ['Content-Type' => $version->mime, 'Content-Disposition' => ($download ? 'attachment' : 'inline').'; filename="source-document.'.match ($version->mime) {
            'application/pdf' => 'pdf','image/png' => 'png',default => 'jpg'
        }.'"', 'Cache-Control' => 'private, no-store, max-age=0', 'X-Content-Type-Options' => 'nosniff', 'Content-Security-Policy' => "sandbox; default-src 'none'"]);
    }
}
