<?php

namespace App\Services\System;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class QueueHealth
{
    /**
     * Returns health for common Redis queues:
     * - pending: list length
     * - delayed: zset cardinality
     * - reserved: zset cardinality
     */
    public function snapshot(array $queues = ['default', 'billing', 'notifications', 'monitoring']): array
    {
        $out = [
            'redis_ok' => false,
            'queues' => [],
            'failed_jobs' => 0,
        ];

        try {
            $pong = Redis::connection()->ping();
            $out['redis_ok'] = is_string($pong) && str_contains(strtolower($pong), 'pong');
        } catch (\Throwable $e) {
            $out['redis_ok'] = false;
        }

        foreach ($queues as $q) {
            $keyPending = "queues:{$q}";
            $keyDelayed = "queues:{$q}:delayed";
            $keyReserved = "queues:{$q}:reserved";

            try {
                $pending = (int) Redis::llen($keyPending);
            } catch (\Throwable $e) {
                $pending = -1;
            }

            try {
                $delayed = (int) Redis::zcard($keyDelayed);
            } catch (\Throwable $e) {
                $delayed = -1;
            }

            try {
                $reserved = (int) Redis::zcard($keyReserved);
            } catch (\Throwable $e) {
                $reserved = -1;
            }

            $out['queues'][] = [
                'name' => $q,
                'pending' => $pending,
                'delayed' => $delayed,
                'reserved' => $reserved,
            ];
        }

        try {
            $out['failed_jobs'] = (int) DB::table('failed_jobs')->count();
        } catch (\Throwable $e) {
            $out['failed_jobs'] = 0;
        }

        return $out;
    }
}