<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_refunds', function (Blueprint $table) {
            $table->id();
            $table->morphs('billable');
            $table->foreignId('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->string('amount');
            $table->string('currency', 3)->default('USD');
            $table->string('reason', 255)->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();

            $table->index(['refunded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_refunds');
    }
};
