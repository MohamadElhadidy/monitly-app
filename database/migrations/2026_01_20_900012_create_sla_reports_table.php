<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sla_reports', function (Blueprint $table) {
            $table->id();

            $table->foreignId('monitor_id')->constrained('monitors')->cascadeOnDelete();
            $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->foreignId('generated_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('window_start');
            $table->timestamp('window_end');

            // Storage reference (private, not publicly accessible)
            $table->string('storage_path', 500)->unique();

            // Immutable metadata
            $table->string('sha256', 64);
            $table->unsignedBigInteger('size_bytes');

            // Expiring download control (in addition to signed URL)
            $table->timestamp('expires_at')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['monitor_id', 'created_at']);
            $table->index(['team_id', 'created_at']);
            $table->index(['expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sla_reports');
    }
};