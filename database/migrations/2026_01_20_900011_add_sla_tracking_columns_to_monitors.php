<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monitors', function (Blueprint $table) {
            $table->decimal('sla_uptime_pct_30d', 7, 4)->nullable()->after('next_check_at');
            $table->unsignedBigInteger('sla_downtime_seconds_30d')->nullable()->after('sla_uptime_pct_30d');
            $table->unsignedInteger('sla_incident_count_30d')->nullable()->after('sla_downtime_seconds_30d');
            $table->unsignedInteger('sla_mttr_seconds_30d')->nullable()->after('sla_incident_count_30d');

            $table->timestamp('sla_last_calculated_at')->nullable()->after('sla_mttr_seconds_30d');

            $table->boolean('sla_breached')->default(false)->after('sla_last_calculated_at');
            $table->timestamp('sla_last_breach_alert_at')->nullable()->after('sla_breached');

            $table->index('sla_breached');
            $table->index('sla_last_calculated_at');
        });
    }

    public function down(): void
    {
        Schema::table('monitors', function (Blueprint $table) {
            $table->dropIndex(['sla_breached']);
            $table->dropIndex(['sla_last_calculated_at']);

            $table->dropColumn([
                'sla_uptime_pct_30d',
                'sla_downtime_seconds_30d',
                'sla_incident_count_30d',
                'sla_mttr_seconds_30d',
                'sla_last_calculated_at',
                'sla_breached',
                'sla_last_breach_alert_at',
            ]);
        });
    }
};
