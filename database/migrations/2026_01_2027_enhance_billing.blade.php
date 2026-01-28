<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add missing columns to users table
        Schema::table('users', function (Blueprint $table) {
            // Check if columns don't exist before adding
            if (!Schema::hasColumn('users', 'monitor_limit_override')) {
                $table->integer('monitor_limit_override')->nullable()->after('billing_plan');
            }
            if (!Schema::hasColumn('users', 'user_limit_override')) {
                $table->integer('user_limit_override')->nullable()->after('monitor_limit_override');
            }
            if (!Schema::hasColumn('users', 'trial_ends_at')) {
                $table->timestamp('trial_ends_at')->nullable()->after('user_limit_override');
            }
            if (!Schema::hasColumn('users', 'has_payment_method')) {
                $table->boolean('has_payment_method')->default(false)->after('trial_ends_at');
            }
            if (!Schema::hasColumn('users', 'billing_email')) {
                $table->string('billing_email')->nullable()->after('email');
            }
            if (!Schema::hasColumn('users', 'company_name')) {
                $table->string('company_name')->nullable()->after('billing_email');
            }
            if (!Schema::hasColumn('users', 'tax_id')) {
                $table->string('tax_id', 50)->nullable()->after('company_name');
            }
            if (!Schema::hasColumn('users', 'billing_address')) {
                $table->text('billing_address')->nullable()->after('tax_id');
            }
            if (!Schema::hasColumn('users', 'last_bill_at')) {
                $table->timestamp('last_bill_at')->nullable()->after('next_bill_at');
            }
        });

        // Create billing_invoices table
        Schema::create('billing_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('paddle_invoice_id')->nullable()->unique();
            $table->string('paddle_transaction_id')->nullable();
            $table->string('number')->unique();
            $table->decimal('amount', 10, 2);
            $table->decimal('tax', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->string('status')->default('pending'); // pending, paid, failed, refunded
            $table->json('items')->nullable();
            $table->text('description')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'status']);
            $table->index('paddle_invoice_id');
        });

        // Create billing_transactions table
        Schema::create('billing_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('invoice_id')->nullable()->constrained('billing_invoices')->onDelete('set null');
            $table->string('paddle_transaction_id')->unique();
            $table->string('type'); // payment, refund, adjustment, credit
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->string('status'); // completed, pending, failed
            $table->string('payment_method')->nullable();
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'type']);
            $table->index('paddle_transaction_id');
        });

        // Create billing_payment_methods table
        Schema::create('billing_payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('paddle_payment_method_id')->unique();
            $table->string('type'); // card, paypal, etc.
            $table->string('card_brand')->nullable();
            $table->string('card_last4')->nullable();
            $table->integer('card_exp_month')->nullable();
            $table->integer('card_exp_year')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            
            $table->index(['user_id', 'is_default']);
        });

        // Create billing_usage_records table
        Schema::create('billing_usage_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('resource_type'); // monitors, checks, users, etc.
            $table->integer('quantity');
            $table->date('recorded_date');
            $table->timestamps();
            
            $table->index(['user_id', 'recorded_date']);
            $table->index(['user_id', 'resource_type', 'recorded_date']);
        });

        // Create billing_promo_codes table
        Schema::create('billing_promo_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('type'); // percentage, fixed
            $table->decimal('value', 10, 2);
            $table->integer('max_uses')->nullable();
            $table->integer('times_used')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('active')->default(true);
            $table->json('applicable_plans')->nullable(); // Which plans this applies to
            $table->timestamps();
            
            $table->index('code');
            $table->index(['active', 'expires_at']);
        });

        // Create billing_promo_code_usage table
        Schema::create('billing_promo_code_usage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('promo_code_id')->constrained('billing_promo_codes')->onDelete('cascade');
            $table->timestamp('used_at');
            $table->timestamps();
            
            $table->index(['user_id', 'promo_code_id']);
        });

        // Create billing_refund_requests table
        Schema::create('billing_refund_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('invoice_id')->nullable()->constrained('billing_invoices')->onDelete('set null');
            $table->string('paddle_refund_id')->nullable();
            $table->text('reason');
            $table->string('status')->default('pending'); // pending, approved, rejected, processed
            $table->decimal('amount', 10, 2);
            $table->text('admin_notes')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billing_refund_requests');
        Schema::dropIfExists('billing_promo_code_usage');
        Schema::dropIfExists('billing_promo_codes');
        Schema::dropIfExists('billing_usage_records');
        Schema::dropIfExists('billing_payment_methods');
        Schema::dropIfExists('billing_transactions');
        Schema::dropIfExists('billing_invoices');
        
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'monitor_limit_override',
                'user_limit_override',
                'trial_ends_at',
                'has_payment_method',
                'billing_email',
                'company_name',
                'tax_id',
                'billing_address',
                'last_bill_at',
            ]);
        });
    }
};