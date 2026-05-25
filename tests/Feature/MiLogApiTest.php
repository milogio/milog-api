<?php

namespace Tests\Feature;

use App\ApiKey;
use App\Tenant;
use App\TimelineEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
