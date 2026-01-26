<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('monitors', function (Blueprint $table) {
            $table->id();

            // Individual monitors: team_id = null, owned by user_id.
            // Team monitors: team_id set, still have an owner user_id (creator/primary owner).
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('name', 255);
            $table->string('url', 2048);

            $table->boolean('is_public')->default(false);
            $table->boolean('paused')->default(false);

            // Values we'll standardize later: unknown|up|down|degraded
            $table->string('last_status', 20)->default('unknown')->index();

            $table->unsignedSmallInteger('consecutive_failures')->default(0);

            // Scheduler selects due monitors by next_check_at
            $table->timestamp('next_check_at')->nullable()->index();

            $table->timestamps();
            $table->softDeletes();

            // Indexes for common access patterns
            $table->index(['user_id', 'paused', 'next_check_at'], 'monitors_user_due_idx');
            $table->index(['team_id', 'paused', 'next_check_at'], 'monitors_team_due_idx');
            $table->index(['team_id', 'is_public'], 'monitors_team_public_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitors');
    }
};
