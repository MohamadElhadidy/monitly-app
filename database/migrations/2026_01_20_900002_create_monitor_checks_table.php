<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('monitor_checks', function (Blueprint $table) {
            $table->id();

            $table->foreignId('monitor_id')->constrained('monitors')->cascadeOnDelete();

            $table->timestamp('checked_at')->index();

            $table->boolean('ok')->index();

            $table->unsignedSmallInteger('status_code')->nullable();
            $table->unsignedInteger('response_time_ms')->nullable();

            $table->string('error_code', 64)->nullable();
            $table->text('error_message')->nullable();

            $table->string('resolved_ip', 45)->nullable();    // IPv4/IPv6
            $table->string('resolved_host', 255)->nullable();

            $table->json('raw_response_meta')->nullable();

            $table->timestamps();

            $table->index(['monitor_id', 'checked_at'], 'checks_monitor_time_idx');
            $table->index(['monitor_id', 'ok', 'checked_at'], 'checks_monitor_ok_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitor_checks');
    }
};
