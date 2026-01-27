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
            $table->string('event_id', 120)->nullable()->change();
        });
    }

    public function down(): void
    {

    }
};