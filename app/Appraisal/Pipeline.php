<?php

namespace App\Appraisal;

use App\Bank\DocumentParser;
use App\Bank\Ledger;
use App\Bank\Money;
use App\Bank\ProcessingException;
use App\Bank\Scanner;
use App\Models\DocumentVersion;
use App\Models\LoanFile;
use App\Models\Organization;
use App\Models\Review\Run;
use App\Models\User;
use App\Services\Entitlements;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Pipeline
{
    public function execute(Run $r): void
    {
        if (in_array($r->status, ['NeedsReview', 'Completed', 'Cancelled'], true)) {
            return;
        }app()->instance(BatchBudget::class, new BatchBudget);
        $start = microtime(true);
        $file = LoanFile::findOrFail($r->loan_file_id);
        $images = [];
        $duplicateCandidates = 0;
        $currentImages = 0;
        if ($r->parent_id) {
            $parent = Run::where('organization_id', $r->organization_id)->where('loan_file_id', $r->loan_file_id)->findOrFail($r->parent_id);
            $images = Store::rows($parent, 'image');
        }
        $pages = 0;
        $processedDocuments = 0;
        $worker = [];
        foreach ($r->inputs as $pin) {
            $this->stage($r, 'Validating');
            $v = DocumentVersion::where('organization_id', $r->organization_id)->findOrFail($pin['id']);
            if ($v->document->loan_file_id !== $r->loan_file_id) {
                throw new ProcessingException('Input file scope invalid');
            }Gate::forUser(User::findOrFail($r->requested_by))->authorize('view', $v->document);
            if (! hash_equals($pin['sha256'], $v->sha256)) {
                throw new ProcessingException('Pinned original metadata hash mismatch');
            }
            $done = Store::query($r)->where('kind', 'document_completed')->where('record_key', (string) $v->id)->first();
            if ($done) {
                $d = json_decode($done->data, true);
                $pages += $d['pages'];
                $worker[] = $d['worker_observation'];
                $cachedImages = array_values(array_filter(Store::rows($r, 'image'), fn ($image) => $image['data']['document_version_id'] === $v->id));
                $images = array_merge($images, $cachedImages);
                $currentImages += count($cachedImages);

                continue;
            }
            if ($processedDocuments >= 4) {
                throw new ContinueReview('Bounded document checkpoint; queued continuation');
            }$processedDocuments++;

            $tmp = storage_path('app/bank-work/review-'.Str::uuid());
            mkdir($tmp, 0700, true);
            try {
                $in = Storage::disk($v->disk)->readStream($v->storage_key);
                $out = fopen($tmp.'/original', 'xb');
                stream_copy_to_stream($in, $out, config('bank.max_bytes') + 1);
                fclose($in);
                fclose($out);
                if (! hash_equals($pin['sha256'], hash_file('sha256', $tmp.'/original'))) {
                    throw new ProcessingException('Original hash mismatch');
                }$scan = app(Scanner::class)->scan($tmp.'/original', $file->is_demo && in_array($v->validation_status, ['fictional', 'accepted_sample'], true));
                DB::table('bank_scans')->insert(['organization_id' => $r->organization_id, 'document_version_id' => $v->id, 'sha256' => $v->sha256, 'status' => $scan['status'], 'adapter' => $scan['adapter'], 'data' => Ledger::json($scan), 'created_at' => now()]);
                if (in_array($scan['status'], ['Rejected', 'ScanFailed'], true)) {
                    throw new ProcessingException('Malware scan '.$scan['status'].'; original remains quarantined');
                }
                if ($scan['status'] !== 'Clean') {
                    $this->finding($r, 'scan-unscanned:'.$v->id, 'missing_evidence', 'Malware scanner unavailable. Original marked unscanned, not clean. Review can continue without a virus-scan certification.', ['document_version_id' => $v->id, 'what_verify' => 'Install ClamAV before treating this original as scanned']);
                }
                $this->stage($r, 'Extracting');
                $m = app(DocumentParser::class)->parse($tmp.'/original', $tmp.'/derived');
                $pages += count($m['pages']);
                $worker[] = $m['observed_resources'] ?? [];
                if ($pages > config('review.max_run_pages')) {
                    throw new ProcessingException('Review page limit exceeded');
                }$seen = [];
                foreach ($m['pages'] as $p) {
                    if (! empty($p['image_limitations'])) {
                        $this->finding($r, 'image-coverage:'.$v->id.':'.$p['number'], 'missing_evidence', 'Embedded-image coverage limitation; full rendered page retained', ['document_version_id' => $v->id, 'page' => $p['number'], 'what_verify' => implode('; ', $p['image_limitations'])]);
                    }
                    $this->artifact($r, $v, $p['render'], $tmp.'/derived/'.$p['render'], 'page', ($p + ['document_version_id' => $v->id]));
                    if (isset($seen[$p['sha256']])) {
                        $this->finding($r, 'duplicate-page:'.$v->id.':'.$p['number'], 'missing_evidence', 'Repeated page fingerprint; legitimate exhibit repetition is possible', ['document_version_id' => $v->id, 'page' => $p['number']]);
                    }$seen[$p['sha256']] = true;
                    if (($p['declared_number'] ?? $p['number']) !== $p['number'] || ($p['declared_total'] ?? count($m['pages'])) !== count($m['pages'])) {
                        $this->finding($r, 'page-order:'.$v->id.':'.$p['number'], 'missing_evidence', 'Printed page numbering/coverage differs; confirm complete ordered original', ['document_version_id' => $v->id, 'page' => $p['number']]);
                    }foreach ($p['images'] ?? [] as $image) {
                        if ($currentImages >= config('review.max_images')) {
                            throw new ProcessingException('Embedded image review limit reached; original preserved, select bounded documents or manual review');
                        }
                        $currentImages++;
                        $photo = $this->artifact($r, $v, $image['render'], $tmp.'/derived/'.$image['render'], 'image', $image + ['page' => $p['number'], 'document_version_id' => $v->id]);
                        foreach ($images as $prior) {
                            if ($duplicateCandidates >= config('review.max_duplicate_candidates')) {
                                $this->finding($r, 'duplicate-coverage-limit', 'missing_evidence', 'Duplicate candidate review capped; all source images retained but pair comparisons are not exhaustive');
                                break;
                            }
                            $distance = 0;
                            foreach (str_split($image['dhash']) as $ix => $hex) {
                                $distance += substr_count(decbin(hexdec($hex) ^ hexdec($prior['data']['dhash'][$ix])), '1');
                            }if ($prior['data']['sha256'] === $image['sha256'] || $distance <= 6) {
                                $duplicateCandidates++;
                                $this->finding($r, 'duplicate-photo:'.$prior['id'].':'.$photo['id'], 'photo_review', 'Exact/perceptual duplicate candidate; repeated exhibits/revisions can be legitimate', ['image_ids' => [$prior['id'], $photo['id']], 'image_references' => [['id' => $prior['id'], 'run_id' => $prior['run_id'], 'document_version_id' => $prior['data']['document_version_id'], 'page' => $prior['data']['page']], ['id' => $photo['id'], 'run_id' => $r->id, 'document_version_id' => $v->id, 'page' => $p['number']]], 'exact_pixels' => $prior['data']['sha256'] === $image['sha256'], 'dhash_hamming_distance' => $distance, 'algorithm' => '64-bit dHash, operational candidate only, not a fraud/program threshold', 'document_version_id' => $v->id, 'page' => $p['number']]);
                            }
                        }$images[] = $photo;
                    }
                }
                $a = Schema::validate(app(Extractor::class)->extract($m, $r, $v->id, $tmp.'/derived'), array_column($m['pages'], 'number'));
                Store::add($r, 'document', (string) $v->id, ['document_version_id' => $v->id, 'requested_classification' => $pin['classification'], 'original_name' => Ledger::redact($v->original_name), 'sha256' => $v->sha256, 'worker_observation' => $m['observed_resources'] ?? null, 'image_coverage' => array_map(fn ($p) => ['page' => $p['number'], 'embedded_total' => $p['images_total'] ?? count($p['images'] ?? []), 'embedded_extracted' => count($p['images'] ?? []), 'limitations' => $p['image_limitations'] ?? []], $m['pages']), 'extracted_classification' => $a['classification'], 'form' => $a['form'], 'form_version' => $a['form_version'], 'complete' => $a['complete'], 'page_coverage' => $a['pages'], 'vision_status' => config('review.vision_approved') && config('review.model') && config('bank.openai.key') ? 'configured_vision_extraction_unverified' : 'unavailable_native_manual_only']);
                if (! $a['classification'] || $a['classification'] === 'uncertain' || ($pin['classification'] !== 'uncertain' && $pin['classification'] !== $a['classification'])) {
                    $this->finding($r, 'classification:'.$v->id, 'missing_evidence', 'Document classification uncertain or differs from intake; confirm the document/exhibit path', ['document_version_id' => $v->id]);
                }
                if ($a['form'] !== null && ! in_array($a['form'], ['1004', '1025', '1007', '216'], true)) {
                    $this->finding($r, 'unsupported:'.$v->id, 'scope_limitation', 'Unsupported form or format; manual-review path, not silently equivalent', ['document_version_id' => $v->id]);
                }if (($a['form'] !== null || in_array($a['classification'], ['appraisal', 'revised_appraisal', 'rental_schedule', 'sales_comparison', 'operating_income'], true)) && ($a['form_version'] === null || ! in_array($a['form_version'], ['2005', '03/2005', '2005-03'], true))) {
                    $this->finding($r, 'form-version:'.$v->id, 'missing_evidence', 'Form/version identification requires confirmation; UAD3.6 is not assumed equivalent', ['document_version_id' => $v->id]);
                }foreach ($a['fields'] as $n => $f) {
                    if ($f['key'] === 'form' && $f['normalized'] !== null && ! in_array($f['normalized'], ['1004', '1025', '1007', '216'], true)) {
                        $this->finding($r, 'unsupported-field:'.$v->id.':'.$n, 'scope_limitation', 'Unsupported form detected in a combined report; not silently treated as a supported form', ['document_version_id' => $v->id, 'page' => $f['page']]);
                    }
                    $f = array_replace($f, ['document_version_id' => $v->id, 'normalized' => $f['normalized'], 'raw' => $f['raw'], 'excerpt' => $f['excerpt'], 'region' => null, 'region_verified' => false, 'reviewer_confirmed' => false]);
                    $page = collect($m['pages'])->firstWhere('number', $f['page']);
                    $matches = array_values(array_filter($page['words'] ?? [], fn ($w) => $w['text'] === $f['raw']));
                    if (count($matches) === 1) {
                        $f['region'] = $matches[0]['region'];
                        $f['region_verified'] = true;
                    }
                    Store::add($r, 'field', $v->id.':'.$f['key'].':'.($f['unit'] ?? '').':'.$n, $f);
                    if ($f['key'] === 'property_type' && preg_match('/^(commercial|industrial|agricultural|vacant land|five|5\+|multifamily 5)/i', $f['normalized'] ?? '')) {
                        $this->finding($r, 'property-scope:'.$v->id.':'.$n, 'scope_limitation', 'Reported property type outside confirmed residential1–4 scope; manual/program verification, no eligibility determination', ['document_version_id' => $v->id, 'page' => $f['page']]);
                    }
                    if ($f['key'] === 'units' && ($f['normalized'] === null || ! ctype_digit($f['normalized']))) {
                        $this->finding($r, 'unit-normalization:'.$v->id.':'.$n, 'extraction_uncertainty', 'Unit count unknown/not reliably normalized; confirm scope, never treat missing as0', ['document_version_id' => $v->id, 'page' => $f['page']]);
                    }
                    if ($f['key'] === 'units' && $f['normalized'] !== null && ctype_digit($f['normalized']) && ((int) $f['normalized'] < 1 || (int) $f['normalized'] > 4)) {
                        $this->finding($r, 'units-scope:'.$v->id, 'scope_limitation', 'Extracted unit count outside confirmed1–4 scope; manual review', ['document_version_id' => $v->id, 'page' => $f['page']]);
                    }
                }foreach ($a['photos'] as $n => $photo) {
                    $p = Store::add($r, 'photo_observation', $v->id.':'.$n, array_replace($photo, ['document_version_id' => $v->id, 'region' => null, 'status' => 'AI suggestion, not verified occupancy/legal use/safety/fraud']));
                    $this->finding($r, 'photo:'.$p['id'], 'photo_review', $photo['observation'], ['document_version_id' => $v->id, 'page' => $photo['page'], 'photo_observation_id' => $p['id'], 'what_verify' => $photo['why_verify'], 'uncertainty' => $photo['uncertainty']]);
                }foreach ($a['issues'] as $n => $issue) {
                    $this->finding($r, 'extract:'.$v->id.':'.$n, $issue['category'], $issue['observation'], ['document_version_id' => $v->id, 'page' => $issue['page'], 'what_verify' => $issue['what_verify']]);
                }
                Store::add($r, 'document_completed', (string) $v->id, ['document_version_id' => $v->id, 'pages' => count($m['pages']), 'worker_observation' => $m['observed_resources'] ?? [], 'notice' => 'Every extraction record for this pinned original is committed; retries reuse immutable evidence']);
            } finally {
                foreach (glob($tmp.'/derived/*') ?: [] as $f) {
                    unlink($f);
                }if (is_dir($tmp.'/derived')) {
                    rmdir($tmp.'/derived');
                }if (is_file($tmp.'/original')) {
                    unlink($tmp.'/original');
                }rmdir($tmp);
            }
        }
        $this->stage($r, 'Analyzing');
        $this->review($r);
        app(LeaseMatcher::class)->candidates($r);
        $r->update(['status' => 'NeedsReview', 'error' => null, 'metrics' => array_replace($r->fresh()->metrics ?? [], ['wall_seconds' => round(microtime(true) - $start, 3), 'php_peak_bytes' => memory_get_peak_usage(true), 'pages' => $pages, 'worker_observations' => $worker, 'php_peak_scope' => 'worker process lifetime, not isolated per-run allocation', 'notice' => 'Observed local run, not production capacity'])]);
    }

    private function artifact(Run $r, $v, string $name, string $path, string $kind, array $data): array
    {
        $key = 'review-derived/'.$r->organization_id.'/'.$r->loan_file_id.'/'.$r->id.'/'.$v->id.'/'.$name;

        return DB::transaction(function () use ($r, $v, $name, $path, $kind, $data, $key) {
            $org = Organization::lockForUpdate()->findOrFail($r->organization_id);
            $old = Store::query($r)->where('kind', $kind)->where('record_key', $v->id.':'.$name)->first();
            if ($old) {
                $prior = json_decode($old->data, true);
                $hash = $prior['artifact_sha256'] ?? hash('sha256', Storage::disk($prior['disk'])->get($prior['storage_key']));
                if (! hash_equals($hash, hash_file('sha256', $path))) {
                    throw new ProcessingException('Derived evidence changed during retry; preserve old artifact and start a new review version');
                }

                return array_replace((array) $old, ['data' => $prior]);
            }
            Entitlements::storage($org, filesize($path));
            Storage::disk($v->disk)->put($key, file_get_contents($path), ['visibility' => 'private']);
            $safe = array_intersect_key($data, array_flip(['number', 'page', 'width', 'height', 'sha256', 'dhash', 'document_version_id', 'region']));

            return Store::add($r, $kind, $v->id.':'.$name, $safe + ['disk' => $v->disk, 'storage_key' => $key, 'artifact_bytes' => filesize($path), 'artifact_sha256' => hash_file('sha256', $path)]);
        });
    }

    public function stage(Run $r, string $s): void
    {
        Gate::forUser(User::findOrFail($r->requested_by))->authorize('review', LoanFile::findOrFail($r->loan_file_id));
        if ($r->fresh()->cancel_requested) {
            $r->update(['status' => 'Cancelled']);
            throw new ProcessingException('Cancelled');
        }$r->update(['status' => $s]);
    }

    public function finding(Run $r, string $key, string $cat, string $obs, array $evidence = []): array
    {
        return Store::add($r, 'finding', $key, ['category' => $cat, 'priority' => 'review', 'observation' => Ledger::redact($obs), 'evidence' => $evidence, 'guideline' => 'No explicit rule located', 'impact' => 'Review affected input before confirmation', 'what_verify' => $evidence['what_verify'] ?? 'Verify Against Program Guidelines; review original evidence', 'status' => 'open', 'original_suggestion' => true, 'actor' => 'system_evidence_analysis', 'at' => now()->toIso8601String(), 'checklist_version' => $r->checklist['version'] ?? null]);
    }

    public function review(Run $r): void
    {
        $fields = Store::facts($r);
        $by = [];
        foreach ($fields as $f) {
            $by[$f['data']['key'].':'.($f['data']['unit'] ?? '')][] = $f;
        }foreach ($by as $key => $same) {
            // A report bundle intentionally contains different document classes/forms/versions.
            // Preserve these per-original identities without mislabeling the bundle inconsistent.
            if (! in_array(explode(':', $key, 2)[0], ['classification', 'form', 'form_version'], true) && count(array_unique(array_column(array_column($same, 'data'), 'normalized'))) > 1) {
                $this->finding($r, 'conflict:'.$key, 'appraisal_inconsistency', 'Conflicting extracted values preserved for '.$key, ['field_ids' => array_column($same, 'id')]);
            }
        }foreach (['address', 'parcel', 'units', 'appraised_value', 'condition', 'inspection_date', 'report_date', 'signature_presence', 'certification', 'comparables', 'reconciliation'] as $key) {
            if (! isset($by[$key.':'])) {
                $this->finding($r, 'missing:'.$key, 'missing_evidence', 'Missing or unreadable '.$key.'; no value inferred');
            }
        }foreach (['site_area', 'agricultural_use', 'mixed_use', 'adu', 'subject_to', 'repairs', 'sale_history', 'contract_price', 'concessions', 'ownership_history', 'market_trends', 'marketing_time', 'access', 'flood', 'zoning'] as $key) {
            if (isset($by[$key.':'])) {
                $this->finding($r, 'verify:'.$key, 'program_verification', 'Review reported '.$key.' in context; no universal threshold or eligibility decision', ['field_ids' => array_column($by[$key.':'], 'id')]);
            }
        }
        $sections = [
            'identity_completeness' => ['address', 'parcel', 'legal_description', 'borrower', 'entity', 'appraiser', 'inspection_date', 'effective_date', 'report_date', 'signature_presence', 'certification'],
            'property_characteristics' => ['property_type', 'units', 'adu', 'accessory_structures', 'condo', 'coop', 'mixed_use', 'zoning', 'access', 'utilities', 'flood'],
            'site_marketability' => ['site_area', 'parcels', 'excess_land', 'external_influences', 'marketability', 'allocation', 'agricultural_use'],
            'condition_repairs' => ['condition', 'quality', 'subject_to', 'repairs', 'deferred_maintenance', 'completion'],
            'market_comparables' => ['comparables', 'comparable_rents', 'adjustments', 'concessions', 'market_trends', 'marketing_time'],
            'value_support' => ['appraised_value', 'comparable_indications', 'reconciliation', 'sale_history'],
        ];
        foreach ($sections as $section => $keys) {
            $evidence = array_values(array_filter($fields, fn ($f) => in_array(explode('.', $f['data']['key'])[0], $keys, true)));
            if ($evidence) {
                $this->finding($r, 'section:'.$section, $section, 'Review reported '.str_replace('_', ' ', $section).' and supporting explanations; extracted facts are not independent certification', ['field_ids' => array_column($evidence, 'id'), 'what_verify' => 'Compare the linked sources and narratives. Verify Against Program Guidelines; no universal comparable/site/adjustment threshold is assumed.']);
            }
        }
        if (($r->context['transaction'] ?? 'unknown') === 'purchase') {
            $this->finding($r, 'transaction:purchase', 'purchase_review', 'Reconcile contract, amendments, concessions and appraisal transaction facts; separately confirm qualifying value basis', ['field_ids' => array_column(array_filter($fields, fn ($f) => in_array($f['data']['key'], ['contract_price', 'concessions', 'appraised_value'], true)), 'id')]);
        }
        if (in_array($r->context['transaction'] ?? 'unknown', ['rate_term', 'cash_out'], true)) {
            $this->finding($r, 'transaction:refinance', 'refinance_review', 'Verify reported ownership, transfer/seasoning history and selected refinance program; no universal seasoning rule', ['field_ids' => array_column(array_filter($fields, fn ($f) => in_array($f['data']['key'], ['ownership_history', 'sale_history', 'transfer_date'], true)), 'id')]);
        }
        foreach (['units', 'loan_occupancy', 'transaction', 'program'] as $k) {
            $matches = $by[$k.':'] ?? [];
            foreach ($matches as $f) {
                if (isset($r->context[$k]) && (string) $r->context[$k] !== (string) $f['data']['normalized']) {
                    $this->finding($r, 'user-conflict:'.$f['id'], 'user_extraction_conflict', 'User-provided '.$k.' differs from extracted source; both retained, no automatic replacement', ['field_ids' => [$f['id']], 'user_value' => $r->context[$k], 'extracted_value' => $f['data']['normalized']]);
                }
            }
        }
        if ($r->parent_id) {
            $parent = Run::where('organization_id', $r->organization_id)->where('loan_file_id', $r->loan_file_id)->findOrFail($r->parent_id);
            $old = [];
            foreach (Store::facts($parent) as $f) {
                $old[$f['data']['key'].':'.($f['data']['unit'] ?? '')][] = $f;
            }foreach (array_unique(array_merge(array_keys($by), array_keys($old))) as $key) {
                $new = $by[$key] ?? [];
                $before = $old[$key] ?? [];
                if (array_column(array_column($before, 'data'), 'normalized') !== array_column(array_column($new, 'data'), 'normalized')) {
                    Store::add($r, 'revision', $key, ['identity' => $key, 'change_type' => ! $new ? 'removed' : (! $before ? 'added' : 'changed'), 'previous' => $before, 'proposed' => $new, 'notice' => 'Field identity/content comparison; page shifts allowed. Confirmed inputs and findings are not automatically changed.']);
                }
            }
        }
        // Deterministic arithmetic only for explicit same-comparable source identity; no universal valuation threshold.
        $comps = [];
        foreach ($fields as $f) {
            if (str_starts_with($f['data']['key'], 'comparable.') && $f['data']['unit'] !== null) {
                $comps[$f['data']['unit']][$f['data']['key']][] = $f;
            }
        }
        foreach ($comps as $identity => $values) {
            $keys = ['comparable.price', 'comparable.net_adjustment', 'comparable.adjusted_value'];
            $picked = [];
            foreach ($keys as $key) {
                if (count($values[$key] ?? []) !== 1) {
                    continue 2;
                }$f = $values[$key][0];
                if (! preg_match('/^-?\d{1,12}(?:\.\d{1,2})?$/D', $f['data']['normalized'] ?? '')) {
                    continue 2;
                }$picked[] = $f;
            }$p = Money::minor($picked[0]['data']['normalized']);
            $a = Money::minor($picked[1]['data']['normalized']);
            $total = Money::minor($picked[2]['data']['normalized']);
            if ($p + $a !== $total) {
                $this->finding($r, 'comparable-arithmetic:'.$identity, 'appraisal_inconsistency', 'Reported comparable price plus net adjustment differs from reported adjusted indication; extraction error remains an alternative', ['field_ids' => array_column($picked, 'id'), 'what_verify' => 'Check the original same-comparable values and explanations; this is not an independent valuation or alteration determination']);
            }
        }
        if (isset($by['inspection_date:'][0],$by['report_date:'][0])) {
            $a = $by['inspection_date:'][0];
            $b = $by['report_date:'][0];
            $left = $a['data']['normalized'];
            $right = $b['data']['normalized'];
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/D', $left ?? '') && preg_match('/^\d{4}-\d{2}-\d{2}$/D', $right ?? '') && $left > $right) {
                $this->finding($r, 'date-chronology', 'appraisal_inconsistency', 'Reported report date precedes inspection date; verify dates and extraction, no authenticity conclusion', ['field_ids' => [$a['id'], $b['id']]]);
            }
        }
        foreach (['sketch', 'map'] as $expected) {
            $present = collect(Store::rows($r, 'document'))->contains(fn ($d) => $d['data']['extracted_classification'] === $expected) || isset($by[$expected.':']);
            if (! $present) {
                $this->finding($r, 'exhibit:'.$expected, 'missing_evidence', 'Verify applicable '.$expected.' exhibit and completeness in the combined original; classification may be incomplete, no approved-rule violation inferred');
            }
        }
        $this->finding($r, 'external-coverage', 'integration_unavailable', 'Bank Ocrolus forensic coverage is not enabled or claimed for appraisals; only declared internal/vision checks performed');
    }
}
