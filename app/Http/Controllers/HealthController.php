<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class HealthController extends Controller
{
    public function __invoke(Request $request)
    {
        $checks = [
            'db' => filter_var(config('app.healthcheck_db', false), FILTER_VALIDATE_BOOL),
            'redis' => filter_var(config('app.healthcheck_redis', false), FILTER_VALIDATE_BOOL),
        ];

        $result = [
            'ok' => true,
            'service' => config('app.name', 'rewardstack'),
            'env' => config('app.env'),
            'version' => config('app.version'),
            'time' => now()->toIso8601String(),
            'checks' => [],
        ];

        if ($checks['db']) {
            try {
                DB::select('select 1');
                $result['checks']['db'] = 'ok';
            } catch (\Throwable $e) {
                $result['ok'] = false;
                $result['checks']['db'] = 'fail';
                $result['errors']['db'] = $e->getMessage();
            }
        }

        if ($checks['redis']) {
            try {
                $pong = Redis::connection('default')->ping();
                $result['checks']['redis'] = ($pong ? 'ok' : 'fail');
                if (!$pong) {
                    $result['ok'] = false;
                }
            } catch (\Throwable $e) {
                $result['ok'] = false;
                $result['checks']['redis'] = 'fail';
                $result['errors']['redis'] = $e->getMessage();
            }
        }

        return response()->json($result, $result['ok'] ? 200 : 503);
    }
}
