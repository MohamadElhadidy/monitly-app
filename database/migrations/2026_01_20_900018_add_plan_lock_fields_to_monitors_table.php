<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monitors', function (Blueprint $table) {
            $table->boolean('locked_by_plan')->default(false)->after('paused');
            $table->string('locked_reason', 190)->nullable()->after('locked_by_plan');

            $table->index(['locked_by_plan', 'paused']);
        });
    }

    public function down(): void
    {
        Schema::table('monitors', function (Blueprint $table) {
            $table->dropIndex(['locked_by_plan', 'paused']);
            $table->dropColumn(['locked_by_plan', 'locked_reason']);
        });
    }
};