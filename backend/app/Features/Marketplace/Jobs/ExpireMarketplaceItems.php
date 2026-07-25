<?php

namespace App\Features\Marketplace\Jobs;

use App\Features\Marketplace\Services\MarketplaceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class ExpireMarketplaceItems implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(MarketplaceService $marketplace): void
    {
        $marketplace->expireDueItems();
    }
}
