<?php

namespace App\Features\Payments\Gateways;

use App\Features\Payments\Contracts\PaymentGateway;
use App\Features\Payments\DTOs\RefundResult;
use App\Features\Payments\Exceptions\PaymentGatewayException;
use App\Features\Payments\Models\PaymentTransaction;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

abstract class AbstractGateway implements PaymentGateway
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(protected array $config) {}

    abstract public function key(): string;

    public function isEnabled(): bool
    {
        return (bool) ($this->config['enabled'] ?? false);
    }

    public function isLive(): bool
    {
        return ($this->config['mode'] ?? 'sandbox') === 'live';
    }

    public function refund(PaymentTransaction $transaction, int $amountMinor, ?string $reason = null): RefundResult
    {
        throw new PaymentGatewayException(
            "The {$this->key()} gateway does not support programmatic refunds.",
            'refund_unsupported',
        );
    }

    protected function config(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    protected function baseUrl(): string
    {
        return rtrim((string) $this->config('base_url', ''), '/');
    }

    protected function http(): PendingRequest
    {
        return Http::acceptJson()
            ->timeout(30)
            ->retry(2, 200);
    }

    /**
     * Convert internal minor units to the gateway's expected major-unit
     * decimal string (e.g. 1050 -> "10.50"). Zero-decimal currencies are
     * emitted without decimals.
     */
    protected function toMajorAmount(int $minor, string $currency): string
    {
        $zeroDecimal = (array) config('payments.pricing.zero_decimal_currencies', []);

        if (in_array(strtoupper($currency), $zeroDecimal, true)) {
            return (string) $minor;
        }

        return number_format($minor / 100, 2, '.', '');
    }
}
