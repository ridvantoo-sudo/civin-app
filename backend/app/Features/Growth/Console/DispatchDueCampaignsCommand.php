<?php

namespace App\Features\Growth\Console;

use App\Features\Growth\Services\CampaignService;
use Illuminate\Console\Command;

final class DispatchDueCampaignsCommand extends Command
{
    protected $signature = 'growth:dispatch-campaigns';

    protected $description = 'Dispatch scheduled growth push/email campaigns that are due';

    public function handle(CampaignService $campaigns): int
    {
        $count = $campaigns->dispatchDue();
        $this->info("Dispatched {$count} campaign(s).");

        return self::SUCCESS;
    }
}
