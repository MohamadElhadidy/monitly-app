<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            // Number of extra monitor packs purchased (e.g. 5 monitors per pack)
            $table->unsignedInteger('addon_extra_monitor_packs')
                ->nullable()
                ->after('billing_status');

            // Override check interval in minutes (e.g. 5, 10)
            $table->unsignedInteger('addon_interval_override_minutes')
                ->nullable()
                ->after('addon_extra_monitor_packs');
                
         $table->unsignedInteger('addon_extra_seat_packs')
                ->nullable()
                ->after('addon_interval_override_minutes');
                
            $table->timestamp('first_paid_at')->nullable()->after('addon_extra_seat_packs');

        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn([
                'addon_extra_monitor_packs',
                'addon_interval_override_minutes',
                'addon_extra_seat_packs',
                'first_paid_at'
            ]);
        });
    }
};