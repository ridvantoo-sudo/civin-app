<?php

namespace App\Filament\Resources;

use App\Features\Admin\Support\AdminPermission;
use App\Features\Games\Models\RedEnvelope;
use App\Features\Games\Models\RedEnvelopeClaim;
use App\Filament\Concerns\AuthorizesAdminAccess;
use App\Filament\Resources\RedEnvelopeClaimResource\Pages;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RedEnvelopeClaimResource extends Resource
{
    use AuthorizesAdminAccess;

    protected static ?string $model = RedEnvelopeClaim::class;

    protected static ?string $navigationIcon = 'heroicon-o-inbox-arrow-down';

    protected static ?string $navigationGroup = 'Games';

    protected static ?int $navigationSort = 32;

    protected static ?string $navigationLabel = 'Envelope Claims';

    protected static ?string $modelLabel = 'Red Envelope Claim';

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
                Tables\Columns\TextColumn::make('claimed_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('user.username')->label('Claimer')->searchable(),
                Tables\Columns\TextColumn::make('envelope.sender.username')->label('Creator')->searchable(),
                Tables\Columns\TextColumn::make('reward_type')->badge(),
                Tables\Columns\TextColumn::make('reward_amount')->label('Reward'),
                Tables\Columns\TextColumn::make('envelope.distribution_type')->label('Distribution'),
                Tables\Columns\TextColumn::make('envelope.total_amount')->label('Envelope total'),
                Tables\Columns\TextColumn::make('envelope_id')->limit(12)->label('Envelope'),
                Tables\Columns\TextColumn::make('idempotency_key')
                    ->limit(12)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('claimed_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('reward_type')
                    ->options([
                        RedEnvelope::REWARD_COINS => 'Coins',
                        RedEnvelope::REWARD_DIAMONDS => 'Diamonds',
                    ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['user', 'envelope.sender']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRedEnvelopeClaims::route('/'),
        ];
    }
}
