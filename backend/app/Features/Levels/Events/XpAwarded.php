<?php

namespace App\Features\Levels\Events;

use App\Features\Levels\Models\XpTransaction;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class XpAwarded
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly XpTransaction $transaction) {}
}
