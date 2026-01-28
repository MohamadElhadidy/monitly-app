<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_channels', function (Blueprint $table) {
            $table->text('slack_last_error')->nullable()->after('slack_webhook_url');
            $table->json('slack_retry_meta')->nullable()->after('slack_last_error');
        });
    }

    public function down(): void
    {
        Schema::table('notification_channels', function (Blueprint $table) {
            $table->dropColumn(['slack_last_error', 'slack_retry_meta']);
        });
    }
};
