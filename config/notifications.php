<?php

return [
    // Absolute URL so the logo loads in Gmail and Outlook, independent of APP_URL.
    'logo_url' => 'https://nonqmdoc.ai/images/brand/non-qm-doc-logo.png',

    'support_email' => 'info@nonqmdoc.ai',

    'company' => 'Non-QM Doc AI',

    // Remaining pages below either threshold counts as low. Each state emails once per credit period.
    'low_pages' => 50,

    'low_ratio' => 0.10,

    'trial_notice_days' => 3,

    /*
     * Owner-console toggles overlay these defaults (on). Password reset and
     * email verification are not in a group; they always send.
     */
    'groups' => [
        'admin_signups' => true,
        'admin_website' => true,
        'admin_billing' => true,
        'admin_support' => true,
        'admin_credits' => true,
        'customer_account' => true,
        'customer_team' => true,
        'customer_billing' => true,
        'customer_support' => true,
        'customer_processing' => true,
    ],
];
