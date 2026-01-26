<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('monitor_member_permissions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('monitor_id')->constrained('monitors')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->boolean('view_logs')->default(false);
            $table->boolean('receive_alerts')->default(false);
            $table->boolean('pause_resume')->default(false);
            $table->boolean('edit_settings')->default(false);

            $table->timestamps();

            $table->unique(['monitor_id', 'user_id'], 'mmp_monitor_user_uq');
            $table->index(['user_id', 'monitor_id'], 'mmp_user_monitor_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitor_member_permissions');
    }
};
