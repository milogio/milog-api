<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * Test legacy frontend routes are disabled by default.
     *
     * @return void
     */
    public function testFrontendIsDisabledByDefault()
    {
        Log::shouldReceive('channel')
            ->once()
            ->with('stack')
            ->andReturnSelf();

        Log::shouldReceive('info')
            ->once()
            ->with('MiLog frontend access', Mockery::on(function ($context) {
                return $context['outcome'] === 'blocked'
                    && $context['purpose'] === 'legacy frontend landing page'
                    && $context['method'] === 'GET'
                    && $context['path'] === '/'
                    && ! array_key_exists('referer', $context)
                    && ! array_key_exists('user_agent', $context);
            }));

        $response = $this->withHeaders([
            'Referer' => 'https://example.test/verify-email?token=secret-token',
            'User-Agent' => 'secret-token',
        ])->get('/');

        $response->assertStatus(404);
    }

    /**
     * Test legacy frontend routes can be enabled explicitly.
     *
     * @return void
     */
    public function testFrontendCanBeEnabled()
    {
        config(['milog.frontend.enabled' => true]);

        Log::shouldReceive('channel')
            ->once()
            ->with('stack')
            ->andReturnSelf();

        Log::shouldReceive('info')
            ->once()
            ->with('MiLog frontend access', Mockery::on(function ($context) {
                return $context['outcome'] === 'allowed'
                    && $context['purpose'] === 'legacy frontend landing page';
            }));

        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function testFrontendAuditLogOmitsPasswordResetTokenFromPath()
    {
        Log::shouldReceive('channel')->once()->with('stack')->andReturnSelf();
        Log::shouldReceive('info')->once()->with('MiLog frontend access', Mockery::on(function ($context) {
            return $context['path'] === 'password/reset/{token}'
                && ! str_contains(json_encode($context), 'secret-token');
        }));

        $this->get('/password/reset/secret-token')->assertStatus(404);
    }

    /**
     * Test API routes are not controlled by the frontend flag.
     *
     * @return void
     */
    public function testApiRoutesAreNotGatedByFrontendFlag()
    {
        $response = $this->getJson('/api/v1/timeline');

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Invalid API key.',
            ]);
    }
}
