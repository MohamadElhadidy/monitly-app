<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            // Optional public team status page slug: /status/{slug}
            $table->string('slug', 80)->nullable()->unique()->after('name');

            // If false, /status/{slug} returns 404 (even if slug exists).
            $table->boolean('public_status_enabled')->default(false)->after('slug');

            // If true, team public status page may show monitor hostnames IF monitor->public_show_url = true.
            $table->boolean('public_status_show_urls')->default(false)->after('public_status_enabled');

            $table->index(['public_status_enabled', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropIndex(['public_status_enabled', 'slug']);
            $table->dropColumn(['public_status_show_urls', 'public_status_enabled', 'slug']);
        });
    }
};