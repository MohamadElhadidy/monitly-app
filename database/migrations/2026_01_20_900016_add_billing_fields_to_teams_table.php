<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            // Team billing (Team plan only in normal operation, but may downgrade to free after grace)
            $table->string('billing_plan', 20)->default('free')->after('personal_team'); // free|team
            $table->string('billing_status', 20)->default('free')->after('billing_plan'); // free|active|grace|canceled

            $table->string('paddle_customer_id', 100)->nullable()->after('billing_status');
            $table->string('paddle_subscription_id', 100)->nullable()->after('paddle_customer_id');

            $table->timestamp('next_bill_at')->nullable()->after('paddle_subscription_id');
            $table->timestamp('grace_ends_at')->nullable()->after('next_bill_at');

            $table->timestamp('first_paid_at')->nullable()->after('grace_ends_at');
            $table->timestamp('refund_override_until')->nullable()->after('first_paid_at');

            $table->unsignedInteger('addon_extra_monitor_packs')->default(0)->after('refund_override_until'); // +5 monitors per pack
            $table->unsignedInteger('addon_extra_seat_packs')->default(0)->after('addon_extra_monitor_packs'); // +3 seats per pack
            $table->unsignedTinyInteger('addon_interval_override_minutes')->nullable()->after('addon_extra_seat_packs'); // 2 or 1 only

            $table->index(['billing_plan', 'billing_status']);
            $table->index(['paddle_customer_id']);
            $table->index(['paddle_subscription_id']);
            $table->index(['grace_ends_at']);
            $table->index(['first_paid_at']);
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropIndex(['billing_plan', 'billing_status']);
            $table->dropIndex(['paddle_customer_id']);
            $table->dropIndex(['paddle_subscription_id']);
            $table->dropIndex(['grace_ends_at']);
            $table->dropIndex(['first_paid_at']);

            $table->dropColumn([
                'billing_plan',
                'billing_status',
                'paddle_customer_id',
                'paddle_subscription_id',
                'next_bill_at',
                'grace_ends_at',
                'first_paid_at',
                'refund_override_until',
                'addon_extra_monitor_packs',
                'addon_extra_seat_packs',
                'addon_interval_override_minutes',
            ]);
        });
    }
};