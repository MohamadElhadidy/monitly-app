<?php

namespace App\Services\Admin;

use App\Models\AdminSetting;
use Illuminate\Support\Facades\Artisan;

class AdminSettingsService
{
    public function getSettings(): AdminSetting
    {
        return AdminSetting::query()->firstOrCreate([], [
            'read_only_mode' => false,
            'maintenance_mode' => false,
            'admin_notifications_email' => null,
        ]);
    }

    public function update(array $attributes): AdminSetting
    {
        $settings = $this->getSettings();
        $settings->fill($attributes);
        $settings->save();

        return $settings;
    }

    public function toggleMaintenance(bool $enabled): void
    {
        if ($enabled) {
            Artisan::call('down');
        } else {
            Artisan::call('up');
        }

        $this->update(['maintenance_mode' => $enabled]);
    }
}
