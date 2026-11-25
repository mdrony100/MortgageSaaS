<?php

namespace App\Http\Controllers\Admin;

use App\Billing\PageCredits;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlanAdminController extends Controller
{
    public function store(Request $r)
    {
        $plan = Plan::create($this->validated($r) + ['file_limit' => 25, 'member_limit' => 5, 'storage_limit_bytes' => 1073741824]);

        return response()->json(['plan' => app(PageCredits::class)->planPayload($plan)], 201);
    }

    public function update(Request $r, Plan $plan)
    {
        $plan->update($this->validated($r, $plan));

        return ['plan' => app(PageCredits::class)->planPayload($plan->fresh())];
    }

    private function validated(Request $r, ?Plan $plan = null): array
    {
        $d = $r->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('plans', 'name')->ignore($plan?->id)],
            'display_monthly_price' => 'required|numeric|min:0|max:1000000',
            'monthly_page_quota' => 'required|integer|min:0|max:10000000',
            'trial_page_allowance' => 'required|integer|min:0|max:10000000',
            'per_file_page_cap' => 'nullable|integer|min:1|max:10000000',
            'is_active' => 'required|boolean',
            'listed_publicly' => 'required|boolean',
            'summary' => 'nullable|string|max:280',
            'file_limit' => 'sometimes|integer|min:0|max:1000000',
            'member_limit' => 'sometimes|integer|min:0|max:100000',
            'storage_limit_bytes' => 'sometimes|integer|min:0',
            'topup_packs' => 'present|array|max:10',
            'topup_packs.*.pages' => 'required|integer|min:1|max:1000000',
            'topup_packs.*.price' => 'required|numeric|min:0|max:1000000',
            'topup_packs.*.label' => 'required|string|max:80',
        ]);
        $packs = array_map(fn ($pack) => [
            'pages' => (int) $pack['pages'],
            'price' => number_format((float) $pack['price'], 2, '.', ''),
            'label' => trim($pack['label']),
        ], $d['topup_packs']);

        return [
            'name' => trim($d['name']),
            'provisional_monthly_price' => number_format((float) $d['display_monthly_price'], 2, '.', ''),
            'monthly_page_quota' => (int) $d['monthly_page_quota'],
            'trial_page_allowance' => (int) $d['trial_page_allowance'],
            'per_file_page_cap' => $d['per_file_page_cap'] ?? null,
            'is_active' => (bool) $d['is_active'],
            'listed_publicly' => (bool) $d['listed_publicly'],
            'summary' => isset($d['summary']) ? trim((string) $d['summary']) ?: null : null,
            'topup_packs' => $packs,
        ] + array_intersect_key($d, array_flip(['file_limit', 'member_limit', 'storage_limit_bytes']));
    }
}
