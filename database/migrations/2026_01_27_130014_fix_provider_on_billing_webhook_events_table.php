<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('billing_webhook_events', function (Blueprint $table) {
            // Make provider nullable OR give it a default.
            // Default is safer if you want to keep it not-null.
            $table->string('provider')->default('paddle')->change();
        });
    }

    public function down(): void
    {
        Schema::table('billing_webhook_events', function (Blueprint $table) {
            $table->string('provider')->default(null)->change(); // adjust if you know the previous state
        });
    }
};