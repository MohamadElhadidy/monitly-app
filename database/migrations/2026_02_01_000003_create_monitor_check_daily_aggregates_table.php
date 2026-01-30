<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitor_check_daily_aggregates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monitor_id')->constrained()->onDelete('cascade');
            $table->date('day');
            $table->unsignedInteger('total_checks');
            $table->unsignedInteger('ok_checks');
            $table->unsignedInteger('avg_response_time_ms')->nullable();
            $table->timestamps();

            $table->unique(['monitor_id', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitor_check_daily_aggregates');
    }
};
