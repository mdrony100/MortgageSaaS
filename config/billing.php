<?php

return ['provider' => env('BILLING_PROVIDER', 'disabled'), 'fake_enabled' => (bool) env('BILLING_FAKE_ENABLED', false), 'webhook_secret' => env('BILLING_WEBHOOK_SECRET'), 'catalog' => [], 'processing_limit' => null, 'count_statuses' => ['reserved', 'completed', 'failed', 'cancelled'], 'cancellation_policy' => 'unapproved_preserve_current_access', 'refund_policy' => 'unapproved_manual_review'];
