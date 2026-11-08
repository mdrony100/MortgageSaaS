<?php

namespace App\Bank\Providers;

use App\Bank\Ledger;
use App\Models\Bank\Run;
use App\Models\LoanFile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;

class OcrolusDetect implements IntegrityProvider
{
    public function analyze(Run $run, int $versionId, string $originalPath, string $sha256): array
    {
        return $this->original($run, $versionId, $originalPath, $sha256) + ['adapter_contract_version' => 'detect-v2/docs-2026-11-08'];
    }

    private function original(Run $run, int $versionId, string $originalPath, string $sha256): array
    {
        Gate::forUser(User::findOrFail($run->requested_by))->authorize('review', LoanFile::findOrFail($run->loan_file_id));
        $cfg = config('bank.integrity');
        if ($cfg['provider'] !== 'ocrolus' || ! $cfg['approved'] || ! $cfg['token']) {
            return ['status' => 'Unavailable', 'provider' => $cfg['provider'], 'reason' => 'Specialist not configured or approved; not a clean result'];
        }
        $book = DB::table('bank_vendor_books')->where('organization_id', $run->organization_id)->where('loan_file_id', $run->loan_file_id)->first();
        if (! $book) {
            return ['status' => 'Unavailable', 'provider' => 'ocrolus', 'reason' => 'A dedicated tenant/file Book must be configured by the operator'];
        }
        $key = Ledger::hash(['ocrolus', $run->organization_id, $run->loan_file_id, $versionId, $sha256]);
        try {
            $call = Budget::reserve($run, $key, 'ocrolus', 'detect-v2/docs-2026-11-08', $cfg['reservation_micro_usd_per_document']);
            if ($call['status'] === 'completed') {
                return $call['data']['result'];
            }
            if (in_array($call['status'], ['unknown', 'submitting', 'failed'], true)) {
                return ['status' => 'Failed', 'provider' => 'ocrolus', 'reason' => 'Uncertain submission; no automatic duplicate upload'];
            }
            $data = $call['data'];
            if (! isset($data['uploaded_doc_uuid'])) {
                if (! Budget::claimSubmission($call['id'])) {
                    return ['status' => 'Pending', 'provider' => 'ocrolus', 'reason' => 'Another submission is in progress; no duplicate original upload'];
                }
                $h = fopen($originalPath, 'rb');
                try {
                    $upload = Http::withToken($cfg['token'])->acceptJson()->connectTimeout(10)->timeout(90)->attach('upload', $h, 'original.pdf')->post('https://api.ocrolus.com/v1/book/upload', ['book_uuid' => $book->book_uuid, 'doc_name' => $sha256]);
                } catch (\Throwable) {
                    Budget::update($call['id'], 'unknown', ['code' => 'submission_transport']);

                    return ['status' => 'Failed', 'provider' => 'ocrolus', 'reason' => 'Upload outcome unknown; operator review required'];
                } finally {
                    fclose($h);
                }
                if ($upload->status() === 429) {
                    Budget::update($call['id'], 'rate_limited', []);

                    return ['status' => 'Pending', 'provider' => 'ocrolus', 'reason' => 'Rate limited; retry with bounded backoff'];
                }
                $uuid = $upload->json('response.uploaded_docs.0.uuid') ?? $upload->json('response.original_existing_doc_uuid.0');
                if (! $upload->successful() || ! is_string($uuid) || ! preg_match('/^[0-9a-f-]{36}$/i', $uuid)) {
                    Budget::update($call['id'], 'failed', ['http_status' => $upload->status()]);

                    return ['status' => 'Failed', 'provider' => 'ocrolus', 'reason' => 'Upload rejected or invalid response'];
                }
                $data = ['uploaded_doc_uuid' => $uuid, 'book_uuid' => $book->book_uuid, 'sha256' => $sha256, 'polls' => 0];
                Budget::update($call['id'], 'pending', $data);
            }
            if (($data['polls'] ?? 0) >= 12) {
                return ['status' => 'Failed', 'provider' => 'ocrolus', 'reason' => 'Polling limit reached; operator review required'];
            }
            $data['polls'] = ($data['polls'] ?? 0) + 1;
            Budget::update($call['id'], 'pending', $data);
            $resp = Http::withToken($cfg['token'])->acceptJson()->connectTimeout(10)->timeout(45)->get('https://api.ocrolus.com/v2/detect/uploaded_doc/'.$data['uploaded_doc_uuid'].'/signals', ['exclude_dashboard_url' => 'true']);
            if (! $resp->successful()) {
                return ['status' => 'Pending', 'provider' => 'ocrolus', 'reason' => 'Specialist polling unavailable', 'job_id' => $data['uploaded_doc_uuid']];
            }
            $doc = $resp->json('doc_analysis.0');
            if (! is_array($doc) || ($doc['uploaded_doc_uuid'] ?? null) !== $data['uploaded_doc_uuid']) {
                return ['status' => 'Failed', 'provider' => 'ocrolus', 'reason' => 'Specialist result identity mismatch'];
            }
            if (($doc['detect_status'] ?? null) === 'PENDING') {
                return ['status' => 'Pending', 'provider' => 'ocrolus', 'job_id' => $data['uploaded_doc_uuid']];
            }
            if (($doc['detect_status'] ?? null) !== 'COMPLETED') {
                return ['status' => 'Failed', 'provider' => 'ocrolus', 'reason' => 'Specialist unable to process original'];
            }
            $signals = [];
            foreach ($doc['form_analysis'] ?? [] as $form) {
                foreach ($form['signals'] ?? [] as $s) {
                    $signals[] = ['identifier' => $s['identifier'] ?? 'unmapped', 'observation' => Ledger::redact($s['description'] ?? $s['display_name'] ?? 'Indicator requires review'), 'type' => $s['signal_type'] ?? 'INFO', 'page' => $s['page_number'] ?? null, 'evidence_reference' => ['uploaded_doc_uuid' => $data['uploaded_doc_uuid'], 'form_uuid' => $form['form_uuid'] ?? null], 'provider_version' => $form['form_authenticity']['version'] ?? 'unspecified'];
                }
            }
            $result = ['status' => 'Completed', 'provider' => 'ocrolus', 'job_id' => $data['uploaded_doc_uuid'], 'signals' => $signals, 'notice' => 'Indicators are not proof or a loan decision'];
            Budget::update($call['id'], 'completed', $data + ['result' => $result]);

            return $result;
        } catch (\Throwable) {
            return ['status' => 'Unavailable', 'provider' => 'ocrolus', 'reason' => 'Budget, credentials or provider unavailable; review required'];
        }
    }
}
