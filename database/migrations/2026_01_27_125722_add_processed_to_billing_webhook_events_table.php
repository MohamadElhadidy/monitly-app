<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('billing_webhook_events', function (Blueprint $table) {
            if (!Schema::hasColumn('billing_webhook_events', 'processed')) {
                $table->boolean('processed')->default(false)->after('payload');
            }
        });
    }

    public function down(): void
    {
        Schema::table('billing_webhook_events', function (Blueprint $table) {
            if (Schema::hasColumn('billing_webhook_events', 'processed')) {
                $table->dropColumn('processed');
            }
        });
    }
};