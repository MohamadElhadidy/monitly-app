<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'checkout_in_progress_until')) {
                $table->timestamp('checkout_in_progress_until')->nullable()->after('grace_ends_at');
            }
        });

        Schema::table('teams', function (Blueprint $table) {
            if (! Schema::hasColumn('teams', 'checkout_in_progress_until')) {
                $table->timestamp('checkout_in_progress_until')->nullable()->after('grace_ends_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'checkout_in_progress_until')) {
                $table->dropColumn('checkout_in_progress_until');
            }
        });

        Schema::table('teams', function (Blueprint $table) {
            if (Schema::hasColumn('teams', 'checkout_in_progress_until')) {
                $table->dropColumn('checkout_in_progress_until');
            }
        });
    }
};
