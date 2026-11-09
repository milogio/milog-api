<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

class ReadinessController extends Controller
{
    public function __invoke()
    {
        try {
            DB::select('SELECT 1');

            if (! Redis::connection()->ping()) {
                throw new \RuntimeException('Redis ping failed');
            }
        } catch (Throwable $exception) {
            return response()->json(['status' => 'unavailable'], 503)
                ->header('Cache-Control', 'no-store');
        }

        return response()->json(['status' => 'ready'])
            ->header('Cache-Control', 'no-store');
    }
}
