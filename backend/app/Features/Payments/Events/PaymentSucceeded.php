<?php

namespace App\Features\Payments\Events;

use App\Features\Payments\Models\PaymentTransaction;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class PaymentSucceeded
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public PaymentTransaction $transaction) {}
}
