<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('status', 20)->default('active')->after('remember_token');
            $table->timestamp('suspended_at')->nullable()->after('status');
            $table->string('suspended_reason', 190)->nullable()->after('suspended_at');
            $table->timestamp('restricted_at')->nullable()->after('suspended_reason');
            $table->string('restricted_reason', 190)->nullable()->after('restricted_at');

            $table->index(['status']);
            $table->index(['suspended_at']);
            $table->index(['restricted_at']);
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->string('status', 20)->default('active')->after('personal_team');
            $table->timestamp('suspended_at')->nullable()->after('status');
            $table->string('suspended_reason', 190)->nullable()->after('suspended_at');
            $table->timestamp('banned_at')->nullable()->after('suspended_reason');
            $table->string('ban_reason', 190)->nullable()->after('banned_at');
            $table->timestamp('restricted_at')->nullable()->after('ban_reason');
            $table->string('restricted_reason', 190)->nullable()->after('restricted_at');

            $table->index(['status']);
            $table->index(['suspended_at']);
            $table->index(['banned_at']);
            $table->index(['restricted_at']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['suspended_at']);
            $table->dropIndex(['restricted_at']);
            $table->dropColumn(['status', 'suspended_at', 'suspended_reason', 'restricted_at', 'restricted_reason']);
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['suspended_at']);
            $table->dropIndex(['banned_at']);
            $table->dropIndex(['restricted_at']);
            $table->dropColumn([
                'status',
                'suspended_at',
                'suspended_reason',
                'banned_at',
                'ban_reason',
                'restricted_at',
                'restricted_reason',
            ]);
        });
    }
};
