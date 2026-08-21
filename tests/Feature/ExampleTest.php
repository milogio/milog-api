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
                    && $context['path'] === '/';
            }));

        $response = $this->get('/');

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
