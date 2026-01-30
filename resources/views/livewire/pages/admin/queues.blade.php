<?php

use App\Services\Admin\AdminSettingsService;
use App\Services\Audit\Audit;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new
#[Layout('layouts.admin')]
#[Title('Admin • Queues')]
class extends Component
{
    public bool $loadError = false;

    public function pauseQueue(AdminSettingsService $settings, string $queue): void
    {
        if ($settings->getSettings()->read_only_mode) {
            $this->addError('queue', 'Read-only mode is enabled.');
            return;
        }

        Cache::put("admin.queue.paused.{$queue}", true, now()->addHours(12));
        Audit::log('admin.queue.paused', null, null, ['queue' => $queue]);
    }

    public function resumeQueue(AdminSettingsService $settings, string $queue): void
    {
        if ($settings->getSettings()->read_only_mode) {
            $this->addError('queue', 'Read-only mode is enabled.');
            return;
        }

        Cache::forget("admin.queue.paused.{$queue}");
        Audit::log('admin.queue.resumed', null, null, ['queue' => $queue]);
    }

    public function refreshPage(): void
    {
        $this->loadError = false;
    }

    public function with(): array
    {
        $queues = collect(config('admin.queue_names', []))->map(function ($queue) {
            $waiting = DB::table('jobs')->where('queue', $queue)->whereNull('reserved_at')->count();
            $processing = DB::table('jobs')->where('queue', $queue)->whereNotNull('reserved_at')->count();
            $failed = DB::table('failed_jobs')->where('queue', $queue)->count();
            $oldest = DB::table('jobs')->where('queue', $queue)->orderBy('available_at')->value('available_at');

            return [
                'name' => $queue,
                'waiting' => $waiting,
                'processing' => $processing,
                'failed' => $failed,
                'oldest_age' => $oldest ? now()->diffInMinutes(\Carbon\Carbon::createFromTimestamp($oldest)) : null,
                'avg_time' => '—',
                'paused' => Cache::get("admin.queue.paused.{$queue}", false),
            ];
        });

        return ['queues' => $queues];
    }
};
?>

<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-semibold text-slate-900">Queues</h1>
        <p class="text-sm text-slate-600">Queue backlog, processing, and pause controls.</p>
    </div>

    @if($loadError)
        <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            Unable to load queue metrics.
            <button wire:click="refreshPage" class="ml-3 rounded border border-rose-300 px-2 py-1 text-xs font-semibold">Retry</button>
        </div>
    @endif

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3 text-left">Queue</th>
                    <th class="px-4 py-3 text-left">Waiting</th>
                    <th class="px-4 py-3 text-left">Processing</th>
                    <th class="px-4 py-3 text-left">Failed</th>
                    <th class="px-4 py-3 text-left">Oldest Job Age</th>
                    <th class="px-4 py-3 text-left">Avg Job Time</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
                @forelse($queues as $queue)
                    <tr>
                        <td class="px-4 py-3 font-medium text-slate-900">{{ $queue['name'] }}</td>
                        <td class="px-4 py-3">{{ $queue['waiting'] }}</td>
                        <td class="px-4 py-3">{{ $queue['processing'] }}</td>
                        <td class="px-4 py-3">{{ $queue['failed'] }}</td>
                        <td class="px-4 py-3">{{ $queue['oldest_age'] ? $queue['oldest_age'] . ' min' : '—' }}</td>
                        <td class="px-4 py-3">{{ $queue['avg_time'] }}</td>
                        <td class="px-4 py-3 text-right">
                            @if($queue['paused'])
                                <button wire:click="resumeQueue('{{$queue['name']}}')" class="text-xs font-semibold text-emerald-700 hover:underline">Resume</button>
                            @else
                                <button wire:click="pauseQueue('{{$queue['name']}}')" class="text-xs font-semibold text-amber-700 hover:underline">Pause</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-sm text-slate-500">No queues configured.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
