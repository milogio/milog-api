<?php

return [
    'api_keys' => [
        'header' => 'X-API-Key',
        'prefix_length' => 12,
        'temporary_lifetime_days' => (int) env('MILOG_TEMPORARY_KEY_DAYS', 7),
        'temporary_issuance_limit' => (int) env('MILOG_TEMPORARY_KEY_LIMIT', 2),
        'paid_active_limit' => (int) env('MILOG_PAID_KEY_LIMIT', 5),
    ],

    'signup' => [
        'ui_url' => env('MILOG_UI_URL', 'http://localhost:3000'),
        'verification_hours' => (int) env('MILOG_SIGNUP_VERIFICATION_HOURS', 24),
        'trial_days' => (int) env('MILOG_TRIAL_DAYS', 14),
    ],

    'timeline' => [
        'per_page' => env('MiLog_TIMELINE_PER_PAGE', 50),
    ],

    'ui_auth' => [
        'access_token_minutes' => (int) env('MILOG_UI_ACCESS_TOKEN_MINUTES', 15),
        'refresh_token_days' => (int) env('MILOG_UI_REFRESH_TOKEN_DAYS', 30),
        'roles' => ['owner', 'admin', 'member'],
    ],

    'frontend' => [
        'enabled' => env('MILOG_FRONTEND_ENABLED', false),
        'disabled_status' => env('MILOG_FRONTEND_DISABLED_STATUS', 404),
        'log_channel' => env('MILOG_FRONTEND_LOG_CHANNEL', env('LOG_CHANNEL', 'stack')),
        'purposes' => [
            '/' => 'legacy frontend landing page',
            'home' => 'legacy frontend dashboard',
            'login' => 'legacy frontend login',
            'register' => 'legacy frontend registration',
            'password.request' => 'legacy frontend password reset request',
            'password.email' => 'legacy frontend password reset email',
            'password.reset' => 'legacy frontend password reset form',
            'password.update' => 'legacy frontend password reset update',
            'password.confirm' => 'legacy frontend password confirmation',
            'verification.notice' => 'legacy frontend email verification notice',
            'verification.verify' => 'legacy frontend email verification',
            'verification.resend' => 'legacy frontend email verification resend',
            'logout' => 'legacy frontend logout',
        ],
    ],

    'formatters' => [
        App\Services\MiLog\Formatters\GenericTimelineEventFormatter::class,
    ],
];
