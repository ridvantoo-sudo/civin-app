<?php

namespace App\Filament\Resources;

use App\Features\Admin\Support\AdminPermission;
use App\Features\Games\Models\RedEnvelope;
use App\Filament\Concerns\AuthorizesAdminAccess;
use App\Filament\Resources\RedEnvelopeLogResource\Pages;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RedEnvelopeLogResource extends Resource
{
    use AuthorizesAdminAccess;

    protected static ?string $model = RedEnvelope::class;

    protected static ?string $navigationIcon = 'heroicon-o-queue-list';

    protected static ?string $navigationGroup = 'Games';

    protected static ?int $navigationSort = 31;

    protected static ?string $navigationLabel = 'Envelope Logs';

    protected static ?string $modelLabel = 'Red Envelope';

    public static function canViewAny(): bool
    {
        return static::canAccessWithPermission(AdminPermission::MANAGE_GAMES);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return static::canAccessWithPermission(AdminPermission::MANAGE_GAMES);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('sender.username')->label('Creator')->searchable(),
                Tables\Columns\TextColumn::make('reward_type')->badge(),
                Tables\Columns\TextColumn::make('distribution_type')->label('Distribution'),
                Tables\Columns\TextColumn::make('total_amount')->label('Total'),
                Tables\Columns\TextColumn::make('remaining_amount')->label('Remaining'),
                Tables\Columns\TextColumn::make('winner_count')->label('Winners'),
                Tables\Columns\TextColumn::make('remaining_count')->label('Slots left'),
                Tables\Columns\TextColumn::make('claims_count')->counts('claims')->label('Claims'),
                Tables\Columns\TextColumn::make('visibility'),
                Tables\Columns\TextColumn::make('claim_scope')->label('Scope'),
                Tables\Columns\TextColumn::make('room_type')->label('Room')->toggleable(),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('expires_at')->dateTime()->toggleable(),
                Tables\Columns\TextColumn::make('idempotency_key')
                    ->limit(12)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        RedEnvelope::STATUS_OPEN => 'Open',
                        RedEnvelope::STATUS_EMPTY => 'Fully claimed',
                        RedEnvelope::STATUS_EXPIRED => 'Expired',
                    ]),
                Tables\Filters\SelectFilter::make('reward_type')
                    ->options([
                        RedEnvelope::REWARD_COINS => 'Coins',
                        RedEnvelope::REWARD_DIAMONDS => 'Diamonds',
                    ]),
                Tables\Filters\SelectFilter::make('distribution_type')
                    ->options([
                        RedEnvelope::DIST_RANDOM => 'Random',
                        RedEnvelope::DIST_FIXED => 'Fixed',
                    ]),
                Tables\Filters\SelectFilter::make('claim_scope')
                    ->options(array_combine(
                        RedEnvelope::CLAIM_SCOPES,
                        ['Everyone', 'Followers', 'VIP', 'Family', 'Agency', 'Whitelist'],
                    )),
                Tables\Filters\SelectFilter::make('room_type')
                    ->options(array_combine(RedEnvelope::ROOM_TYPES, ['Live', 'Voice', 'Chat', 'Family'])),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['sender', 'config']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRedEnvelopeLogs::route('/'),
        ];
    }
}
