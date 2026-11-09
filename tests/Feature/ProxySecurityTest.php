<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ProxySecurityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/proxy-probe', function (Request $request) {
            return response()->json([
                'ip' => $request->ip(),
                'secure' => $request->isSecure(),
                'url' => url('/probe'),
            ]);
        });
    }

    public function testSanitizedForwardingFromNginxIsTrusted()
    {
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeaders([
                'Host' => 'api.milog.ca',
                'X-Forwarded-For' => '203.0.113.7',
                'X-Forwarded-Proto' => 'https',
                'X-Forwarded-Host' => 'api.milog.ca',
                'X-Forwarded-Port' => '443',
            ])
            ->getJson('http://api.milog.ca/proxy-probe')
            ->assertOk()
            ->assertExactJson([
                'ip' => '203.0.113.7',
                'secure' => true,
                'url' => 'https://api.milog.ca/probe',
            ]);
    }

    public function testForwardingHeadersFromAnUntrustedSourceAreIgnored()
    {
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.4'])
            ->withHeaders([
                'Host' => 'api.milog.ca',
                'X-Forwarded-For' => '203.0.113.7',
                'X-Forwarded-Proto' => 'https',
                'X-Forwarded-Host' => 'attacker.example',
            ])
            ->getJson('http://api.milog.ca/proxy-probe')
            ->assertOk()
            ->assertExactJson([
                'ip' => '192.0.2.4',
                'secure' => false,
                'url' => 'http://api.milog.ca/probe',
            ]);
    }

    public function testApiDoesNotGrantBrowserCrossOriginAccess()
    {
        $this->withHeader('Origin', 'https://example.net')
            ->getJson('/api/v1/timeline')
            ->assertStatus(401)
            ->assertHeaderMissing('Access-Control-Allow-Origin');
    }
}
