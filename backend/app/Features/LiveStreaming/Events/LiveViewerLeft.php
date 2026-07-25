<?php

namespace App\Features\LiveStreaming\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class LiveViewerLeft
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $roomId,
        public readonly string $viewerId,
        public readonly int $watchSeconds,
        public readonly string $joinedAtKey,
    ) {}
}
