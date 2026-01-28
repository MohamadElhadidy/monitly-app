<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Ensure all timestamp columns are properly set to UTC
        DB::statement("SET time_zone = '+00:00'");
        
        // Add public status columns to users table if not exists
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'public_status_enabled')) {
                $table->boolean('public_status_enabled')->default(false)->after('timezone');
            }
            if (!Schema::hasColumn('users', 'public_status_slug')) {
                $table->string('public_status_slug', 100)->nullable()->unique()->after('public_status_enabled');
            }
        });
        
        // Add public status columns to teams table if not exists
        Schema::table('teams', function (Blueprint $table) {
            if (!Schema::hasColumn('teams', 'public_status_enabled')) {
                $table->boolean('public_status_enabled')->default(false)->after('personal_team');
            }
            if (!Schema::hasColumn('teams', 'public_status_slug')) {
                $table->string('public_status_slug', 100)->nullable()->unique()->after('public_status_enabled');
            }
            if (!Schema::hasColumn('teams', 'public_show_incidents')) {
                $table->boolean('public_show_incidents')->default(true)->after('public_status_slug');
            }
        });
        
        // Add public UUID column to monitors if not exists
        Schema::table('monitors', function (Blueprint $table) {
            if (!Schema::hasColumn('monitors', 'public_uuid')) {
                $table->uuid('public_uuid')->nullable()->unique()->after('id');
            }
            
            // Add index for public status pages
            if (!Schema::hasColumn('monitors', 'is_public')) {
                $table->boolean('is_public')->default(false)->after('paused');
                $table->index(['is_public', 'paused', 'last_status'], 'monitors_public_idx');
            }
        });
        
        // Generate UUIDs for existing monitors
        DB::table('monitors')->whereNull('public_uuid')->get()->each(function ($monitor) {
            DB::table('monitors')
                ->where('id', $monitor->id)
                ->update(['public_uuid' => \Illuminate\Support\Str::uuid()]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['public_status_enabled', 'public_status_slug']);
        });
        
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn(['public_status_enabled', 'public_status_slug', 'public_show_incidents']);
        });
        
        Schema::table('monitors', function (Blueprint $table) {
            $table->dropColumn('public_uuid');
            $table->dropIndex('monitors_public_idx');
        });
    }
};