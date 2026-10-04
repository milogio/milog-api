<?php

namespace Tests\Feature;

use App\Tenant;
use App\TimelineEvent;
use App\UiRefreshToken;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Tests\TestCase;

class UiAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(ClientRepository::class)->createPersonalAccessGrantClient('MiLog UI Tests', 'users');
    }

    public function testUserCanLoginToOnlyActiveTenantAndReadSession()
    {
        [$user, $tenant] = $this->makeMember();

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => strtoupper($user->email),
            'password' => 'correct-password',
        ]);

        $login->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.tenant.id', $tenant->id)
            ->assertJsonPath('user.tenant.role', 'admin');

        $this->assertNotEmpty($login->json('access_token'));
        $this->assertNotEmpty($login->json('refresh_token'));
        $this->assertDatabaseHas('oauth_access_tokens', [
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'is_ui_token' => true,
            'revoked' => false,
        ]);

        $this->withToken($login->json('access_token'))
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('user.tenant.id', $tenant->id);
    }

    public function testMultipleMembershipsRequireTenantSelection()
    {
        [$user, $tenant] = $this->makeMember();
        $otherTenant = Tenant::create(['name' => 'Other tenant']);
        $user->tenants()->attach($otherTenant->id, ['role' => 'member', 'status' => 'active']);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'correct-password',
        ])->assertStatus(409)
            ->assertJsonPath('error.code', 'tenant_selection_required')
            ->assertJsonCount(2, 'error.tenants');

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'correct-password',
            'tenant_id' => $tenant->id,
        ])->assertOk()->assertJsonPath('user.tenant.id', $tenant->id);
    }

    public function testInvalidCredentialsAndInvalidMembershipUseSameError()
    {
        [$user] = $this->makeMember();
        $unknownTenant = Tenant::create(['name' => 'No access']);

        $wrongPassword = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $wrongTenant = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'correct-password',
            'tenant_id' => $unknownTenant->id,
        ]);

        $wrongPassword->assertStatus(401)->assertJsonPath('error.code', 'invalid_credentials');
        $wrongTenant->assertStatus(401)->assertExactJson($wrongPassword->json());
    }

    public function testRefreshRotatesTokensAndReplayRevokesFamily()
    {
        [$user] = $this->makeMember();
        $login = $this->login($user);
        $firstAccessTokenId = UiRefreshToken::first()->access_token_id;

        $refresh = $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => $login->json('refresh_token'),
        ])->assertOk();

        $this->assertNotSame($login->json('access_token'), $refresh->json('access_token'));
        $this->assertNotSame($login->json('refresh_token'), $refresh->json('refresh_token'));
        $this->assertDatabaseHas('oauth_access_tokens', ['id' => $firstAccessTokenId, 'revoked' => true]);

        $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => $login->json('refresh_token'),
        ])->assertStatus(401)->assertJsonPath('error.code', 'refresh_token_reused');

        $this->assertSame(0, Passport::token()->newQuery()->where('is_ui_token', true)->where('revoked', false)->count());
    }

    public function testLogoutRevokesCurrentFamilyButNotApiKeys()
    {
        [$user, $tenant] = $this->makeMember();
        $login = $this->login($user);

        $this->withToken($login->json('access_token'))
            ->postJson('/api/v1/auth/logout')
            ->assertNoContent();

        $this->withToken($login->json('access_token'))
            ->getJson('/api/v1/auth/me')
            ->assertStatus(401);

        $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => $login->json('refresh_token'),
        ])->assertStatus(401);

        $this->assertDatabaseHas('tenants', ['id' => $tenant->id]);
    }

    public function testLogoutAllRevokesEveryUiSession()
    {
        [$user, $tenant] = $this->makeMember();
        $first = $this->login($user, $tenant);
        $second = $this->login($user, $tenant);

        $this->withToken($first->json('access_token'))
            ->postJson('/api/v1/auth/logout-all')
            ->assertNoContent();

        $this->withToken($second->json('access_token'))
            ->getJson('/api/v1/auth/me')
            ->assertStatus(401);

        $this->assertSame(0, Passport::token()->newQuery()->where('is_ui_token', true)->where('revoked', false)->count());
    }

    public function testMembershipRemovalAndAuthVersionInvalidateTokens()
    {
        [$user, $tenant] = $this->makeMember();
        $membershipLogin = $this->login($user, $tenant);
        $user->tenants()->updateExistingPivot($tenant->id, ['status' => 'suspended']);

        $this->withToken($membershipLogin->json('access_token'))
            ->getJson('/api/v1/auth/me')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'membership_inactive');

        $user->tenants()->updateExistingPivot($tenant->id, ['status' => 'active']);
        $versionLogin = $this->login($user->fresh(), $tenant);
        $user->fresh()->revokeUiSessions();

        $this->withToken($versionLogin->json('access_token'))
            ->getJson('/api/v1/auth/me')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'unauthenticated');

        $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => $versionLogin->json('refresh_token'),
        ])->assertStatus(401);
    }

    public function testChangingPasswordInvalidatesExistingAccessAndRefreshTokens()
    {
        [$user, $tenant] = $this->makeMember();
        $login = $this->login($user, $tenant);

        $user->password = Hash::make('new-password');
        $user->save();

        $this->withToken($login->json('access_token'))
            ->getJson('/api/v1/auth/me')
            ->assertStatus(401);

        $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => $login->json('refresh_token'),
        ])->assertStatus(401);
    }

    public function testUiTokenCanReadOnlyItsTenantTimeline()
    {
        [$user, $tenant] = $this->makeMember();
        $otherTenant = Tenant::create(['name' => 'Other tenant']);
        $login = $this->login($user, $tenant);

        TimelineEvent::create([
            'tenant_id' => $tenant->id,
            'actor_type' => 'user',
            'actor_id' => '1',
            'action' => 'logged_in',
            'target_type' => 'session',
            'target_id' => 'owned',
            'log_level' => 'info',
            'metadata' => [],
            'occurred_at' => now(),
        ]);

        TimelineEvent::create([
            'tenant_id' => $otherTenant->id,
            'actor_type' => 'user',
            'actor_id' => '2',
            'action' => 'logged_in',
            'target_type' => 'session',
            'target_id' => 'other',
            'log_level' => 'info',
            'metadata' => [],
            'occurred_at' => now(),
        ]);

        $this->withToken($login->json('access_token'))
            ->getJson('/api/v1/timeline')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.target_id', 'owned');
    }

    public function testUiTokenLogLevelFilterRemainsTenantScoped()
    {
        [$user, $tenant] = $this->makeMember();
        $otherTenant = Tenant::create(['name' => 'Other tenant']);
        $login = $this->login($user, $tenant);

        foreach ([$tenant, $otherTenant] as $eventTenant) {
            TimelineEvent::create([
                'tenant_id' => $eventTenant->id,
                'actor_type' => 'user',
                'actor_id' => '1',
                'action' => 'failed',
                'target_type' => 'session',
                'target_id' => 'session-1',
                'log_level' => 'fatal',
                'metadata' => [],
                'occurred_at' => now(),
            ]);
        }

        $this->withToken($login->json('access_token'))
            ->getJson('/api/v1/timeline?log_level=error')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.tenant_id', $tenant->id)
            ->assertJsonPath('data.0.log_level', 'fatal');
    }

    protected function makeMember()
    {
        $user = User::create([
            'name' => 'UI User',
            'email' => 'ui@example.test',
            'password' => Hash::make('correct-password'),
        ]);
        $tenant = Tenant::create(['name' => 'Primary tenant']);
        $user->tenants()->attach($tenant->id, ['role' => 'admin', 'status' => 'active']);

        return [$user, $tenant];
    }

    protected function login(User $user, ?Tenant $tenant = null)
    {
        return $this->postJson('/api/v1/auth/login', array_filter([
            'email' => $user->email,
            'password' => 'correct-password',
            'tenant_id' => $tenant ? $tenant->id : null,
        ]))->assertOk();
    }
}
