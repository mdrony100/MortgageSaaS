<?php

namespace App\Appraisal;

use App\Models\Review\Run;

class LeaseMatcher
{
    private function address(?string $s): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower($s ?? ''));
    }

    public function candidates(Run $r): void
    {
        $docs = [];
        $addresses = [];
        foreach (Store::facts($r) as $f) {
            $d = $f['data'];
            $docs[$d['document_version_id']][] = $f;
            if ($d['key'] === 'address' && $d['normalized'] !== null) {
                $addresses[] = $this->address($d['normalized']);
            }
        }
        $addresses = array_values(array_unique($addresses));
        foreach (Store::rows($r, 'document') as $doc) {
            $d = $doc['data'];
            if (! in_array($d['extracted_classification'] ?? $d['requested_classification'], ['lease', 'lease_amendment', 'rent_roll'], true)) {
                continue;
            }
            $facts = $docs[$d['document_version_id']] ?? [];
            $leaseAddresses = [];
            $units = [];
            foreach ($facts as $f) {
                if ($f['data']['key'] === 'lease.address' && $f['data']['normalized'] !== null) {
                    $leaseAddresses[] = $this->address($f['data']['normalized']);
                }if ($f['data']['key'] === 'lease.unit' && $f['data']['normalized'] !== null) {
                    $units[] = $f['data']['normalized'];
                }
            }
            $leaseAddresses = array_values(array_unique($leaseAddresses));
            $units = array_values(array_unique($units));
            $same = count($addresses) === 1 && count($leaseAddresses) === 1 && $addresses[0] === $leaseAddresses[0];
            $unit = count($units) === 1 ? $units[0] : null;
            Store::add($r, 'lease_candidate', (string) $d['document_version_id'], ['document_version_id' => $d['document_version_id'], 'proposed_unit' => $same ? $unit : null, 'status' => $same && $unit !== null ? 'proposed_requires_confirmation' : 'uncertain_manual_match', 'evidence' => $facts, 'notice' => 'Unique address and explicit unique unit evidence only; conflicts require human identification, no arbitrary assignment or renewal inference; amendments remain separate']);
        }
    }
}
