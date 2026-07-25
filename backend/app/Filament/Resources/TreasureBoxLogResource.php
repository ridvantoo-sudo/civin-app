<?php

namespace App\Filament\Resources;

use App\Features\Admin\Support\AdminPermission;
use App\Features\Games\Models\TreasureBox;
use App\Filament\Concerns\AuthorizesAdminAccess;
use App\Filament\Resources\TreasureBoxLogResource\Pages;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TreasureBoxLogResource extends Resource
{
    use AuthorizesAdminAccess;

    protected static ?string $model = TreasureBox::class;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';

    protected static ?string $navigationGroup = 'Games';

    protected static ?int $navigationSort = 34;

    protected static ?string $navigationLabel = 'Treasure Box Manager';

    protected static ?string $modelLabel = 'Treasure Box';

    protected static ?string $pluralModelLabel = 'Treasure Boxes / Schedule';

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
                Tables\Columns\TextColumn::make('creator.username')->label('Creator')->searchable(),
                Tables\Columns\TextColumn::make('type')->badge(),
                Tables\Columns\TextColumn::make('reward_type')->badge(),
                Tables\Columns\TextColumn::make('total_amount')->label('Total'),
                Tables\Columns\TextColumn::make('remaining_amount')->label('Remaining'),
                Tables\Columns\TextColumn::make('winner_count')->label('Winners'),
                Tables\Columns\TextColumn::make('participant_count')->label('Participants'),
                Tables\Columns\TextColumn::make('visibility'),
                Tables\Columns\TextColumn::make('eligibility_scope')->label('Eligibility'),
                Tables\Columns\TextColumn::make('room_type')->label('Room')->toggleable(),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('draw_at')->dateTime()->toggleable(),
                Tables\Columns\TextColumn::make('expires_at')->dateTime()->toggleable(),
                Tables\Columns\TextColumn::make('idempotency_key')
                    ->limit(12)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        TreasureBox::STATUS_SCHEDULED => 'Scheduled',
                        TreasureBox::STATUS_OPEN => 'Open',
                        TreasureBox::STATUS_COUNTDOWN => 'Countdown',
                        TreasureBox::STATUS_OPENED => 'Opened',
                        TreasureBox::STATUS_FINISHED => 'Finished',
                        TreasureBox::STATUS_EXPIRED => 'Expired',
                    ]),
                Tables\Filters\SelectFilter::make('reward_type')
                    ->options(array_combine(
                        TreasureBox::REWARD_TYPES,
                        ['Coins', 'Diamonds', 'Gift', 'Frame', 'Entry effect', 'VIP days', 'SVIP days', 'Mystery', 'Jackpot'],
                    )),
                Tables\Filters\SelectFilter::make('type')
                    ->options(array_combine(
                        TreasureBox::TYPES,
                        ['Instant', 'Scheduled', 'Auto', 'Live room', 'Voice room'],
                    )),
                Tables\Filters\SelectFilter::make('eligibility_scope')
                    ->options(array_combine(
                        TreasureBox::ELIGIBILITY_SCOPES,
                        ['Everyone', 'Followers', 'VIP', 'SVIP', 'Family', 'Agency', 'Min watch time', 'Min user level', 'Min gift value', 'Whitelist'],
                    )),
                Tables\Filters\SelectFilter::make('room_type')
                    ->options(array_combine(TreasureBox::ROOM_TYPES, ['Live', 'Voice', 'Chat', 'Family'])),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['creator', 'config']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTreasureBoxLogs::route('/'),
        ];
    }
}
