<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monitors', function (Blueprint $table) {
            $table->boolean('email_alerts_enabled')->default(true)->after('paused');
            $table->index('email_alerts_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('monitors', function (Blueprint $table) {
            $table->dropIndex(['email_alerts_enabled']);
            $table->dropColumn('email_alerts_enabled');
        });
    }
};
