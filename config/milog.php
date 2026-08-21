<?php

return [
    'api_keys' => [
        'header' => 'X-API-Key',
        'prefix_length' => 12,
    ],

    'timeline' => [
        'per_page' => env('MiLog_TIMELINE_PER_PAGE', 50),
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
