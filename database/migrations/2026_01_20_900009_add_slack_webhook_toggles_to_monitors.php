<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monitors', function (Blueprint $table) {
            $table->boolean('slack_alerts_enabled')->default(true)->after('email_alerts_enabled');
            $table->boolean('webhook_alerts_enabled')->default(true)->after('slack_alerts_enabled');

            $table->index('slack_alerts_enabled');
            $table->index('webhook_alerts_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('monitors', function (Blueprint $table) {
            $table->dropIndex(['slack_alerts_enabled']);
            $table->dropIndex(['webhook_alerts_enabled']);
            $table->dropColumn(['slack_alerts_enabled', 'webhook_alerts_enabled']);
        });
    }
};
