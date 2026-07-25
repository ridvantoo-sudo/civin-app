<?php

namespace App\Features\Payments\Services;

use App\Features\Payments\Models\TaxRate;

final class TaxService
{
    /**
     * Resolve the applicable tax rate for a country/region. Database rows
     * override the config default. Region-specific rows win over country-wide.
     *
     * @return array{rate_bps:int, inclusive:bool, name:?string, country:?string}
     */
    public function resolveRate(?string $countryCode, ?string $region = null): array
    {
        $countryCode = $countryCode !== null ? strtoupper(substr($countryCode, 0, 2)) : null;

        if ($countryCode !== null) {
            $rate = TaxRate::query()
                ->where('active', true)
                ->where('country_code', $countryCode)
                ->where(function ($query) use ($region): void {
                    $query->whereNull('region');
                    if ($region !== null) {
                        $query->orWhere('region', $region);
                    }
                })
                ->orderByRaw('CASE WHEN region IS NULL THEN 1 ELSE 0 END') // prefer region-specific
                ->orderByDesc('priority')
                ->first();

            if ($rate !== null) {
                return [
                    'rate_bps' => (int) $rate->rate_bps,
                    'inclusive' => (bool) $rate->inclusive,
                    'name' => $rate->name,
                    'country' => $countryCode,
                ];
            }
        }

        return [
            'rate_bps' => (int) config('payments.tax.default_rate_bps', 0),
            'inclusive' => (bool) config('payments.tax.prices_include_tax', false),
            'name' => null,
            'country' => $countryCode,
        ];
    }

    /**
     * Compute tax breakdown from a gross/base amount in minor units.
     *
     * @return array{subtotal:int, tax:int, total:int, rate_bps:int}
     */
    public function compute(int $amountMinor, int $rateBps, bool $inclusive): array
    {
        if ($rateBps <= 0) {
            return ['subtotal' => $amountMinor, 'tax' => 0, 'total' => $amountMinor, 'rate_bps' => 0];
        }

        if ($inclusive) {
            // amount already includes tax: extract it.
            $subtotal = (int) round($amountMinor * 10000 / (10000 + $rateBps));
            $tax = $amountMinor - $subtotal;

            return ['subtotal' => $subtotal, 'tax' => $tax, 'total' => $amountMinor, 'rate_bps' => $rateBps];
        }

        $tax = (int) round($amountMinor * $rateBps / 10000);

        return ['subtotal' => $amountMinor, 'tax' => $tax, 'total' => $amountMinor + $tax, 'rate_bps' => $rateBps];
    }
}
