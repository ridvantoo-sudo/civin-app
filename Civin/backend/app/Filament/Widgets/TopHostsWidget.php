<?php

namespace App\Filament\Widgets;

use App\Features\Gifts\Models\GiftTransaction;
use App\Features\Users\Models\User;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class TopHostsWidget extends BaseWidget
{
    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Top Hosts';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                User::query()
                    ->select('users.*')
                    ->selectSub(
                        GiftTransaction::query()
                            ->selectRaw('coalesce(sum(coins), 0)')
                            ->whereColumn('receiver_id', 'users.id'),
                        'gift_coins_received',
                    )
                    ->orderByDesc('gift_coins_received')
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('username')->searchable(),
                Tables\Columns\TextColumn::make('email')->toggleable(),
                Tables\Columns\TextColumn::make('gift_coins_received')
                    ->label('Coins received')
                    ->numeric()
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('gift_coins_received', $direction)),
            ])
            ->paginated(false);
    }
}
