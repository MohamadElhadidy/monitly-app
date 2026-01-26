<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('billing_plan', 20)->default('free')->after('email');
            $table->string('billing_status', 20)->default('free')->after('billing_plan');
            $table->string('paddle_customer_id', 100)->nullable()->after('billing_status');
            $table->string('paddle_subscription_id', 100)->nullable()->after('paddle_customer_id');
            $table->timestamp('next_bill_at')->nullable()->after('paddle_subscription_id');
            $table->timestamp('grace_ends_at')->nullable()->after('next_bill_at');
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->string('billing_plan', 20)->default('free')->after('personal_team');
            $table->string('billing_status', 20)->default('free')->after('billing_plan');
            $table->string('paddle_customer_id', 100)->nullable()->after('billing_status');
            $table->string('paddle_subscription_id', 100)->nullable()->after('paddle_customer_id');
            $table->timestamp('next_bill_at')->nullable()->after('paddle_subscription_id');
            $table->timestamp('grace_ends_at')->nullable()->after('next_bill_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['billing_plan', 'billing_status', 'paddle_customer_id', 'paddle_subscription_id', 'next_bill_at', 'grace_ends_at']);
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn(['billing_plan', 'billing_status', 'paddle_customer_id', 'paddle_subscription_id', 'next_bill_at', 'grace_ends_at']);
        });
    }
};