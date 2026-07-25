<?php

namespace App\Filament\Widgets\Security;

use App\Features\Security\Models\SecurityAlert;
use App\Filament\Widgets\Security\Concerns\SecurityWidget;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * The live feed behind the threat monitor. Polls rather than subscribes so the
 * panel stays useful even when the WebSocket layer is unavailable.
 */
class ThreatFeedWidget extends TableWidget
{
    use SecurityWidget;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Live threat feed';

    protected static ?string $pollingInterval = '10s';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => SecurityAlert::query()->with('user')->latest('created_at'))
            ->defaultPaginationPageOption(10)
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('When')
                    ->since()
                    ->sortable(),
                Tables\Columns\TextColumn::make('severity')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'critical' => 'danger',
                        'high' => 'warning',
                        'medium' => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('title')
                    ->label('Detection')
                    ->wrap()
                    ->searchable(),
                Tables\Columns\TextColumn::make('surface')->badge()->color('gray'),
                Tables\Columns\TextColumn::make('action')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'ban', 'suspend' => 'danger',
                        'throttle', 'review' => 'warning',
                        'warn' => 'info',
                        default => 'success',
                    }),
                Tables\Columns\TextColumn::make('confidence')
                    ->numeric(1)
                    ->suffix('%')
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.username')
                    ->label('Account')
                    ->placeholder('—')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')->badge(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('severity')->options([
                    'critical' => 'Critical',
                    'high' => 'High',
                    'medium' => 'Medium',
                    'low' => 'Low',
                    'info' => 'Info',
                ]),
                Tables\Filters\SelectFilter::make('status')->options([
                    SecurityAlert::STATUS_OPEN => 'Open',
                    SecurityAlert::STATUS_ACKNOWLEDGED => 'Acknowledged',
                    SecurityAlert::STATUS_RESOLVED => 'Resolved',
                    SecurityAlert::STATUS_DISMISSED => 'Dismissed',
                ]),
            ]);
    }
}
