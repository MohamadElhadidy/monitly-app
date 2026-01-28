<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('notification_channels', function (Blueprint $table) {
            $table->id();

            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->unique('team_id');

            $table->boolean('email_enabled')->default(true);

            // Team plan enables these; still stored to avoid conditional schema.
            $table->boolean('slack_enabled')->default(false);
            $table->string('slack_webhook_url', 2048)->nullable();

            $table->boolean('webhooks_enabled')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_channels');
    }
};
