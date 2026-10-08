<?php

namespace Tests\Feature;

use App\ApiKey;
use App\Mail\VerifyMiLogSignup;
use App\Tenant;
use App\TenantEntitlement;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class SignupAndApiKeysTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(ClientRepository::class)->createPersonalAccessGrantClient('MiLog Signup Tests', 'users');
    }

    public function testSignupRequiresVerificationBeforeLoginAndTemporaryKeyUse()
    {
        Mail::fake();

        $this->postJson('/api/v1/signup', $this->signupPayload())->assertStatus(202);
        $this->assertDatabaseHas('users', ['email' => 'owner@example.com', 'status' => 'pending']);
        $this->assertDatabaseHas('tenants', ['name' => 'Example Org', 'status' => 'pending']);
        $this->assertDatabaseCount('api_keys', 0);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'owner@example.com',
            'password' => 'very-secure-password',
        ])->assertStatus(401);

        $url = null;
        Mail::assertSent(VerifyMiLogSignup::class, function ($mail) use (&$url) {
            $url = $mail->verificationUrl;

            return $mail->hasTo('owner@example.com');
        });
        parse_str(parse_url($url, PHP_URL_QUERY), $query);

        $this->postJson('/api/v1/signup/verify', ['token' => $query['token']])->assertOk();
        $this->postJson('/api/v1/signup/verify', ['token' => $query['token']])
            ->assertStatus(422)->assertJsonPath('error.code', 'invalid_verification');

        $user = User::where('email', 'owner@example.com')->firstOrFail();
        $tenant = Tenant::where('name', 'Example Org')->firstOrFail();
        $this->assertSame('active', $user->status);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNotNull($user->terms_accepted_at);
        $this->assertDatabaseHas('tenant_user', [
            'tenant_id' => $tenant->id, 'user_id' => $user->id, 'role' => 'owner', 'status' => 'active',
        ]);
        $this->assertTrue($tenant->entitlement->trial_ends_at->isFuture());

        $accessToken = $this->postJson('/api/v1/auth/login', [
            'email' => 'owner@example.com',
            'password' => 'very-secure-password',
        ])->assertOk()->json('access_token');

        $this->withToken($accessToken)->getJson('/api/v1/entitlement')
            ->assertOk()->assertJsonPath('data.state', 'evaluation')
            ->assertJsonPath('data.can_create_temporary_key', true);

        $created = $this->withToken($accessToken)->postJson('/api/v1/api-keys', [
            'name' => 'Development', 'kind' => 'temporary', 'password' => 'very-secure-password',
        ])->assertCreated()
            ->assertJsonPath('data.kind', 'temporary');
        $this->assertStringContainsString('no-store', $created->headers->get('Cache-Control'));
        $rawKey = $created->json('api_key');

        $this->withToken($accessToken)->getJson('/api/v1/api-keys')
            ->assertOk()->assertJsonMissing(['api_key' => $rawKey]);
        $this->withHeader('X-API-Key', $rawKey)->getJson('/api/v1/timeline')->assertOk();

        $key = ApiKey::where('kind', 'temporary')->firstOrFail();
        $key->forceFill(['expires_at' => now()->subSecond()])->save();
        $this->withHeader('X-API-Key', $rawKey)->getJson('/api/v1/timeline')->assertStatus(401);
    }

    public function testSignupIsGenericForExistingEmailAndResendRotatesVerification()
    {
        Mail::fake();
        $this->postJson('/api/v1/signup', $this->signupPayload())->assertStatus(202);
        $originalHash = \App\SignupVerification::firstOrFail()->token_hash;

        $this->postJson('/api/v1/signup', $this->signupPayload())->assertStatus(202);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('tenants', 1);

        $this->postJson('/api/v1/signup/resend', ['email' => 'OWNER@example.com'])->assertStatus(202);
        $this->assertNotSame($originalHash, \App\SignupVerification::firstOrFail()->token_hash);
        $this->assertDatabaseCount('signup_verifications', 1);
    }

    public function testKeyManagementIsTenantBoundAndPaidKeysRequireEntitlement()
    {
        [$owner, $tenant, $token] = $this->activeMember('owner');
        [$otherOwner, $otherTenant, $otherToken] = $this->activeMember('owner', 'other@example.com');
        TenantEntitlement::create(['tenant_id' => $tenant->id, 'trial_ends_at' => now()->addDays(14)]);
        TenantEntitlement::create(['tenant_id' => $otherTenant->id, 'trial_ends_at' => now()->addDays(14)]);

        $this->withToken($token)->postJson('/api/v1/api-keys', [
            'name' => 'Production', 'kind' => 'paid', 'password' => 'very-secure-password',
        ])->assertStatus(403)->assertJsonPath('error.code', 'entitlement_required');

        $this->withToken($token)->postJson('/api/v1/api-keys', [
            'name' => 'Development', 'kind' => 'temporary', 'password' => 'wrong-password',
        ])->assertStatus(401);

        $created = $this->withToken($token)->postJson('/api/v1/api-keys', [
            'name' => 'Development', 'kind' => 'temporary', 'password' => 'very-secure-password',
        ])->assertCreated();
        $keyId = $created->json('data.id');
        $rawKey = $created->json('api_key');

        app('auth')->forgetGuards();
        $this->withToken($otherToken)->getJson('/api/v1/api-keys')
            ->assertOk()->assertJsonCount(0, 'data');
        $this->withToken($otherToken)->deleteJson('/api/v1/api-keys/'.$keyId)->assertNotFound();
        app('auth')->forgetGuards();
        $this->withToken($token)->deleteJson('/api/v1/api-keys/'.$keyId)->assertNoContent();
        $this->withHeader('X-API-Key', $rawKey)->getJson('/api/v1/timeline')->assertStatus(401);

        $tenant->entitlement->forceFill([
            'billing_status' => 'active', 'paid_through_at' => now()->addMonth(),
        ])->save();
        $paid = $this->withToken($token)->postJson('/api/v1/api-keys', [
            'name' => 'Production', 'kind' => 'paid', 'password' => 'very-secure-password',
        ])->assertCreated();
        $this->assertNull(ApiKey::findOrFail($paid->json('data.id'))->expires_at);

        $paidKey = $paid->json('api_key');
        $this->withHeader('X-API-Key', $paidKey)->getJson('/api/v1/timeline')->assertOk();
        $tenant->entitlement->forceFill(['billing_status' => 'past_due', 'grace_ends_at' => now()->subSecond()])->save();
        $this->withHeader('X-API-Key', $paidKey)->getJson('/api/v1/timeline')->assertStatus(401);
        $this->withToken($token)->getJson('/api/v1/entitlement')->assertOk();
    }

    public function testLegacyKeysContinueWorkingButSuspendedTenantIsDenied()
    {
        $tenant = Tenant::create(['name' => 'Existing']);
        $rawKey = 'milog_existing_key';
        ApiKey::create([
            'tenant_id' => $tenant->id,
            'name' => 'Existing',
            'key_prefix' => ApiKey::keyPrefix($rawKey),
            'key_hash' => ApiKey::hashKey($rawKey),
        ]);

        $this->withHeader('X-API-Key', $rawKey)->getJson('/api/v1/timeline')->assertOk();
        $tenant->forceFill(['status' => 'suspended'])->save();
        $this->withHeader('X-API-Key', $rawKey)->getJson('/api/v1/timeline')->assertStatus(401);
    }

    public function testTrialExpiryAndLifetimeIssuanceLimitCannotBeBypassedByRevocation()
    {
        [, $tenant, $token] = $this->activeMember('owner');
        TenantEntitlement::create(['tenant_id' => $tenant->id, 'trial_ends_at' => now()->addDays(14)]);
        config(['milog.api_keys.temporary_issuance_limit' => 1]);

        $created = $this->withToken($token)->postJson('/api/v1/api-keys', [
            'name' => 'Evaluation', 'kind' => 'temporary', 'password' => 'very-secure-password',
        ])->assertCreated();
        $rawKey = $created->json('api_key');

        $this->withToken($token)->deleteJson('/api/v1/api-keys/'.$created->json('data.id'))->assertNoContent();
        $this->withToken($token)->postJson('/api/v1/api-keys', [
            'name' => 'Replacement', 'kind' => 'temporary', 'password' => 'very-secure-password',
        ])->assertStatus(409)->assertJsonPath('error.code', 'key_limit_reached');

        $tenant->entitlement->forceFill(['trial_ends_at' => now()->subSecond()])->save();
        $this->withToken($token)->getJson('/api/v1/entitlement')
            ->assertOk()->assertJsonPath('data.state', 'inactive');
        $this->withHeader('X-API-Key', $rawKey)->getJson('/api/v1/timeline')->assertStatus(401);
    }

    public function testMemberCannotManageKeys()
    {
        [, $tenant, $token] = $this->activeMember('member');
        TenantEntitlement::create(['tenant_id' => $tenant->id, 'trial_ends_at' => now()->addDays(14)]);

        $this->withToken($token)->getJson('/api/v1/entitlement')->assertOk();
        $this->withToken($token)->getJson('/api/v1/api-keys')
            ->assertStatus(403)->assertJsonPath('error.code', 'forbidden');
        $this->withToken($token)->postJson('/api/v1/api-keys', [
            'name' => 'Denied', 'kind' => 'temporary', 'password' => 'very-secure-password',
        ])->assertStatus(403);
    }

    public function testLegacyProvisioningRequiresExplicitAdministrativeOverride()
    {
        $this->artisan('milog:provision-tenant', ['name' => 'Unapproved'])->assertExitCode(2);
        $this->assertDatabaseCount('tenants', 0);
        $this->assertDatabaseCount('api_keys', 0);
    }

    protected function signupPayload()
    {
        return [
            'name' => 'Owner',
            'tenant_name' => 'Example Org',
            'email' => 'owner@example.com',
            'password' => 'very-secure-password',
            'password_confirmation' => 'very-secure-password',
            'terms_accepted' => true,
        ];
    }

    protected function activeMember($role, $email = 'owner@example.com')
    {
        $user = User::create([
            'name' => 'Owner', 'email' => $email,
            'password' => Hash::make('very-secure-password'),
        ]);
        $tenant = Tenant::create(['name' => $email]);
        $user->tenants()->attach($tenant->id, ['role' => $role, 'status' => 'active']);
        $token = $this->postJson('/api/v1/auth/login', [
            'email' => $email, 'password' => 'very-secure-password',
        ])->assertOk()->json('access_token');

        return [$user, $tenant, $token];
    }
}
