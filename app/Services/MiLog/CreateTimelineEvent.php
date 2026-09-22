<?php

namespace App\Services\MiLog;

use App\Exceptions\IdempotencyConflictException;
use App\Tenant;
use App\TimelineEvent;
use Illuminate\Database\QueryException;
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
        $idempotencyKey = $payload['idempotency_key'] ?? null;
        $requestHash = $idempotencyKey ? $this->requestHash($payload) : null;

        if ($idempotencyKey) {
            $existing = $this->findIdempotentEvent($tenant, $idempotencyKey);

            if ($existing) {
                return $this->resolveReplay($existing, $requestHash);
            }
        }

        $createdAt = now();
        $occurredAt = empty($payload['occurred_at'])
            ? $createdAt
            : Carbon::parse($payload['occurred_at']);

        try {
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
                'idempotency_key' => $idempotencyKey,
                'request_hash' => $requestHash,
            ]);
        } catch (QueryException $exception) {
            if (! $idempotencyKey || $exception->getCode() !== '23505') {
                throw $exception;
            }

            $existing = $this->findIdempotentEvent($tenant, $idempotencyKey);

            if (! $existing) {
                throw $exception;
            }

            return $this->resolveReplay($existing, $requestHash);
        }
    }

    /**
     * Find an event previously created with an idempotency key.
     *
     * @param  \App\Tenant  $tenant
     * @param  string  $idempotencyKey
     * @return \App\TimelineEvent|null
     */
    protected function findIdempotentEvent(Tenant $tenant, $idempotencyKey)
    {
        return TimelineEvent::query()
            ->where('tenant_id', $tenant->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();
    }

    /**
     * Return a matching replay or reject conflicting key reuse.
     *
     * @param  \App\TimelineEvent  $event
     * @param  string  $requestHash
     * @return \App\TimelineEvent
     */
    protected function resolveReplay(TimelineEvent $event, $requestHash)
    {
        if (! hash_equals((string) $event->request_hash, $requestHash)) {
            throw new IdempotencyConflictException();
        }

        return $event;
    }

    /**
     * Build a stable hash of the event request, excluding the key itself.
     *
     * @param  array  $payload
     * @return string
     */
    protected function requestHash(array $payload)
    {
        unset($payload['idempotency_key']);

        $payload = $this->sortRecursively($payload);

        return hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES));
    }

    /**
     * Sort associative payload values without changing list order.
     *
     * @param  mixed  $value
     * @return mixed
     */
    protected function sortRecursively($value)
    {
        if (! is_array($value)) {
            return $value;
        }

        foreach ($value as $key => $item) {
            $value[$key] = $this->sortRecursively($item);
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }
}
