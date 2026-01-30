<?php

use App\Services\Admin\AdminSettingsService;
use App\Services\Audit\Audit;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new
#[Layout('layouts.admin')]
#[Title('Admin • Settings')]
class extends Component
{
    public bool $loadError = false;
    public bool $readOnlyMode = false;
    public bool $maintenanceMode = false;
    public string $adminNotificationsEmail = '';
    public ?string $selectedAction = null;
    public string $reason = '';

    public function mount(AdminSettingsService $settings): void
    {
        $setting = $settings->getSettings();
        $this->readOnlyMode = $setting->read_only_mode;
        $this->maintenanceMode = $setting->maintenance_mode;
        $this->adminNotificationsEmail = $setting->admin_notifications_email ?? '';
    }

    public function confirmAction(string $action): void
    {
        $this->selectedAction = $action;
    }

    public function performAction(AdminSettingsService $settings)
    {
        $this->validate([
            'reason' => 'required|string|min:3',
        ]);

        if ($this->selectedAction === 'toggle_readonly') {
            $this->readOnlyMode = ! $this->readOnlyMode;
            $settings->update(['read_only_mode' => $this->readOnlyMode, 'admin_notifications_email' => $this->adminNotificationsEmail]);
            Audit::log('admin.settings.read_only', null, null, ['enabled' => $this->readOnlyMode, 'reason' => $this->reason]);
        }

        if ($this->selectedAction === 'toggle_maintenance') {
            $this->maintenanceMode = ! $this->maintenanceMode;
            $settings->toggleMaintenance($this->maintenanceMode);
            Audit::log('admin.settings.maintenance', null, null, ['enabled' => $this->maintenanceMode, 'reason' => $this->reason]);
        }

        if ($this->selectedAction === 'save_notifications') {
            $settings->update([
                'admin_notifications_email' => $this->adminNotificationsEmail,
                'read_only_mode' => $this->readOnlyMode,
            ]);
            Audit::log('admin.settings.notifications_email', null, null, ['email' => $this->adminNotificationsEmail, 'reason' => $this->reason]);
        }

        if ($this->selectedAction === 'export') {
            Audit::log('admin.settings.export', null, null, ['reason' => $this->reason]);

            $rows = DB::table('users')->select('id', 'email', 'billing_plan', 'billing_status', 'created_at')->get();
            return response()->streamDownload(function () use ($rows) {
                $handle = fopen('php://output', 'wb');
                fputcsv($handle, ['id', 'email', 'billing_plan', 'billing_status', 'created_at']);
                foreach ($rows as $row) {
                    fputcsv($handle, [$row->id, $row->email, $row->billing_plan, $row->billing_status, $row->created_at]);
                }
                fclose($handle);
            }, 'monitly-admin-export.csv');
        }

        $this->reset(['selectedAction', 'reason']);
        session()->flash('status', 'Settings updated.');
    }

    public function refreshPage(): void
    {
        $this->loadError = false;
    }

    public function with(AdminSettingsService $settings): array
    {
        return ['settings' => $settings->getSettings()];
    }
};
?>

<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-semibold text-slate-900">Admin Settings</h1>
        <p class="text-sm text-slate-600">Owner-only system controls.</p>
    </div>

    @if (session('status'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ session('status') }}
        </div>
    @endif

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div class="rounded-lg border border-slate-200 bg-white p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-sm font-semibold text-slate-900">Read-only mode</div>
                    <div class="text-xs text-slate-500">Block admin actions without deleting anything.</div>
                </div>
                <button wire:click="confirmAction('toggle_readonly')" class="rounded-lg border border-slate-200 px-4 py-2 text-sm">{{ $readOnlyMode ? 'Disable' : 'Enable' }}</button>
            </div>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-sm font-semibold text-slate-900">Maintenance mode</div>
                    <div class="text-xs text-slate-500">Dangerous: blocks customer access.</div>
                </div>
                <button wire:click="confirmAction('toggle_maintenance')" class="rounded-lg border border-rose-200 text-rose-700 px-4 py-2 text-sm">{{ $maintenanceMode ? 'Disable' : 'Enable' }}</button>
            </div>
        </div>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-4">
        <div class="text-sm font-semibold text-slate-900">Admin notifications</div>
        <p class="mt-1 text-xs text-slate-500">Where owner notifications should be sent.</p>
        <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center">
            <input wire:model="adminNotificationsEmail" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="owner@monitly.app" />
            <button wire:click="confirmAction('save_notifications')" class="rounded-lg bg-slate-900 px-4 py-2 text-sm text-white">Save</button>
        </div>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-4">
        <div class="flex items-center justify-between">
            <div>
                <div class="text-sm font-semibold text-slate-900">Export data (CSV)</div>
                <div class="text-xs text-slate-500">Basic user export only.</div>
            </div>
            <button wire:click="confirmAction('export')" class="rounded-lg border border-slate-200 px-4 py-2 text-sm">Export</button>
        </div>
    </div>

    @if($selectedAction)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
            <div class="w-full max-w-lg rounded-lg bg-white p-6">
                <h2 class="text-lg font-semibold text-slate-900">Confirm action</h2>
                <p class="mt-2 text-sm text-slate-600">All admin actions require a reason.</p>
                <div class="mt-4">
                    <label class="text-xs font-semibold uppercase text-slate-500">Reason</label>
                    <textarea wire:model.defer="reason" class="mt-2 w-full rounded-lg border border-slate-200 p-2 text-sm" rows="3" placeholder="Reason required"></textarea>
                    @error('reason') <div class="text-xs text-rose-600 mt-1">{{ $message }}</div> @enderror
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <button wire:click="$set('selectedAction', null)" class="rounded-lg border border-slate-200 px-4 py-2 text-sm">Cancel</button>
                    <button wire:click="performAction" class="rounded-lg bg-slate-900 px-4 py-2 text-sm text-white">Confirm</button>
                </div>
            </div>
        </div>
    @endif
</div>
