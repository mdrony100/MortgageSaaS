<?php

namespace App\Billing;

use App\Contracts\SubscriptionBilling;

class FakeProvider implements SubscriptionBilling
{
    public function status(): string
    {
        return 'test_only';
    }

    public function checkout(array $request): array
    {
        $reference = $request['reference'];
        $action = $request['action'];
        $plan = $request['plan_id'] ?? null;
        abort_unless(app()->environment('local', 'testing') && config('billing.fake_enabled'), 503, 'Fake billing is isolated to explicitly enabled local/testing');

        return ['status' => 'pending_test_event', 'reference' => $reference, 'action' => $action, 'plan_id' => $plan, 'notice' => 'TEST provider only. No charge or entitlement change; signed test webhook required.'];
    }
}
