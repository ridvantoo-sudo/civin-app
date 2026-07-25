<?php

namespace App\Filament\Resources;

use App\Features\Admin\Support\AdminPermission;
use App\Features\Games\Plugins\LuckyDice\Models\LuckyDiceBet;
use App\Filament\Concerns\AuthorizesAdminAccess;
use App\Filament\Resources\LuckyDiceBetResource\Pages;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LuckyDiceBetResource extends Resource
{
    use AuthorizesAdminAccess;

    protected static ?string $model = LuckyDiceBet::class;

    protected static ?string $navigationIcon = 'heroicon-o-queue-list';

    protected static ?string $navigationGroup = 'Games';

    protected static ?int $navigationSort = 21;

    protected static ?string $navigationLabel = 'Lucky Dice Bets';

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
                Tables\Columns\TextColumn::make('bet_mode')->label('Mode'),
                Tables\Columns\TextColumn::make('prediction'),
                Tables\Columns\TextColumn::make('bet_amount')->label('Bet'),
                Tables\Columns\TextColumn::make('result.dice_1')->label('D1'),
                Tables\Columns\TextColumn::make('result.dice_2')->label('D2'),
                Tables\Columns\TextColumn::make('result.dice_3')->label('D3'),
                Tables\Columns\TextColumn::make('result.total')->label('Total'),
                Tables\Columns\IconColumn::make('is_win')->boolean(),
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
                        LuckyDiceBet::STATUS_SETTLED => 'Settled',
                        LuckyDiceBet::STATUS_FULFILLMENT_PENDING => 'Fulfillment pending',
                        LuckyDiceBet::STATUS_PENDING => 'Pending',
                        LuckyDiceBet::STATUS_FAILED => 'Failed',
                    ]),
                Tables\Filters\SelectFilter::make('bet_mode')
                    ->options([
                        LuckyDiceBet::MODE_BIG_SMALL => 'Big / Small',
                        LuckyDiceBet::MODE_ODD_EVEN => 'Odd / Even',
                        LuckyDiceBet::MODE_EXACT_TOTAL => 'Exact Total',
                        LuckyDiceBet::MODE_TRIPLE_MATCH => 'Triple Match',
                    ]),
                Tables\Filters\TernaryFilter::make('is_jackpot'),
                Tables\Filters\TernaryFilter::make('is_win'),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['result', 'user']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLuckyDiceBets::route('/'),
        ];
    }
}
