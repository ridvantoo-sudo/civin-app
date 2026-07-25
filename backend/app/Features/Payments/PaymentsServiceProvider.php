<?php

namespace App\Features\Payments;

use App\Features\Payments\Events\PaymentSucceeded;
use App\Features\Payments\Gateways\PaymentGatewayManager;
use App\Features\Payments\Listeners\MarkShopOrderPaid;
use App\Features\Payments\Models\Invoice;
use App\Features\Payments\Models\PaymentTransaction;
use App\Features\Payments\Policies\InvoicePolicy;
use App\Features\Payments\Policies\PaymentTransactionPolicy;
use App\Features\Payments\Repositories\Contracts\PaymentRepository;
use App\Features\Payments\Repositories\Eloquent\EloquentPaymentRepository;
use App\Features\Payments\Services\InvoiceService;
use App\Features\Payments\Services\PaymentService;
use App\Features\Payments\Services\RevenueSharingService;
use App\Features\Payments\Services\TaxService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the monetization platform: real-money gateways (physical goods /
 * merchant orders only), revenue sharing, and tax + invoicing.
 *
 * The only cross-feature touch point is a single listener that marks the linked
 * ShopOrder paid — no existing module, migration or route is modified.
 */
final class PaymentsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../../config/payments.php', 'payments');

        $this->app->singleton(PaymentGatewayManager::class);
        $this->app->bind(PaymentRepository::class, EloquentPaymentRepository::class);

        $this->app->singleton(TaxService::class);
        $this->app->singleton(RevenueSharingService::class);
        $this->app->singleton(InvoiceService::class);
        $this->app->singleton(PaymentService::class);
    }

    public function boot(): void
    {
        Gate::policy(PaymentTransaction::class, PaymentTransactionPolicy::class);
        Gate::policy(Invoice::class, InvoicePolicy::class);

        Event::listen(PaymentSucceeded::class, MarkShopOrderPaid::class);
    }
}
