<?php

namespace App\Jobs\Monitoring;

use App\Models\Monitor;
use App\Models\MonitorCheck;
use App\Jobs\Monitoring\EvaluateMonitorStateJob;
use App\Services\Monitoring\MonitorHttpChecker;
use App\Services\Monitoring\MonitorIntervalResolver;
use App\Services\Monitoring\CheckResult;
use App\Services\Security\SsrfBlockedException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Throwable;

class RunMonitorCheckJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 20;

    public function __construct(public int $monitorId)
    {
        $this->onQueue('checks_standard');
    }

    public function backoff(): array
    {
        return [5, 15, 30];
    }

    public function handle(
        MonitorHttpChecker $checker,
        MonitorIntervalResolver $intervalResolver
    ): void {
        $lock = Cache::store('redis')->lock('lock:monitor_check:' . $this->monitorId, 55);

        if (! $lock->get()) {
            return;
        }

        try {
            $monitor = Monitor::query()
                ->with(['team', 'owner'])
                ->find($this->monitorId);

            if (! $monitor || $monitor->paused) {
                return;
            }

            $now = now();
            $interval = $intervalResolver->resolveMinutes($monitor);

            try {
                $result = $checker->check($monitor->url);
            } catch (SsrfBlockedException $e) {
                $result = new CheckResult(
                    ok: false,
                    statusCode: null,
                    responseTimeMs: null,
                    errorCode: 'SSRF_BLOCKED',
                    errorMessage: $this->truncate((string) $e->getMessage()),
                    resolvedIp: null,
                    resolvedHost: null,
                    finalUrl: $monitor->url,
                    meta: ['blocked' => true],
                );
            } catch (Throwable $e) {
                $result = new CheckResult(
                    ok: false,
                    statusCode: null,
                    responseTimeMs: null,
                    errorCode: 'EXCEPTION',
                    errorMessage: $this->truncate((string) $e->getMessage()),
                    resolvedIp: null,
                    resolvedHost: null,
                    finalUrl: $monitor->url,
                    meta: ['exception' => get_class($e)],
                );
            }

            MonitorCheck::query()->create([
                'monitor_id' => $monitor->id,
                'checked_at' => $now,
                'ok' => (bool) $result->ok,
                'status_code' => $result->statusCode,
                'response_time_ms' => $result->responseTimeMs,
                'error_code' => $result->errorCode,
                'error_message' => $result->errorMessage,
                'resolved_ip' => $result->resolvedIp,
                'resolved_host' => $result->resolvedHost,
                'raw_response_meta' => $result->meta + ['final_url' => $result->finalUrl],
            ]);

            $monitor->next_check_at = $now->copy()->addMinutes($interval);
            $monitor->save();

            EvaluateMonitorStateJob::dispatch((int) $monitor->id)
                ->onQueue('incidents');
        } finally {
            optional($lock)->release();
        }
    }

    private function truncate(string $msg): string
    {
        $max = (int) config('monitly.http.max_error_message_len', 500);
        $msg = trim($msg);
        if ($msg === '') return 'Request failed.';
        if (mb_strlen($msg) <= $max) return $msg;
        return mb_substr($msg, 0, $max);
    }
}
