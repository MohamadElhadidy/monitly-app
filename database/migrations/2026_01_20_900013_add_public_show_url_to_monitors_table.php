<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monitors', function (Blueprint $table) {
            // If true, public pages may display a sanitized endpoint (host only).
            // Default false to avoid leaking URLs by default.
            $table->boolean('public_show_url')->default(false)->after('is_public');

            $table->index(['is_public', 'public_show_url']);
        });
    }

    public function down(): void
    {
        Schema::table('monitors', function (Blueprint $table) {
            $table->dropIndex(['is_public', 'public_show_url']);
            $table->dropColumn('public_show_url');
        });
    }
};