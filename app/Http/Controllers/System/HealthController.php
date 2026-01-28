<?php

namespace App\Http\Controllers\System;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class HealthController
{
    public function __invoke(): JsonResponse
    {
        // Security: Require token in production. In local/testing, allow without token.
        if (app()->environment('production')) {
            $expected = (string) config('monitly.health.token');
            $provided = (string) request()->header('X-Health-Token', '');

            if ($expected === '' || !hash_equals($expected, $provided)) {
                // Hide existence to reduce probing surface
                abort(404);
            }
        }

        $checks = [
            'app' => true,
            'db' => false,
            'redis' => false,
        ];

        $errors = [];

        // DB check
        try {
            DB::select('select 1');
            $checks['db'] = true;
        } catch (\Throwable $e) {
            $errors['db'] = $e->getMessage();
        }

        // Redis check
        try {
            $pong = Redis::connection()->ping();
            $checks['redis'] = is_string($pong) ? str_contains(strtoupper($pong), 'PONG') : (bool) $pong;
        } catch (\Throwable $e) {
            $errors['redis'] = $e->getMessage();
        }

        $ok = $checks['app'] && $checks['db'] && $checks['redis'];

        return response()->json([
            'ok' => $ok,
            'env' => app()->environment(),
            'time' => now()->toIso8601String(),
            'checks' => $checks,
            'errors' => $errors,
            'version' => config('app.version', null),
        ], $ok ? 200 : 503);
    }
}