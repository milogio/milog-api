<?php

namespace App\Services\MiLog;

use App\Tenant;
use App\TimelineEvent;
use Illuminate\Support\Carbon;

class CreateTimelineEvent
{
    /**
     * Persist a new tenant-scoped timeline event.
     *
     * @param  \App\Tenant  $tenant
     * @param  array  $payload
     * @return \App\TimelineEvent
     */
    public function handle(Tenant $tenant, array $payload)
    {
        $createdAt = now();
        $occurredAt = empty($payload['occurred_at'])
            ? $createdAt
            : Carbon::parse($payload['occurred_at']);

        return TimelineEvent::create([
            'tenant_id' => $tenant->id,
            'actor_type' => $payload['actor_type'],
            'actor_id' => $payload['actor_id'],
            'action' => $payload['action'],
            'target_type' => $payload['target_type'],
            'target_id' => $payload['target_id'],
            'log_level' => $payload['log_level'] ?? 'info',
            'metadata' => $payload['metadata'] ?? [],
            'occurred_at' => $occurredAt,
            'created_at' => $createdAt,
        ]);
    }
}
