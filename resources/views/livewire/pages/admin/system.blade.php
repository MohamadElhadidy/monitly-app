<?php

use App\Services\System\QueueHealth;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new
#[Layout('layouts.app')]
#[Title('Admin • System Health')]
class extends Component
{
    public function mount(): void
    {
        abort_unless(auth()->user()?->can('access-admin'), 403);
    }

    public function with(): array
    {
        $health = app(QueueHealth::class)->snapshot();

        $hb = Cache::get('system:scheduler_heartbeat');
        $hbAt = $hb ? now()->parse($hb) : null;
        $hbOk = $hbAt ? $hbAt->diffInSeconds(now()) <= 120 : false;

        return [
            'health' => $health,
            'hbAt' => $hbAt,
            'hbOk' => $hbOk,
        ];
    }
};
?>

<div class="space-y-6">
    <div class="sticky top-0 z-20 -mx-4 sm:-mx-6 lg:-mx-8 px-4 sm:px-6 lg:px-8 py-4 bg-white/80 backdrop-blur border-b border-slate-200">
        <div class="flex items-start justify-between gap-4">
            <div>
                <div class="text-xl font-semibold text-slate-900">System Health</div>
                <div class="mt-1 text-sm text-slate-600">Queues, Redis, failed jobs, scheduler heartbeat.</div>
            </div>

            <div class="flex items-center gap-2">
                <a href="{{ route('admin.index') }}" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Overview</a>
                <a href="{{ route('admin.audit_logs') }}" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Audit logs</a>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm p-6">
            <div class="text-sm font-semibold text-slate-900">Redis</div>
            <div class="mt-3 text-sm text-slate-600">
                Status:
                @if ($health['redis_ok'])
                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200">OK</span>
                @else
                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-rose-50 text-rose-700 ring-1 ring-rose-200">DOWN</span>
                @endif
            </div>
            <div class="mt-2 text-xs text-slate-500">Cache + sessions + queues backend.</div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white shadow-sm p-6">
            <div class="text-sm font-semibold text-slate-900">Scheduler Heartbeat</div>
            <div class="mt-3 text-sm text-slate-600">
                Status:
                @if ($hbOk)
                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200">OK</span>
                @else
                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-rose-50 text-rose-700 ring-1 ring-rose-200">MISSING</span>
                @endif
            </div>
            <div class="mt-2 text-xs text-slate-500">
                Last: {{ $hbAt ? $hbAt->format('Y-m-d H:i:s') : '—' }}
            </div>
            <div class="mt-3 text-xs text-slate-500">
                Cron must run <span class="font-mono">php artisan schedule:run</span> every minute.
            </div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white shadow-sm p-6">
            <div class="text-sm font-semibold text-slate-900">Failed Jobs</div>
            <div class="mt-3 text-2xl font-semibold text-slate-900">{{ $health['failed_jobs'] }}</div>
            <div class="mt-2 text-xs text-slate-500">From failed_jobs table.</div>
        </div>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white shadow-sm p-6">
        <div class="flex items-center justify-between">
            <div>
                <div class="text-sm font-semibold text-slate-900">Queue Depth</div>
                <div class="mt-1 text-sm text-slate-600">Pending / delayed / reserved by queue.</div>
            </div>
        </div>

        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-semibold text-slate-600">Queue</th>
                        <th class="px-4 py-2 text-left text-xs font-semibold text-slate-600">Pending</th>
                        <th class="px-4 py-2 text-left text-xs font-semibold text-slate-600">Delayed</th>
                        <th class="px-4 py-2 text-left text-xs font-semibold text-slate-600">Reserved</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @foreach ($health['queues'] as $q)
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-2 text-sm font-medium text-slate-900">{{ $q['name'] }}</td>
                            <td class="px-4 py-2 text-sm text-slate-600">{{ $q['pending'] }}</td>
                            <td class="px-4 py-2 text-sm text-slate-600">{{ $q['delayed'] }}</td>
                            <td class="px-4 py-2 text-sm text-slate-600">{{ $q['reserved'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>