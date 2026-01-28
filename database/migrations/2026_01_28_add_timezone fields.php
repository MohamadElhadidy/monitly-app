<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add last_checked_at to monitors table
        Schema::table('monitors', function (Blueprint $table) {
            $table->timestamp('last_checked_at')->nullable()->after('next_check_at');
        });

        // Add timezone_offset to users table
        Schema::table('users', function (Blueprint $table) {
            $table->integer('timezone_offset')->default(0)->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('monitors', function (Blueprint $table) {
            $table->dropColumn('last_checked_at');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('timezone_offset');
        });
    }
};