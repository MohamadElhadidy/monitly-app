<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('incidents', function (Blueprint $table) {
            $table->id();

            $table->foreignId('monitor_id')->constrained('monitors')->cascadeOnDelete();

            $table->timestamp('started_at')->index();
            $table->timestamp('recovered_at')->nullable()->index();

            // Computed/maintained by app logic when incident closes (recovered_at set).
            $table->unsignedBigInteger('downtime_seconds')->default(0)->nullable();

            $table->string('cause_summary', 255)->nullable();

            // system|user (later we can expand: scheduler|admin_override, etc.)
            $table->string('created_by', 32)->default('system');

            // Whether this incident counts toward SLA window
            $table->boolean('sla_counted')->default(true)->index();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['monitor_id', 'started_at'], 'incidents_monitor_started_idx');
            $table->index(['monitor_id', 'recovered_at'], 'incidents_monitor_recovered_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidents');
    }
};
