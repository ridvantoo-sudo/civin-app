<?php

namespace App\Filament\Resources;

use App\Features\Admin\Support\AdminPermission;
use App\Features\Games\Plugins\SlotMachine\Models\SlotMachineSpin;
use App\Filament\Concerns\AuthorizesAdminAccess;
use App\Filament\Resources\SlotMachineSpinResource\Pages;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SlotMachineSpinResource extends Resource
{
    use AuthorizesAdminAccess;

    protected static ?string $model = SlotMachineSpin::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Games';

    protected static ?int $navigationSort = 24;

    protected static ?string $navigationLabel = 'Slot Logs / Dashboard';

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
                Tables\Columns\TextColumn::make('id')->limit(8)->label('Spin'),
                Tables\Columns\TextColumn::make('user.username')->searchable()->label('User'),
                Tables\Columns\TextColumn::make('game_mode')->badge(),
                Tables\Columns\TextColumn::make('cost_coins')->label('Bet'),
                Tables\Columns\TextColumn::make('reward_coins')->label('Won'),
                Tables\Columns\IconColumn::make('is_win')->boolean(),
                Tables\Columns\IconColumn::make('is_jackpot')->boolean(),
                Tables\Columns\IconColumn::make('is_bonus')->boolean(),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_jackpot'),
                Tables\Filters\TernaryFilter::make('is_bonus'),
                Tables\Filters\SelectFilter::make('status')->options([
                    SlotMachineSpin::STATUS_SETTLED => 'Settled',
                    SlotMachineSpin::STATUS_PENDING => 'Pending',
                    SlotMachineSpin::STATUS_FAILED => 'Failed',
                    SlotMachineSpin::STATUS_REFUNDED => 'Refunded',
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSlotMachineSpins::route('/'),
        ];
    }
}
