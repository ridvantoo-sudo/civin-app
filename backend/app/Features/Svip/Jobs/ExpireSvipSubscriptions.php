<?php

namespace App\Features\Svip\Jobs;

use App\Features\Svip\Services\SvipService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class ExpireSvipSubscriptions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(SvipService $svips): void
    {
        $svips->expireDueSubscriptions();
    }
}
