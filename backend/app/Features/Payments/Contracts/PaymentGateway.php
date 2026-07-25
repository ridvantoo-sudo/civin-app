<?php

namespace App\Features\Payments\Contracts;

use App\Features\Payments\DTOs\GatewayIntent;
use App\Features\Payments\DTOs\GatewayWebhookResult;
use App\Features\Payments\DTOs\RefundResult;
use App\Features\Payments\Models\PaymentTransaction;
use Illuminate\Http\Request;

interface PaymentGateway
{
    /** Canonical key, one of PaymentGatewayKey::*. */
    public function key(): string;

    /** Whether the gateway is configured and enabled. */
    public function isEnabled(): bool;

    /**
     * Create a payment intent / checkout session with the provider for the
     * given (already-persisted, pending) transaction.
     *
     * @param  array<string, mixed>  $options
     */
    public function createIntent(PaymentTransaction $transaction, array $options = []): GatewayIntent;

    /**
     * Verify the signature/authenticity of an inbound webhook and normalize it.
     * Implementations MUST throw when verification fails.
     *
     * @throws \App\Features\Payments\Exceptions\WebhookVerificationException
     */
    public function verifyAndParseWebhook(Request $request): GatewayWebhookResult;

    /**
     * Refund (fully or partially) a succeeded transaction.
     */
    public function refund(PaymentTransaction $transaction, int $amountMinor, ?string $reason = null): RefundResult;
}
