<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('password');

            $table->string('billing_plan', 20)->default('free')->after('is_admin'); // free|pro
            $table->string('billing_status', 20)->default('free')->after('billing_plan'); // free|active|grace|canceled

            $table->string('paddle_customer_id', 100)->nullable()->after('billing_status');
            $table->string('paddle_subscription_id', 100)->nullable()->after('paddle_customer_id');

            $table->timestamp('next_bill_at')->nullable()->after('paddle_subscription_id');
            $table->timestamp('grace_ends_at')->nullable()->after('next_bill_at');

            // Immutable: set on first successful payment, renewals do not reset
            $table->timestamp('first_paid_at')->nullable()->after('grace_ends_at');

            // Admin override: if set, refunds are allowed until this timestamp (even if >30d from first_paid_at)
            $table->timestamp('refund_override_until')->nullable()->after('first_paid_at');

            // Add-ons (Pro only for these user-level fields)
            $table->unsignedInteger('addon_extra_monitor_packs')->default(0)->after('refund_override_until'); // +5 monitors per pack
            $table->unsignedTinyInteger('addon_interval_override_minutes')->nullable()->after('addon_extra_monitor_packs'); // 2 or 1 only

            $table->index(['billing_plan', 'billing_status']);
            $table->index(['paddle_customer_id']);
            $table->index(['paddle_subscription_id']);
            $table->index(['grace_ends_at']);
            $table->index(['first_paid_at']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['billing_plan', 'billing_status']);
            $table->dropIndex(['paddle_customer_id']);
            $table->dropIndex(['paddle_subscription_id']);
            $table->dropIndex(['grace_ends_at']);
            $table->dropIndex(['first_paid_at']);

            $table->dropColumn([
                'is_admin',
                'billing_plan',
                'billing_status',
                'paddle_customer_id',
                'paddle_subscription_id',
                'next_bill_at',
                'grace_ends_at',
                'first_paid_at',
                'refund_override_until',
                'addon_extra_monitor_packs',
                'addon_interval_override_minutes',
            ]);
        });
    }
};