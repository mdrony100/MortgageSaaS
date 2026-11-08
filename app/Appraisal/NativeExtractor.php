<?php

namespace App\Appraisal;

use App\Models\Review\Run;

class NativeExtractor implements Extractor
{
    public function extract(array $m, Run $r, int $v, string $dir): array
    {
        $out = ['classification' => null, 'form' => null, 'form_version' => null, 'complete' => false, 'pages' => array_column($m['pages'], 'number'), 'fields' => [], 'photos' => [], 'issues' => []];
        foreach ($m['pages'] as $p) {
            if (trim($p['text']) === '') {
                $out['issues'][] = ['category' => 'missing_evidence', 'observation' => 'No native text on page; scanned/image evidence requires approved vision or manual review', 'page' => $p['number'], 'what_verify' => 'Inspect actual rendered source; do not infer missing values'];
            }
            foreach (explode("\n", $p['text']) as $line) {
                if (preg_match('/^([a-z][a-z0-9_.-]*)\s*\|\s*([^|]*)\|\s*(.*)$/D', trim($line), $a)) {
                    $out['fields'][] = ['key' => $a[1], 'unit' => trim($a[2]) === '' ? null : trim($a[2]), 'raw' => trim($a[3]), 'normalized' => trim($a[3]) === '' ? null : trim($a[3]), 'units' => null, 'page' => $p['number'], 'excerpt' => $line, 'status' => 'native_supported_layout'];
                    if (in_array($a[1], ['classification', 'form', 'form_version'])) {
                        $out[$a[1]] = trim($a[3]);
                    }
                }
            }
        }$out['issues'][] = ['category' => 'missing_evidence', 'observation' => 'Native labeled-field extraction only; photo interpretation and unsupported layouts require approved vision or manual review.', 'page' => null, 'what_verify' => 'Review all pages; configure approved vision before claiming photo analysis'];

        return $out;
    }
}
