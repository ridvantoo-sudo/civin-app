<?php

namespace App\Features\Payments\Services;

use App\Features\Agency\Models\Agency;
use App\Features\Agency\Models\AgencyMember;
use App\Features\Payments\Models\PaymentTransaction;
use App\Features\Payments\Models\RevenueShare;
use App\Features\Payments\Models\RevenueShareRule;
use App\Features\Payments\Support\RevenueBeneficiary;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class RevenueSharingService
{
    /**
     * Split a succeeded transaction's net revenue between the platform, the
     * merchant (seller) and, when applicable, the seller's agency. Idempotent:
     * if shares already exist for the transaction, nothing happens.
     *
     * @return Collection<int, RevenueShare>
     */
    public function split(PaymentTransaction $transaction, string $sellerId): Collection
    {
        if (RevenueShare::query()->where('payment_transaction_id', $transaction->getKey())->exists()) {
            return RevenueShare::query()->where('payment_transaction_id', $transaction->getKey())->get();
        }

        // Revenue shared is the net (excludes tax collected on behalf of authorities).
        $base = max(0, (int) $transaction->amount - (int) $transaction->tax_amount);

        $rule = $this->resolveRule($sellerId);
        $platformBps = (int) $rule['platform_bps'];
        $agencyBps = (int) $rule['agency_bps'];

        $agencyOwnerId = $agencyBps > 0 ? $this->resolveAgencyOwner($sellerId) : null;
        if ($agencyOwnerId === null) {
            $agencyBps = 0; // no agency -> fold that share back into the merchant.
        }

        $merchantBps = max(0, 10000 - $platformBps - $agencyBps);

        $platformAmount = (int) round($base * $platformBps / 10000);
        $agencyAmount = (int) round($base * $agencyBps / 10000);
        $merchantAmount = $base - $platformAmount - $agencyAmount; // remainder avoids rounding leak.

        return DB::transaction(function () use ($transaction, $sellerId, $agencyOwnerId, $platformAmount, $agencyAmount, $merchantAmount, $platformBps, $agencyBps, $merchantBps): Collection {
            $shares = collect();

            $shares->push($this->record($transaction, RevenueBeneficiary::PLATFORM, null, $platformAmount, $platformBps));
            $shares->push($this->record($transaction, RevenueBeneficiary::MERCHANT, $sellerId, $merchantAmount, $merchantBps));

            if ($agencyOwnerId !== null && $agencyAmount > 0) {
                $shares->push($this->record($transaction, RevenueBeneficiary::AGENCY, $agencyOwnerId, $agencyAmount, $agencyBps));
            }

            return $shares;
        });
    }

    /**
     * @return array{platform_bps:int, agency_bps:int}
     */
    public function resolveRule(string $sellerId): array
    {
        $rule = RevenueShareRule::query()
            ->where('active', true)
            ->where(function ($query) use ($sellerId): void {
                $query->where(fn ($q) => $q->where('scope', RevenueShareRule::SCOPE_SELLER)->where('scope_id', $sellerId))
                    ->orWhere('scope', RevenueShareRule::SCOPE_GLOBAL);
            })
            ->orderByRaw("CASE WHEN scope = 'seller' THEN 0 ELSE 1 END")
            ->orderByDesc('priority')
            ->first();

        if ($rule !== null) {
            return ['platform_bps' => (int) $rule->platform_bps, 'agency_bps' => (int) $rule->agency_bps];
        }

        return [
            'platform_bps' => (int) config('payments.revenue_share.platform_bps', 2000),
            'agency_bps' => (int) config('payments.revenue_share.agency_bps', 500),
        ];
    }

    private function resolveAgencyOwner(string $sellerId): ?string
    {
        $member = AgencyMember::query()
            ->where('user_id', $sellerId)
            ->where('status', AgencyMember::STATUS_APPROVED)
            ->latest()
            ->first();

        if ($member === null) {
            return null;
        }

        $agency = Agency::query()->find($member->agency_id);
        $ownerId = $agency?->owner_id;

        // Don't pay an agency owner who is also the seller.
        return ($ownerId !== null && $ownerId !== $sellerId) ? $ownerId : null;
    }

    private function record(
        PaymentTransaction $transaction,
        string $beneficiaryType,
        ?string $beneficiaryId,
        int $amount,
        int $rateBps,
    ): RevenueShare {
        $autoSettle = (bool) config('payments.revenue_share.auto_settle', false);

        return RevenueShare::query()->create([
            'payment_transaction_id' => $transaction->getKey(),
            'beneficiary_type' => $beneficiaryType,
            'beneficiary_id' => $beneficiaryId,
            'currency' => $transaction->currency,
            'amount' => $amount,
            'rate_bps' => $rateBps,
            'status' => $autoSettle ? RevenueShare::STATUS_SETTLED : RevenueShare::STATUS_PENDING,
            'settled_at' => $autoSettle ? now() : null,
        ]);
    }
}
