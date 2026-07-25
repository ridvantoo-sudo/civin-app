<?php

namespace App\Features\CreatorTools\Jobs;

use App\Features\CreatorTools\Models\StreamClip;
use App\Features\CreatorTools\Services\StreamClipService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class ProcessStreamClipJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public string $clipId) {}

    public function handle(StreamClipService $clips): void
    {
        $clip = StreamClip::query()->find($this->clipId);
        if ($clip === null) {
            return;
        }

        // Stub encoder: in production this would call a transcoder / Agora Cloud Recording cut.
        $clips->markReady($clip);
    }
}
