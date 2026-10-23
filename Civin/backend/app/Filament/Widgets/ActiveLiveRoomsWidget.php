<?php

namespace App\Filament\Widgets;

use App\Features\LiveStreaming\Models\LiveRoom;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ActiveLiveRoomsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 1;

    protected function getStats(): array
    {
        $live = LiveRoom::query()->where('status', 'live')->count();
        $viewers = (int) LiveRoom::query()->where('status', 'live')->sum('viewer_count');

        return [
            Stat::make('Active Live Rooms', (string) $live)
                ->description($viewers.' current viewers')
                ->icon('heroicon-o-video-camera'),
        ];
    }
}
