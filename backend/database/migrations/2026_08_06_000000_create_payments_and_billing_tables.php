<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Real-money payment transactions (physical goods / merchant orders only).
        Schema::create('payment_transactions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->uuidMorphs('payable'); // payable_type + payable_id (e.g. ShopOrder)
            $table->string('gateway', 32)->index();
            $table->string('status', 24)->default('pending')->index();
            $table->string('currency', 3);
            $table->unsignedBigInteger('amount'); // minor units (cents/fils)
            $table->unsignedBigInteger('fee_amount')->default(0);
            $table->unsignedBigInteger('tax_amount')->default(0);
            $table->unsignedBigInteger('net_amount')->default(0);
            $table->unsignedBigInteger('refunded_amount')->default(0);
            $table->string('idempotency_key', 191)->unique();
            $table->string('provider_reference', 191)->nullable()->index(); // session/order id
            $table->string('provider_payment_id', 191)->nullable()->index(); // charge/capture id
            $table->string('client_secret', 512)->nullable();
            $table->string('checkout_url', 2048)->nullable();
            $table->string('error_code', 64)->nullable();
            $table->string('error_message', 512)->nullable();
            $table->timestamp('authorized_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status', 'created_at']);
            $table->index(['payable_type', 'payable_id', 'status']);
            $table->unique(['gateway', 'provider_payment_id']);
        });

        Schema::create('payment_refunds', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('payment_transaction_id')->constrained('payment_transactions')->cascadeOnDelete();
            $table->string('status', 24)->default('pending')->index();
            $table->string('currency', 3);
            $table->unsignedBigInteger('amount');
            $table->string('reason', 191)->nullable();
            $table->string('provider_reference', 191)->nullable();
            $table->foreignUuid('initiated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('refunded_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        // Idempotent inbound webhook ledger.
        Schema::create('payment_webhook_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('gateway', 32)->index();
            $table->string('event_type', 120)->nullable();
            $table->string('provider_event_id', 191)->nullable();
            $table->string('status', 24)->default('received')->index(); // received|processed|failed|ignored
            $table->foreignUuid('payment_transaction_id')->nullable()->constrained('payment_transactions')->nullOnDelete();
            $table->json('payload')->nullable();
            $table->string('error_message', 512)->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['gateway', 'provider_event_id']);
        });

        // Tax rates by country/region (overrides config default).
        Schema::create('tax_rates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('country_code', 2)->index();
            $table->string('region', 120)->nullable();
            $table->string('name', 120);
            $table->unsignedInteger('rate_bps'); // 100 = 1%
            $table->boolean('inclusive')->default(false);
            $table->boolean('active')->default(true)->index();
            $table->unsignedInteger('priority')->default(0);
            $table->timestamps();

            $table->unique(['country_code', 'region']);
        });

        // Invoices generated for successful payments.
        Schema::create('invoices', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('invoice_number', 40)->unique();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete(); // buyer
            $table->foreignUuid('seller_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('payment_transaction_id')->nullable()->constrained('payment_transactions')->nullOnDelete();
            $table->string('status', 24)->default('issued')->index(); // issued|paid|void|refunded
            $table->string('currency', 3);
            $table->unsignedBigInteger('subtotal_amount');
            $table->unsignedBigInteger('tax_amount')->default(0);
            $table->unsignedBigInteger('total_amount');
            $table->string('tax_country', 2)->nullable();
            $table->string('billing_name', 191)->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['seller_id', 'created_at']);
        });

        Schema::create('invoice_lines', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->string('description', 255);
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedBigInteger('unit_amount');
            $table->unsignedInteger('tax_rate_bps')->default(0);
            $table->unsignedBigInteger('tax_amount')->default(0);
            $table->unsignedBigInteger('total_amount');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('invoice_id');
        });

        // Configurable split rules (global / per-seller / per-category).
        Schema::create('revenue_share_rules', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('scope', 24)->default('global'); // global|seller|category
            $table->string('scope_id', 191)->nullable();
            $table->unsignedInteger('platform_bps');
            $table->unsignedInteger('agency_bps')->default(0);
            // merchant receives remainder (10000 - platform - agency).
            $table->boolean('active')->default(true)->index();
            $table->unsignedInteger('priority')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['scope', 'scope_id']);
        });

        // Immutable ledger of how each payment was split.
        Schema::create('revenue_shares', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('payment_transaction_id')->constrained('payment_transactions')->cascadeOnDelete();
            $table->string('beneficiary_type', 24); // platform|merchant|agency
            $table->foreignUuid('beneficiary_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('currency', 3);
            $table->unsignedBigInteger('amount');
            $table->unsignedInteger('rate_bps');
            $table->string('status', 24)->default('pending')->index(); // pending|settled|reversed
            $table->timestamp('settled_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['beneficiary_id', 'status']);
            $table->index(['payment_transaction_id', 'beneficiary_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('revenue_shares');
        Schema::dropIfExists('revenue_share_rules');
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('tax_rates');
        Schema::dropIfExists('payment_webhook_events');
        Schema::dropIfExists('payment_refunds');
        Schema::dropIfExists('payment_transactions');
    }
};
