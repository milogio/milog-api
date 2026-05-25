<?php

return [
    'api_keys' => [
        'header' => 'X-API-Key',
        'prefix_length' => 12,
    ],

    'timeline' => [
        'per_page' => env('MiLog_TIMELINE_PER_PAGE', 50),
    ],

    'formatters' => [
        App\Services\MiLog\Formatters\GenericTimelineEventFormatter::class,
    ],
];
