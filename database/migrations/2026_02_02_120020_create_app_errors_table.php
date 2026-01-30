<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_errors', function (Blueprint $table) {
            $table->id();
            $table->string('fingerprint', 120)->unique();
            $table->string('message', 255);
            $table->string('location', 190)->nullable();
            $table->string('route_or_queue', 190)->nullable();
            $table->unsignedBigInteger('count')->default(1);
            $table->timestamp('last_seen_at')->useCurrent();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('muted_until')->nullable();
            $table->timestamps();

            $table->index(['last_seen_at']);
            $table->index(['acknowledged_at']);
            $table->index(['muted_until']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_errors');
    }
};
