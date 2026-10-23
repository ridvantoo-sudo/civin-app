<?php

namespace App\Features\Wallet\Support;

use Illuminate\Validation\ValidationException;

final class PaymentProofVerifier
{
    public function assertVerified(string $provider, string $transactionId): void
    {
        $provider = strtolower(trim($provider));

        if ($provider === 'sandbox' && app()->environment(['local', 'testing'])) {
            if ($transactionId === '') {
                throw ValidationException::withMessages([
                    'transaction_id' => 'A payment transaction id is required.',
                ]);
            }

            return;
        }

        throw ValidationException::withMessages([
            'payment_provider' => 'This payment could not be verified.',
        ]);
    }
}
