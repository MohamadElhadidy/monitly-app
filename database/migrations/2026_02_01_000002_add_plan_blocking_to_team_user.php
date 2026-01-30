<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('team_user', function (Blueprint $table) {
            if (! Schema::hasColumn('team_user', 'blocked_by_plan')) {
                $table->boolean('blocked_by_plan')->default(false)->after('role');
            }

            if (! Schema::hasColumn('team_user', 'blocked_reason')) {
                $table->string('blocked_reason', 190)->nullable()->after('blocked_by_plan');
            }
        });
    }

    public function down(): void
    {
        Schema::table('team_user', function (Blueprint $table) {
            if (Schema::hasColumn('team_user', 'blocked_reason')) {
                $table->dropColumn('blocked_reason');
            }

            if (Schema::hasColumn('team_user', 'blocked_by_plan')) {
                $table->dropColumn('blocked_by_plan');
            }
        });
    }
};
