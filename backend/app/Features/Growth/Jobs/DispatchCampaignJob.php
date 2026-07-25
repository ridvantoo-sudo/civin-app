<?php

namespace App\Features\Growth\Jobs;

use App\Features\Growth\Models\GrowthCampaign;
use App\Features\Growth\Services\CampaignService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class DispatchCampaignJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public string $campaignId) {}

    public function handle(CampaignService $campaigns): void
    {
        $campaign = GrowthCampaign::query()->find($this->campaignId);
        if ($campaign === null) {
            return;
        }

        $campaigns->process($campaign);
    }
}
