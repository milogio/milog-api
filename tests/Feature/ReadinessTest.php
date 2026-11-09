<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Tests\TestCase;

class ReadinessTest extends TestCase
{
    public function testReadyWhenDatabaseAndRedisRespond()
    {
        DB::shouldReceive('select')->once()->with('SELECT 1')->andReturn([(object) ['one' => 1]]);
        Redis::shouldReceive('connection')->once()->andReturn($this->redisConnection(true));

        $this->getJson('/ready')
            ->assertOk()
            ->assertExactJson(['status' => 'ready']);
    }

    public function testDatabaseFailureReturnsGenericUnavailableResponse()
    {
        DB::shouldReceive('select')->once()->andThrow(new \RuntimeException('database-secret'));
        $this->getJson('/ready')
            ->assertStatus(503)
            ->assertExactJson(['status' => 'unavailable'])
            ->assertDontSee('database-secret');
    }

    public function testRedisFailureReturnsGenericUnavailableResponse()
    {
        DB::shouldReceive('select')->once()->andReturn([(object) ['one' => 1]]);
        Redis::shouldReceive('connection')->once()->andReturn($this->redisConnection(false));

        $this->getJson('/ready')
            ->assertStatus(503)
            ->assertExactJson(['status' => 'unavailable']);
    }

    private function redisConnection($result)
    {
        $connection = Mockery::mock();
        $connection->shouldReceive('ping')->once()->andReturn($result);

        return $connection;
    }
}
