<?php

/*
 * Public marketing website settings.
 *
 * Everything that is business-approved content (brand, contact details, prices,
 * legal approval) lives here so it can be changed centrally without a CMS.
 * Values that are not approved stay null/false and the website falls back to
 * neutral, contact-only wording.
 */
return [
    // Product name shown on the public site. Defaults to the existing APP_NAME branding.
    'product_name' => env('WEBSITE_PRODUCT_NAME', env('APP_NAME', 'MortgageLens')),

    // Canonical public base URL (no trailing slash). Defaults to APP_URL.
    'url' => rtrim((string) env('WEBSITE_URL', env('APP_URL', 'http://localhost')), '/'),

    // Search-engine indexing is only allowed when this flag is true AND APP_ENV=production.
    // Preview/staging/local environments always send noindex.
    'indexing' => (bool) env('WEBSITE_INDEXING', false),

    // Approved public business contact details. Hidden on the site when null.
    'contact' => [
        'email' => env('WEBSITE_CONTACT_EMAIL'),
        'phone' => env('WEBSITE_CONTACT_PHONE'),
    ],

    // Business inbox that receives website inquiry notifications (queued mail).
    // When null, inquiries are still stored and visible to platform staff.
    'inquiry_notify_email' => env('WEBSITE_INQUIRY_NOTIFY_EMAIL'),

    'inquiry_types' => [
        'demo' => 'Request a demo',
        'pricing' => 'Pricing and plans',
        'general' => 'General question',
        'support' => 'Existing customer support',
    ],

    'inquiry_rate_limit' => [
        'per_minute' => (int) env('WEBSITE_INQUIRY_PER_MINUTE', 3),
        'per_day' => (int) env('WEBSITE_INQUIRY_PER_DAY', 20),
    ],

    /*
     * Pricing publication. Prices are shown ONLY when 'published' is true AND at
     * least one plan below is marked approved. Otherwise the pricing page shows
     * "Contact for pricing". Plans are edited here (code-reviewed) because env
     * values cannot express structured, approved commercial terms.
     *
     * Plan shape: ['name' => 'Team', 'price_label' => '$X per month', 'summary' => '...',
     *              'includes' => ['...'], 'approved' => true]
     */
    'pricing' => [
        'published' => (bool) env('WEBSITE_PRICING_PUBLISHED', false),
        'plans' => [],
    ],

    /*
     * Legal content. While 'approved' is false the Privacy Policy and Terms pages
     * render clearly labelled DRAFT development copy and are noindexed.
     */
    'legal' => [
        'approved' => (bool) env('WEBSITE_LEGAL_APPROVED', false),
        'company_name' => env('WEBSITE_LEGAL_COMPANY_NAME'),
        'last_updated' => env('WEBSITE_LEGAL_LAST_UPDATED'),
    ],
];
