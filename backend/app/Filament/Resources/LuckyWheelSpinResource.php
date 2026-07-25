<?php

namespace App\Filament\Resources;

use App\Features\Admin\Support\AdminPermission;
use App\Features\Games\Plugins\LuckyWheel\Models\LuckyWheelSpin;
use App\Filament\Concerns\AuthorizesAdminAccess;
use App\Filament\Resources\LuckyWheelSpinResource\Pages;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class LuckyWheelSpinResource extends Resource
{
    use AuthorizesAdminAccess;

    protected static ?string $model = LuckyWheelSpin::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path';

    protected static ?string $navigationGroup = 'Games';

    protected static ?int $navigationSort = 12;

    protected static ?string $navigationLabel = 'Lucky Wheel Spins';

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
                Tables\Columns\TextColumn::make('user.username')->label('User')->searchable(),
                Tables\Columns\TextColumn::make('cost_coins')->label('Cost'),
                Tables\Columns\TextColumn::make('sector_label')->label('Sector'),
                Tables\Columns\TextColumn::make('reward_type'),
                Tables\Columns\TextColumn::make('reward_amount'),
                Tables\Columns\IconColumn::make('is_jackpot')->boolean(),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('idempotency_key')->limit(12)->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        LuckyWheelSpin::STATUS_SETTLED => 'Settled',
                        LuckyWheelSpin::STATUS_FULFILLMENT_PENDING => 'Fulfillment pending',
                        LuckyWheelSpin::STATUS_PENDING => 'Pending',
                        LuckyWheelSpin::STATUS_FAILED => 'Failed',
                    ]),
                Tables\Filters\TernaryFilter::make('is_jackpot'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLuckyWheelSpins::route('/'),
        ];
    }
}
