<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('webhook_endpoints', function (Blueprint $table) {
            $table->id();

            $table->foreignId('team_id')->constrained()->cascadeOnDelete();

            $table->string('url', 2048);
            $table->text('secret', 255);

            $table->boolean('enabled')->default(true)->index();

            $table->text('last_error')->nullable();

            // Retry metadata (flexible JSON; jobs will update this)
            $table->json('retry_meta')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'enabled'], 'webhooks_team_enabled_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_endpoints');
    }
};
