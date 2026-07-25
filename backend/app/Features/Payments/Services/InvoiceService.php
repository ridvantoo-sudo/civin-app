<?php

namespace App\Features\Payments\Services;

use App\Features\Payments\Models\Invoice;
use App\Features\Payments\Models\InvoiceLine;
use App\Features\Payments\Models\PaymentTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class InvoiceService
{
    /**
     * Generate (idempotently) a tax invoice for a succeeded transaction.
     *
     * @param  array<int, array{description:string, quantity:int, unit_amount:int}>|null  $lines
     */
    public function generate(
        PaymentTransaction $transaction,
        ?string $sellerId,
        string $description,
        ?string $billingName = null,
        ?array $lines = null,
    ): Invoice {
        $existing = Invoice::query()->where('payment_transaction_id', $transaction->getKey())->first();
        if ($existing !== null) {
            return $existing;
        }

        $subtotal = max(0, (int) $transaction->amount - (int) $transaction->tax_amount);
        $tax = (int) $transaction->tax_amount;
        $total = (int) $transaction->amount;
        $taxCountry = $transaction->metadata['tax_country'] ?? null;

        return DB::transaction(function () use ($transaction, $sellerId, $description, $billingName, $lines, $subtotal, $tax, $total, $taxCountry): Invoice {
            $invoice = Invoice::query()->create([
                'invoice_number' => $this->uniqueNumber(),
                'user_id' => $transaction->user_id,
                'seller_id' => $sellerId,
                'payment_transaction_id' => $transaction->getKey(),
                'status' => Invoice::STATUS_PAID,
                'currency' => $transaction->currency,
                'subtotal_amount' => $subtotal,
                'tax_amount' => $tax,
                'total_amount' => $total,
                'tax_country' => is_string($taxCountry) ? $taxCountry : null,
                'billing_name' => $billingName,
                'issued_at' => now(),
                'metadata' => [
                    'gateway' => $transaction->gateway,
                    'seller_name' => config('payments.invoice.seller_name'),
                ],
            ]);

            $rows = $lines ?? [[
                'description' => $description,
                'quantity' => 1,
                'unit_amount' => $subtotal,
            ]];

            foreach ($rows as $row) {
                $lineSubtotal = (int) $row['unit_amount'] * (int) $row['quantity'];
                InvoiceLine::query()->create([
                    'invoice_id' => $invoice->getKey(),
                    'description' => $row['description'],
                    'quantity' => (int) $row['quantity'],
                    'unit_amount' => (int) $row['unit_amount'],
                    'tax_rate_bps' => 0,
                    'tax_amount' => 0,
                    'total_amount' => $lineSubtotal,
                ]);
            }

            return $invoice->load('lines');
        });
    }

    private function uniqueNumber(): string
    {
        $prefix = (string) config('payments.invoice.number_prefix', 'CIV');

        do {
            $number = sprintf('%s-%s-%s', $prefix, now()->format('Ymd'), strtoupper(Str::random(6)));
        } while (Invoice::query()->where('invoice_number', $number)->exists());

        return $number;
    }
}
