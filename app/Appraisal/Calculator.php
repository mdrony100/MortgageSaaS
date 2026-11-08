<?php

namespace App\Appraisal;

use App\Bank\Ledger;
use App\Bank\Money;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

class Calculator
{
    private static function value(?array $a): ?int
    {
        if (! $a || ! ($a['confirmed'] ?? false) || ! isset($a['value']) || empty($a['source'])) {
            return null;
        }

        return Money::minor($a['value']);
    }

    public static function calculate(array $i, array $checklist = []): array
    {
        $missing = [];
        $market = 0;
        $rent = 0;
        $adu = 0;
        $hasAdu = false;
        $units = $i['units'] ?? [];
        if (! $units) {
            $missing[] = 'Select supported units and confirm rent evidence';
        }$marketKnown = (bool) $units;
        $rentKnown = (bool) $units;
        foreach ($units as $u) {
            $hasAdu = $hasAdu || ($u['is_adu'] ?? false);
            $m = self::value($u['market'] ?? null);
            $q = self::value($u['qualifying'] ?? null);
            if ($m === null) {
                $marketKnown = false;
            } else {
                $market += $m;
            }if ($q === null || empty($u['qualifying']['basis'])) {
                $rentKnown = false;
            } else {
                $rent += $q;
                if ($u['is_adu'] ?? false) {
                    $adu += $q;
                }
            }if (($u['current_occupancy'] ?? 'unknown') === 'unknown') {
                $missing[] = 'Confirm current occupancy for unit '.$u['id'];
            }if (($u['rent_method'] ?? 'long_term') === 'short_term') {
                $missing[] = 'Verify separate STR method and documentation for unit '.$u['id'];
            }
        }
        $p = $i['payment'] ?? [];
        $denom = null;
        if (($p['mode'] ?? null) === 'total') {
            $denom = self::value($p['total'] ?? null);
            $definition = 'User-entered confirmed total only; components ignored';
        } else {
            $definition = 'Confirmed payment components';
            $denom = 0;
            $components = $p['components'] ?? [];
            foreach (['payment', 'taxes', 'insurance', 'association', 'other'] as $k) {
                $n = self::value($components[$k] ?? null);
                if ($n === null) {
                    $denom = null;
                    break;
                }$denom += $n;
            }
        }if ($denom === null || $denom <= 0) {
            $denom = null;
            $missing[] = 'Positive confirmed monthly denominator and source required';
        }if (($p['interest_only'] ?? false) && empty($p['definition'])) {
            $denom = null;
            $missing[] = 'Reviewer-confirmed interest-only payment definition required';
        }
        $ratio = fn ($n, $d) => $n === null || $d === null || $d <= 0 ? null : ['numerator_minor' => $n, 'denominator_minor' => $d, 'rational' => $n.'/'.$d, 'decimal_12' => (string) BigDecimal::of($n)->dividedBy($d, 12, RoundingMode::HalfUp), 'display' => (string) BigDecimal::of($n)->dividedBy($d, 2, RoundingMode::HalfUp), 'formula' => 'confirmed monthly rent / confirmed monthly payment'];
        $loan = self::value($i['loan'] ?? null);
        $basis = self::value($i['value_basis'] ?? null);
        if ($loan === null || $basis === null || $basis <= 0 || empty($i['value_basis']['basis'])) {
            $ltv = null;
            $missing[] = 'Confirm loan amount and positive qualifying value basis; no universal lower-of rule';
        } else {
            $ltv = ['loan_minor' => $loan, 'value_basis_minor' => $basis, 'rational_percent' => ($loan * 100).'/'.$basis, 'decimal_12' => (string) BigDecimal::of($loan)->multipliedBy(100)->dividedBy($basis, 12, RoundingMode::HalfUp), 'display_percent' => (string) BigDecimal::of($loan)->multipliedBy(100)->dividedBy($basis, 2, RoundingMode::HalfUp)];
        }$overlayResult = null;
        $overlay = $checklist['overlay'] ?? null;
        if (($checklist['status'] ?? 'draft') === 'approved' && $overlay && ($overlay['approved'] ?? false) && ! empty($overlay['source']) && ! empty($overlay['version']) && ($overlay['order'] ?? null) === 'confirmed_value_times_max_percent' && $basis > 0 && $loan !== null) {
            $maximum = BigDecimal::of($basis)->multipliedBy($overlay['max_percent'])->dividedBy(100, 0, RoundingMode::Down)->toInt();
            $overlayResult = ['source' => $overlay['source'], 'version' => $overlay['version'], 'order' => $overlay['order'], 'maximum_loan_minor' => $maximum, 'requested_difference_minor' => $loan - $maximum, 'notice' => 'Reviewer-approved overlay comparison only; not eligibility'];
        } else {
            $missing[] = 'Verify approved maximum LTV overlay, calculation order and requested-loan impact';
        }
        if (! $marketKnown) {
            $missing[] = 'Unknown market rent is not zero';
        }if (! $rentKnown) {
            $missing[] = 'Qualifying rent requires explicit confirmed basis/source per unit';
        }$approved = ($checklist['status'] ?? 'draft') === 'approved' && ($checklist['rounding_approved'] ?? false) && ($checklist['rounding_rule'] ?? null) === 'HALF_UP_2DP';
        if (! $approved) {
            $missing[] = 'Guideline/rounding policy confirmation pending';
        }$out = ['input' => $i, 'units' => $units, 'payment_definition' => $definition, 'payment_minor' => $denom, 'market_rent_minor' => $marketKnown ? $market : null, 'qualifying_rent_minor' => $rentKnown ? $rent : null, 'market_dscr' => $ratio($marketKnown ? $market : null, $denom), 'qualifying_dscr' => $ratio($rentKnown ? $rent : null, $denom), 'without_adu_dscr' => $hasAdu && $rentKnown ? $ratio($rent - $adu, $denom) : null, 'with_adu_dscr' => $hasAdu && $rentKnown ? $ratio($rent, $denom) : null, 'appraised_value' => $i['appraised_value'] ?? null, 'selected_value_basis' => $i['value_basis'] ?? null, 'ltv' => $ltv, 'overlay' => $overlayResult, 'missing' => array_values(array_unique($missing)), 'rounding' => $approved ? 'Approved HALF_UP_2DP; exact rational retained' : 'HALF_UP two-place DISPLAY convention only; exact rational retained; investor rounding confirmation pending', 'status' => 'provisional', 'notice' => 'Review calculations only; no approval, decline, eligibility or condition clearance'];
        $out['snapshot_hash'] = Ledger::hash($out);

        return $out;
    }
}
