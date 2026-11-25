<?php

namespace App\Http\Controllers;

use App\Billing\PageCredits;
use App\Models\Organization;
use App\Models\PageCreditEntry;
use App\Models\PaymentRequest;
use App\Models\Plan;
use App\Services\Access;
use Illuminate\Http\Request;

class PageCreditController extends Controller
{
    public function show(Request $r, Organization $organization)
    {
        abort_unless(Access::membership($r->user(), $organization->id), 403);
        $credits = app(PageCredits::class);
        $summary = $credits->summary($organization);
        $admin = Access::admin($r->user(), $organization->id);
        $summary['can_request'] = $admin;
        $summary['plans'] = $admin
            ? Plan::query()->where('is_active', true)->orderBy('id')->get()->map(fn (Plan $plan) => $credits->planPayload($plan))->values()
            : [];
        $summary['history'] = $admin
            ? PageCreditEntry::where('organization_id', $organization->id)->latest('id')->limit(40)->get(['id', 'type', 'pages', 'balance_after', 'reason', 'created_at'])
            : [];
        $summary['requests'] = $admin
            ? PaymentRequest::where('organization_id', $organization->id)->with('plan:id,name')->latest('id')->limit(20)->get()
            : [];

        return $summary;
    }
}
