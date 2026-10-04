<?php

namespace Tests\Feature;

use App\ApiKey;
use App\Tenant;
use App\TimelineEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MiLogApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test requests without an API key are rejected.
     *
     * @return void
     */
    public function testRequestsWithoutApiKeyAreRejected()
    {
        $response = $this->getJson('/api/v1/timeline');

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Invalid API key.',
            ]);
    }

    /**
     * Test requests with an invalid API key are rejected.
     *
     * @return void
     */
    public function testRequestsWithInvalidApiKeyAreRejected()
    {
        $response = $this->withHeader('X-API-Key', 'invalid-key')
            ->getJson('/api/v1/timeline');

        $response->assertStatus(401);
    }

    /**
     * Test events can be stored for the tenant tied to the API key.
     *
     * @return void
     */
    public function testEventCanBeStoredForResolvedTenant()
    {
        [$tenant, $rawKey] = $this->makeTenantWithApiKey();

        $response = $this->withHeader('X-API-Key', $rawKey)
            ->postJson('/api/v1/events', [
                'tenant_id' => 'ignored',
                'actor_type' => 'user',
                'actor_id' => '42',
                'action' => 'created',
                'target_type' => 'invoice',
                'target_id' => 'inv_1',
                'log_level' => 'error',
            ]);

        $response->assertCreated()
            ->assertJsonPath('tenant_id', $tenant->id)
            ->assertJsonPath('actor_type', 'user')
            ->assertJsonPath('log_level', 'error')
            ->assertJsonPath('message', 'user 42 created invoice inv_1');

        $this->assertDatabaseHas('events', [
            'tenant_id' => $tenant->id,
            'actor_type' => 'user',
            'actor_id' => '42',
            'action' => 'created',
            'target_type' => 'invoice',
            'target_id' => 'inv_1',
            'log_level' => 'error',
        ]);
    }

    /**
     * Test event creation defaults the log level to info.
     *
     * @return void
     */
    public function testEventCreationDefaultsLogLevelToInfo()
    {
        [$tenant, $rawKey] = $this->makeTenantWithApiKey();

        $response = $this->withHeader('X-API-Key', $rawKey)
            ->postJson('/api/v1/events', [
                'actor_type' => 'user',
                'actor_id' => '42',
                'action' => 'created',
                'target_type' => 'invoice',
                'target_id' => 'inv_2',
            ]);

        $response->assertCreated()
            ->assertJsonPath('tenant_id', $tenant->id)
            ->assertJsonPath('log_level', 'info');

        $this->assertDatabaseHas('events', [
            'tenant_id' => $tenant->id,
            'target_id' => 'inv_2',
            'log_level' => 'info',
        ]);
    }

    /**
     * Test identical idempotent requests return the originally created event.
     *
     * @return void
     */
    public function testEventCreationIsIdempotentWithinTenant()
    {
        [$tenant, $rawKey] = $this->makeTenantWithApiKey();
        $payload = [
            'actor_type' => 'user',
            'actor_id' => '42',
            'action' => 'created',
            'target_type' => 'invoice',
            'target_id' => 'inv-idempotent',
            'metadata' => ['source' => 'billing', 'nested' => ['b' => 2, 'a' => 1]],
        ];

        $first = $this->withHeaders([
            'X-API-Key' => $rawKey,
            'X-Idempotency-Key' => 'event-123',
        ])->postJson('/api/v1/events', $payload);

        $replayPayload = $payload;
        $replayPayload['metadata'] = ['nested' => ['a' => 1, 'b' => 2], 'source' => 'billing'];

        $replay = $this->withHeaders([
            'X-API-Key' => $rawKey,
            'X-Idempotency-Key' => 'event-123',
        ])->postJson('/api/v1/events', $replayPayload);

        $first->assertCreated();
        $replay->assertOk()
            ->assertHeader('Idempotency-Replayed', 'true')
            ->assertJsonPath('id', $first->json('id'));

        $this->assertDatabaseCount('events', 1);
        $this->assertDatabaseHas('events', [
            'tenant_id' => $tenant->id,
            'idempotency_key' => 'event-123',
        ]);
    }

    /**
     * Test an idempotency key cannot be reused with a different payload.
     *
     * @return void
     */
    public function testEventCreationRejectsConflictingIdempotencyKeyReuse()
    {
        [, $rawKey] = $this->makeTenantWithApiKey();
        $payload = [
            'actor_type' => 'user',
            'actor_id' => '42',
            'action' => 'created',
            'target_type' => 'invoice',
            'target_id' => 'inv-original',
        ];

        $this->withHeaders([
            'X-API-Key' => $rawKey,
            'X-Idempotency-Key' => 'event-456',
        ])->postJson('/api/v1/events', $payload)->assertCreated();

        $payload['target_id'] = 'inv-conflict';

        $this->withHeaders([
            'X-API-Key' => $rawKey,
            'X-Idempotency-Key' => 'event-456',
        ])->postJson('/api/v1/events', $payload)
            ->assertStatus(409)
            ->assertJsonPath(
                'message',
                'The idempotency key was already used with a different request.'
            );

        $this->assertDatabaseCount('events', 1);
    }

    /**
     * Test event payload validation errors are returned.
     *
     * @return void
     */
    public function testEventPayloadValidationErrorsAreReturned()
    {
        [, $rawKey] = $this->makeTenantWithApiKey();

        $response = $this->withHeader('X-API-Key', $rawKey)
            ->postJson('/api/v1/events', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'actor_type',
                'actor_id',
                'action',
                'target_type',
                'target_id',
            ]);
    }

    /**
     * Test invalid log levels are rejected.
     *
     * @return void
     */
    public function testInvalidLogLevelIsRejected()
    {
        [, $rawKey] = $this->makeTenantWithApiKey();

        $response = $this->withHeader('X-API-Key', $rawKey)
            ->postJson('/api/v1/events', [
                'actor_type' => 'user',
                'actor_id' => '42',
                'action' => 'created',
                'target_type' => 'invoice',
                'target_id' => 'inv_1',
                'log_level' => 'warning',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'log_level',
            ]);
    }

    /**
     * Test the timeline response is tenant-scoped, filtered, ordered, and paginated.
     *
     * @return void
     */
    public function testTimelineReturnsTenantScopedPaginatedEventsWithFilters()
    {
        [$tenant, $rawKey] = $this->makeTenantWithApiKey();
        [$otherTenant] = $this->makeTenantWithApiKey('Other Tenant');

        $matchingByActor = TimelineEvent::create([
            'tenant_id' => $tenant->id,
            'actor_type' => 'user',
            'actor_id' => 'actor-1',
            'action' => 'updated',
            'target_type' => 'invoice',
            'target_id' => 'target-1',
            'log_level' => 'warn',
            'metadata' => ['type' => 'billing'],
            'occurred_at' => now()->subMinute(),
            'created_at' => now()->subMinute(),
        ]);

        $matchingByTarget = TimelineEvent::create([
            'tenant_id' => $tenant->id,
            'actor_type' => 'system',
            'actor_id' => 'actor-2',
            'action' => 'created',
            'target_type' => 'user',
            'target_id' => 'target-1',
            'log_level' => 'info',
            'metadata' => [],
            'occurred_at' => now(),
            'created_at' => now(),
        ]);

        TimelineEvent::create([
            'tenant_id' => $tenant->id,
            'actor_type' => 'account',
            'actor_id' => 'actor-9',
            'action' => 'created',
            'target_type' => 'workspace',
            'target_id' => 'target-9',
            'log_level' => 'debug',
            'metadata' => [],
            'occurred_at' => now()->subHours(2),
            'created_at' => now()->subHours(2),
        ]);

        TimelineEvent::create([
            'tenant_id' => $otherTenant->id,
            'actor_type' => 'user',
            'actor_id' => 'actor-1',
            'action' => 'created',
            'target_type' => 'user',
            'target_id' => 'target-1',
            'log_level' => 'error',
            'metadata' => [],
            'occurred_at' => now()->addMinute(),
            'created_at' => now()->addMinute(),
        ]);

        $response = $this->withHeader('X-API-Key', $rawKey)
            ->getJson('/api/v1/timeline?target_id=target-1&type=user');

        $response->assertOk()
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $matchingByTarget->id)
            ->assertJsonPath('data.0.log_level', 'info')
            ->assertJsonPath('data.1.id', $matchingByActor->id)
            ->assertJsonPath('data.1.log_level', 'warn');
    }

    public function testTimelineFiltersViewerLevelsAndLegacyStoredValues()
    {
        [$tenant, $rawKey] = $this->makeTenantWithApiKey();

        DB::statement('alter table events drop constraint if exists events_log_level_check');

        foreach (['trace', 'debug', 'info', null, 'success', 'warn', 'warning', 'error', 'fatal', 'notice'] as $index => $level) {
            DB::table('events')->insert([
                'id' => sprintf('00000000-0000-7000-8000-%012d', $index + 1),
                'tenant_id' => $tenant->id,
                'actor_type' => 'user',
                'actor_id' => 'actor-1',
                'action' => 'updated',
                'target_type' => 'invoice',
                'target_id' => 'invoice-1',
                'log_level' => $level,
                'metadata' => json_encode([]),
                'occurred_at' => now()->subSeconds($index),
                'created_at' => now()->subSeconds($index),
            ]);
        }

        $expectations = [
            'debug' => ['trace', 'debug'],
            'info' => ['info', null, 'notice'],
            'success' => ['success'],
            'warning' => ['warn', 'warning'],
            'error' => ['error', 'fatal'],
            'debug,error' => ['trace', 'debug', 'error', 'fatal'],
        ];

        foreach ($expectations as $filter => $expected) {
            $response = $this->withHeader('X-API-Key', $rawKey)
                ->getJson('/api/v1/timeline?log_level='.urlencode($filter));

            $response->assertOk();
            $this->assertEqualsCanonicalizing($expected, collect($response->json('data'))->pluck('log_level')->all());
        }
    }

    public function testTimelineLogLevelFilterIsValidatedTrimmedAndDeduplicated()
    {
        [$tenant, $rawKey] = $this->makeTenantWithApiKey();

        TimelineEvent::create([
            'tenant_id' => $tenant->id,
            'actor_type' => 'user',
            'actor_id' => 'actor-1',
            'action' => 'updated',
            'target_type' => 'invoice',
            'target_id' => 'invoice-1',
            'log_level' => 'warn',
            'metadata' => [],
            'occurred_at' => now(),
        ]);

        $this->withHeader('X-API-Key', $rawKey)
            ->getJson('/api/v1/timeline?log_level='.urlencode(' warning,warning '))
            ->assertOk()
            ->assertJsonCount(1, 'data');

        foreach (['', 'unknown', 'warning,,error', 'debug,info,success,warning,error,unknown'] as $invalid) {
            $this->withHeader('X-API-Key', $rawKey)
                ->getJson('/api/v1/timeline?log_level='.urlencode($invalid))
                ->assertStatus(422)
                ->assertJsonValidationErrors('log_level');
        }
    }

    public function testTimelineLogLevelCombinesWithOtherFiltersAndApiKeyTenantScope()
    {
        [$tenant, $rawKey] = $this->makeTenantWithApiKey();
        [$otherTenant] = $this->makeTenantWithApiKey('Other Tenant');

        foreach ([$tenant, $otherTenant] as $eventTenant) {
            TimelineEvent::create([
                'tenant_id' => $eventTenant->id,
                'actor_type' => 'user',
                'actor_id' => 'actor-1',
                'action' => 'updated',
                'target_type' => 'invoice',
                'target_id' => 'invoice-1',
                'log_level' => 'fatal',
                'metadata' => [],
                'occurred_at' => now(),
            ]);
        }

        TimelineEvent::create([
            'tenant_id' => $tenant->id,
            'actor_type' => 'user',
            'actor_id' => 'actor-2',
            'action' => 'updated',
            'target_type' => 'invoice',
            'target_id' => 'invoice-1',
            'log_level' => 'fatal',
            'metadata' => [],
            'occurred_at' => now(),
        ]);

        $this->withHeader('X-API-Key', $rawKey)
            ->getJson('/api/v1/timeline?log_level=error&actor_id=actor-1&target_id=invoice-1&type=invoice')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.tenant_id', $tenant->id);
    }

    /**
     * Test timeline ordering is deterministic when timestamps match.
     *
     * @return void
     */
    public function testTimelineUsesIdAsStableOrderingTieBreaker()
    {
        [$tenant, $rawKey] = $this->makeTenantWithApiKey();
        $timestamp = now()->startOfSecond();

        foreach ([
            '00000000-0000-7000-8000-000000000001',
            '00000000-0000-7000-8000-000000000002',
        ] as $id) {
            DB::table('events')->insert([
                'id' => $id,
                'tenant_id' => $tenant->id,
                'actor_type' => 'user',
                'actor_id' => 'actor-1',
                'action' => 'updated',
                'target_type' => 'invoice',
                'target_id' => 'invoice-1',
                'log_level' => 'info',
                'metadata' => json_encode([]),
                'occurred_at' => $timestamp,
                'created_at' => $timestamp,
            ]);
        }

        $response = $this->withHeader('X-API-Key', $rawKey)
            ->getJson('/api/v1/timeline');

        $response->assertOk()
            ->assertJsonPath('data.0.id', '00000000-0000-7000-8000-000000000002')
            ->assertJsonPath('data.1.id', '00000000-0000-7000-8000-000000000001');
    }

    /**
     * Test PostgreSQL has indexes matching timeline filtering and ordering.
     *
     * @return void
     */
    public function testTimelineIndexesMatchQueryShape()
    {
        $indexes = collect(DB::select(
            "select indexname, indexdef from pg_indexes where schemaname = current_schema() and tablename = 'events'"
        ))->keyBy('indexname');

        $expectedIndexes = [
            'events_timeline_index' => '(tenant_id, occurred_at DESC, created_at DESC, id DESC)',
            'events_target_timeline_index' => '(tenant_id, target_id, occurred_at DESC, created_at DESC, id DESC)',
            'events_actor_timeline_index' => '(tenant_id, actor_id, occurred_at DESC, created_at DESC, id DESC)',
            'events_actor_type_timeline_index' => '(tenant_id, actor_type, occurred_at DESC, created_at DESC, id DESC)',
            'events_target_type_timeline_index' => '(tenant_id, target_type, occurred_at DESC, created_at DESC, id DESC)',
        ];

        foreach ($expectedIndexes as $name => $columns) {
            $this->assertTrue($indexes->has($name), "Missing timeline index {$name}.");
            $this->assertStringContainsString($columns, $indexes->get($name)->indexdef);
        }
    }

    /**
     * Test cursor pagination produces stable, non-overlapping timeline pages.
     *
     * @return void
     */
    public function testTimelineSupportsCursorPaginationWithoutChangingOffsetDefault()
    {
        config(['milog.timeline.per_page' => 2]);
        [$tenant, $rawKey] = $this->makeTenantWithApiKey();
        $timestamp = now()->startOfSecond();

        foreach ([
            '00000000-0000-7000-8000-000000000001',
            '00000000-0000-7000-8000-000000000002',
            '00000000-0000-7000-8000-000000000003',
        ] as $id) {
            DB::table('events')->insert([
                'id' => $id,
                'tenant_id' => $tenant->id,
                'actor_type' => 'user',
                'actor_id' => 'actor-1',
                'action' => 'updated',
                'target_type' => 'invoice',
                'target_id' => 'invoice-1',
                'log_level' => 'info',
                'metadata' => json_encode([]),
                'occurred_at' => $timestamp,
                'created_at' => $timestamp,
            ]);
        }

        $offsetResponse = $this->withHeader('X-API-Key', $rawKey)
            ->getJson('/api/v1/timeline');

        $offsetResponse->assertOk()
            ->assertJsonPath('meta.current_page', 1);

        $firstPage = $this->withHeader('X-API-Key', $rawKey)
            ->getJson('/api/v1/timeline?pagination=cursor');

        $firstPage->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', '00000000-0000-7000-8000-000000000003')
            ->assertJsonPath('data.1.id', '00000000-0000-7000-8000-000000000002')
            ->assertJsonMissingPath('meta.current_page');

        $nextUrl = $firstPage->json('links.next');
        $this->assertNotNull($nextUrl);

        $secondPage = $this->withHeader('X-API-Key', $rawKey)
            ->getJson($nextUrl);

        $secondPage->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', '00000000-0000-7000-8000-000000000001');

        $this->assertNotNull($secondPage->json('links.prev'));
    }

    public function testFilteredCursorPaginationIsStableAcrossThreePagesAndRetainsFilter()
    {
        config(['milog.timeline.per_page' => 2]);
        [$tenant, $rawKey] = $this->makeTenantWithApiKey();
        $timestamp = now()->startOfSecond();

        foreach (range(1, 7) as $number) {
            DB::table('events')->insert([
                'id' => sprintf('00000000-0000-7000-8000-%012d', $number),
                'tenant_id' => $tenant->id,
                'actor_type' => 'user',
                'actor_id' => 'actor-1',
                'action' => 'updated',
                'target_type' => 'invoice',
                'target_id' => 'invoice-1',
                'log_level' => $number === 4 ? 'info' : ($number % 2 ? 'error' : 'fatal'),
                'metadata' => json_encode([]),
                'occurred_at' => $timestamp,
                'created_at' => $timestamp,
            ]);
        }

        $url = '/api/v1/timeline?pagination=cursor&log_level=error';
        $ids = [];

        for ($page = 0; $page < 3; $page++) {
            $response = $this->withHeader('X-API-Key', $rawKey)->getJson($url)->assertOk();
            $ids = array_merge($ids, collect($response->json('data'))->pluck('id')->all());
            $url = $response->json('links.next');

            if ($page < 2) {
                $this->assertNotNull($url);
                $this->assertStringContainsString('log_level=error', $url);
            }
        }

        $this->assertSame([
            '00000000-0000-7000-8000-000000000007',
            '00000000-0000-7000-8000-000000000006',
            '00000000-0000-7000-8000-000000000005',
            '00000000-0000-7000-8000-000000000003',
            '00000000-0000-7000-8000-000000000002',
            '00000000-0000-7000-8000-000000000001',
        ], $ids);
        $this->assertCount(count(array_unique($ids)), $ids);
    }

    /**
     * Create a tenant and API key for feature tests.
     *
     * @param  string  $name
     * @return array
     */
    protected function makeTenantWithApiKey($name = 'Acme')
    {
        $tenant = Tenant::create([
            'name' => $name,
        ]);

        $rawKey = 'milog_test_key_'.$tenant->id;

        ApiKey::create([
            'tenant_id' => $tenant->id,
            'name' => 'Primary',
            'key_prefix' => ApiKey::keyPrefix($rawKey),
            'key_hash' => ApiKey::hashKey($rawKey),
        ]);

        return [$tenant, $rawKey];
    }
}
