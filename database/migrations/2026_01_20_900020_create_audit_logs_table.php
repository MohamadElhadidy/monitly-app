<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            // Actor (user/system/job)
            $table->string('actor_type', 30)->default('system'); // user|system
            $table->unsignedBigInteger('actor_id')->nullable();

            // Optional team context
            $table->unsignedBigInteger('team_id')->nullable();

            // Subject
            $table->string('subject_type', 120)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();

            $table->string('action', 120); // e.g. billing.plan_changed, monitor.created
            $table->json('meta')->nullable();

            $table->string('ip', 64)->nullable();
            $table->string('user_agent', 255)->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['action', 'created_at']);
            $table->index(['actor_type', 'actor_id', 'created_at']);
            $table->index(['subject_type', 'subject_id', 'created_at']);
            $table->index(['team_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};