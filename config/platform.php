<?php

$env = env('APP_ENV', 'production');

return [
    // Platform admins must have confirmed TOTP two-factor sign-in before using /admin.
    // Defaults ON in production; set OWNER_CONSOLE_REQUIRE_2FA=true elsewhere to rehearse it.
    'console_require_2fa' => (bool) env('OWNER_CONSOLE_REQUIRE_2FA', $env === 'production'),

    // When ON, newly registered organizations wait in the owner approval queue before members can use them.
    'signup_approval' => (bool) env('PLATFORM_SIGNUP_APPROVAL', false),

    'impersonation' => [
        // Global kill switch. Defaults ON only for local/testing; production must opt in deliberately.
        'enabled' => (bool) env('IMPERSONATION_ENABLED', in_array($env, ['local', 'testing'], true)),
        'minutes' => max(5, min(240, (int) env('IMPERSONATION_MINUTES', 30))),
        'allow_document_download' => (bool) env('IMPERSONATION_ALLOW_DOCUMENT_DOWNLOAD', false),
    ],

    'version' => env('APP_VERSION'),
];
