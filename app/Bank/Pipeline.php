<?php

namespace App\Bank;

use App\Bank\ProcessingException as RuntimeException;
use App\Bank\Providers\IntegrityProvider;
use App\Jobs\PollBankIntegrity;
use App\Models\Bank\Run;
use App\Models\DocumentVersion;
use App\Models\LoanFile;
use App\Models\Organization;
use App\Models\User;
use App\Services\Audit;
use App\Services\Entitlements;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Pipeline
{
    public function execute(Run $run): void
    {
        if (in_array($run->status, ['Completed', 'PartiallyCompleted', 'NeedsReview', 'Cancelled'], true)) {
            return;
        }
        $user = User::findOrFail($run->requested_by);
        $file = LoanFile::findOrFail($run->loan_file_id);
        Gate::forUser($user)->authorize('review', $file);
        $this->stage($run, 'Validating');
        $partial = false;
        $seen = [];
        $rule = $run->rule_id ? DB::table('bank_rules')->find($run->rule_id) : null;
        $policy = $rule ? json_decode($rule->policy, true) : [];
        $tolerance = (int) ($policy['reconciliation_tolerance_minor'] ?? 1);
        foreach (Ledger::query('bank_inputs', $run)->orderBy('id')->get() as $input) {
            $version = DocumentVersion::where('organization_id', $run->organization_id)->findOrFail($input->document_version_id);
            if ($version->document->loan_file_id !== $run->loan_file_id) {
                throw new RuntimeException('Pinned input is not in authorized loan file');
            }
            Gate::forUser($user)->authorize('view', $version->document);
            $tmp = storage_path('app/bank-work/'.Str::uuid());
            mkdir($tmp, 0700, true);
            $path = $tmp.'/original';
            try {
                $source = Storage::disk($version->disk)->readStream($version->storage_key);
                $dest = fopen($path, 'xb');
                stream_copy_to_stream($source, $dest, config('bank.max_bytes') + 1);
                fclose($source);
                fclose($dest);
                if (filesize($path) > config('bank.max_bytes') || ! hash_equals($input->sha256, hash_file('sha256', $path))) {
                    throw new RuntimeException('Original hash or size verification failed');
                }
                $scan = app(Scanner::class)->scan($path, $file->is_demo && in_array($version->validation_status, ['accepted_sample', 'fictional'], true));
                DB::table('bank_scans')->insert(['organization_id' => $run->organization_id, 'document_version_id' => $version->id, 'sha256' => $input->sha256, 'status' => $scan['status'], 'adapter' => $scan['adapter'], 'data' => Ledger::json($scan), 'created_at' => now()]);
                DB::table('document_versions')->where('id', $version->id)->where('organization_id', $run->organization_id)->update(['scan_status' => $scan['status']]);
                if (in_array($scan['status'], ['Rejected', 'ScanFailed'], true)) {
                    throw new RuntimeException('Malware scan '.$scan['status'].'; original remains quarantined');
                }
                if ($scan['status'] !== 'Clean') {
                    $this->finding($run, 'scan-unscanned:'.$version->id, ['category' => 'missing_evidence', 'code' => 'malware_scan_unscanned', 'observation' => 'Malware scanner unavailable. Original marked unscanned, not clean. Review can continue without a virus-scan certification.', 'document_version_id' => $version->id]);
                    $partial = true;
                }
                if (isset($seen[$input->sha256])) {
                    $this->finding($run, 'duplicate_original:'.$version->id, ['category' => 'missing_evidence', 'code' => 'duplicate_original', 'observation' => 'Identical original bytes already processed; duplicate extraction excluded by SHA-256 provenance', 'document_version_id' => $version->id, 'original_version_id' => $seen[$input->sha256]]);
                    $partial = true;

                    continue;
                }
                $seen[$input->sha256] = $version->id;
                $this->stage($run, 'Extracting');
                $manifest = app(DocumentParser::class)->parse($path, $tmp.'/derived');
                foreach ($manifest['metadata'] as $key => $value) {
                    if (! in_array($key, ['creationDate', 'modDate'], true) || ! preg_match('/^D:\d{14}(?:[Z+\-].*)?$/D', $value)) {
                        $manifest['metadata'][$key] = Ledger::redact($value);
                    }
                }
                if (count($manifest['pages']) + Ledger::query('bank_pages', $run)->where('record_key', 'not like', $version->id.':%')->count() > config('bank.max_run_pages')) {
                    throw new RuntimeException('Per-run page limit exceeded; split the review into approved bounded runs');
                }
                $hashes = [];
                foreach ($manifest['pages'] as $p) {
                    $artifact = 'bank-derived/'.$run->organization_id.'/'.$run->loan_file_id.'/'.$run->id.'/'.$version->id.'/'.$p['render'];
                    $renderBytes = filesize($tmp.'/derived/'.$p['render']);
                    DB::transaction(function () use ($run, $version, $p, $manifest, $tmp, $artifact, $renderBytes) {
                        $org = Organization::lockForUpdate()->findOrFail($run->organization_id);
                        if (! Ledger::query('bank_pages', $run)->where('record_key', $version->id.':'.$p['number'])->exists()) {
                            Entitlements::storage($org, $renderBytes);
                        }
                        if (! Storage::disk($version->disk)->put($artifact, file_get_contents($tmp.'/derived/'.$p['render']), ['visibility' => 'private'])) {
                            throw new RuntimeException('Private derivative storage write failed');
                        }
                        $words = array_map(fn ($w) => ['text' => Ledger::redact($w['text']), 'region' => $w['region']], $p['words']);
                        Ledger::append('bank_pages', $run, $version->id.':'.$p['number'], ['document_version_id' => $version->id, 'page' => $p['number'], 'width' => $p['width'], 'height' => $p['height'], 'text' => Ledger::redact($p['text']), 'words' => $words, 'sha256' => $p['sha256'], 'artifact_bytes' => $renderBytes, 'artifact_disk' => $version->disk, 'artifact_key' => $artifact, 'metadata' => $manifest['metadata'], 'structural' => $manifest['structural'], 'fonts' => $p['fonts'] ?? [], 'declared_number' => $p['declared_number'] ?? null, 'declared_total' => $p['declared_total'] ?? null]);
                    });
                    if (($p['declared_number'] ?? $p['number']) !== $p['number'] || ($p['declared_total'] ?? count($manifest['pages'])) !== count($manifest['pages'])) {
                        $partial = true;
                        $this->finding($run, 'page_sequence:'.$version->id.':'.$p['number'], ['category' => 'missing_evidence', 'code' => 'missing_or_reordered_pages', 'observation' => 'Printed page numbering/total differs from uploaded sequence; request complete original', 'document_version_id' => $version->id, 'page' => $p['number']]);
                    }
                    if (count($p['fonts'] ?? []) > 4) {
                        $this->finding($run, 'font_layout:'.$version->id.':'.$p['number'], ['category' => 'integrity_indicator', 'code' => 'native_font_variation', 'observation' => 'Native parser observed more than four font names. This is contextual variation, not proof of tampering', 'document_version_id' => $version->id, 'page' => $p['number'], 'font_names' => $p['fonts']]);
                    }
                    if (isset($hashes[$p['sha256']])) {
                        $partial = true;
                        $this->finding($run, 'duplicate_page:'.$version->id.':'.$p['number'], ['category' => 'missing_evidence', 'code' => 'duplicate_page', 'observation' => 'Duplicate rendered/text page fingerprint; confirm page sequence and coverage', 'document_version_id' => $version->id, 'page' => $p['number']]);
                    }
                    $hashes[$p['sha256']] = true;
                }
                foreach ($manifest['structural'] as $code) {
                    $this->finding($run, $code.':'.$version->id, ['category' => 'integrity_indicator', 'code' => $code, 'observation' => 'PDF structural indicator (not proof of alteration); original preserved', 'document_version_id' => $version->id]);
                }
                $run->update(['stage' => 'DocumentClassificationAndExtraction']);
                $d = app(StatementExtractor::class)->extract($manifest, $run, $version->id, $tmp.'/derived');
                // Model output is validated even when supplied by a test adapter.
                $d = ExtractionSchema::validate(array_intersect_key($d, ExtractionSchema::jsonSchema()['properties']), array_column($manifest['pages'], 'number'));
                $account = Ledger::hash([$d['institution'], $d['holder'], $d['masked_account']]);
                $statement = Ledger::append('bank_statements', $run, (string) $version->id, array_diff_key($d, ['transactions' => true]) + ['account_key' => $account, 'document_version_id' => $version->id, 'account_identity_status' => 'unverified_candidate', 'metadata' => $manifest['metadata']]);
                $dedup = [];
                foreach ($d['transactions'] as $t) {
                    $key = Ledger::hash([$version->id, $t['page'], $t['source_row']]);
                    if (isset($dedup[$key])) {
                        $this->finding($run, 'chunk_duplicate:'.$key, ['category' => 'extraction_uncertainty', 'code' => 'duplicate_extraction_artifact', 'observation' => 'Repeated source row excluded by page/row provenance; legitimate separate rows retained', 'document_version_id' => $version->id, 'page' => $t['page']]);

                        continue;
                    }
                    $dedup[$key] = true;
                    $category = $t['category_suggestion'];
                    if ($category === 'unresolved') {
                        foreach (['payroll_income' => '/payroll|salary/i', 'business_revenue' => '/merchant sales|business revenue/i', 'owner_distribution' => '/owner distribution/i', 'own_transfer' => '/own account transfer/i', 'loan_proceeds' => '/loan proceeds/i', 'refund' => '/refund|reversal/i', 'cash_deposit' => '/cash deposit/i'] as $c => $pattern) {
                            if (preg_match($pattern, $t['description'])) {
                                $category = $c;
                                break;
                            }
                        }
                    }
                    $t['initial_category'] = $category;
                    $t['suggestion_source'] = 'extraction suggestion + deterministic label hints v1; eligibility separate';
                    $t['status'] = 'unverified_extraction';
                    // Exact native localization only when the complete description is a unique native word.
                    $page = array_values(array_filter($manifest['pages'], fn ($p) => $p['number'] === $t['page']))[0];
                    $matches = array_values(array_filter($page['words'], fn ($w) => $w['text'] === $t['description']));
                    if (count($matches) === 1) {
                        $t['region'] = $matches[0]['region'];
                        $t['region_verified'] = true;
                    }
                    if (Ledger::query('bank_transactions', $run)->count() >= config('bank.max_run_transactions') && ! Ledger::query('bank_transactions', $run)->where('provenance_key', $key)->exists()) {
                        throw new RuntimeException('Per-run transaction limit exceeded');
                    }
                    Ledger::query('bank_transactions', $run)->insertOrIgnore(Ledger::scope($run) + ['statement_id' => $statement['id'], 'document_version_id' => $version->id, 'provenance_key' => $key, 'account_key' => $account, 'posting_date' => ExtractionSchema::date($t['posting_date']), 'credit_minor' => Money::minor($t['credit']), 'debit_minor' => Money::minor($t['debit']), 'balance_minor' => Money::minor($t['running_balance']), 'data' => Ledger::json($t), 'created_at' => now()]);
                }
                $this->stage($run, 'Reconciling');
                $entries = array_values(array_filter(Ledger::rows('bank_transactions', $run), fn ($t) => $t['statement_id'] === $statement['id']));
                foreach (Reconcile::statement($d, $entries, $tolerance) as $i => $issue) {
                    $partial = true;
                    $this->finding($run, 'reconcile:'.$version->id.':'.$i, $issue + ['document_version_id' => $version->id]);
                }
                $this->stage($run, 'Analyzing');
                $external = app(IntegrityProvider::class)->analyze($run, $version->id, $path, $input->sha256);
                Ledger::append('bank_findings', $run, 'external-status:'.$version->id.':'.Ledger::hash($external), ['category' => 'provider_status', 'severity' => 'info', 'code' => 'external_integrity', 'observation' => $external['reason'] ?? ('Specialist integrity '.$external['status']), 'external' => $external, 'document_version_id' => $version->id, 'rule' => 'provider-contract-v1', 'explanation' => 'Internal and external checks are independent; unavailable/pending is never no fraud detected', 'action' => 'Review specialist completeness', 'status' => 'open']);
                if ($external['status'] !== 'Completed') {
                    $partial = true;
                }
                if ($external['status'] === 'Pending') {
                    PollBankIntegrity::dispatch($run->id, $version->id)->delay(now()->addSeconds(30));
                }
                foreach ($external['signals'] ?? [] as $i => $s) {
                    $this->finding($run, 'external:'.$version->id.':'.$i, ['category' => 'integrity_indicator', 'code' => $s['identifier'], 'observation' => $s['observation'], 'document_version_id' => $version->id, 'page' => $s['page'], 'provider' => $s]);
                }
            } finally {
                $this->removeWork($tmp);
            }
        }
        $this->stage($run, 'Analyzing');
        foreach (Reconcile::continuity(Ledger::rows('bank_statements', $run), $tolerance) as $i => $f) {
            $partial = true;
            $this->finding($run, 'continuity:'.$i, $f);
        }
        app(TransferEngine::class)->match($run);
        $snapshot = app(CalculateIncome::class)->calculate($run);
        $this->stage($run, $partial ? 'PartiallyCompleted' : ($snapshot['data']['status'] === 'reviewable' ? 'Completed' : 'NeedsReview'));
        $run->update(['completed_at' => now(), 'error_code' => null]);
        Audit::record('bank.analysis_finished', $run->organization_id, $run->loan_file_id, ['run_id' => $run->id], 'internal');
    }

    public function stage(Run $r, string $stage): void
    {
        Gate::forUser(User::findOrFail($r->requested_by))->authorize('review', LoanFile::findOrFail($r->loan_file_id));
        if ($r->fresh()->cancel_requested) {
            $r->update(['status' => 'Cancelled', 'stage' => 'Cancelled', 'completed_at' => now()]);
            throw new RuntimeException('Cancelled');
        }
        $r->update(['status' => $stage, 'stage' => $stage]);
    }

    public function finding(Run $r, string $key, array $data): array
    {
        return Ledger::append('bank_findings', $r, $key, $data + ['severity' => 'review', 'rule' => 'internal-v1', 'explanation' => 'Evidence indicator requires review, not proof or a loan decision', 'action' => 'Review original source and request clarification', 'status' => 'open']);
    }

    private function removeWork(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $f) {
            if ($f !== '.' && $f !== '..') {
                $p = $dir.'/'.$f;
                if (is_dir($p)) {
                    $this->removeWork($p);
                } else {
                    unlink($p);
                }
            }
        }rmdir($dir);
    }
}
