<?php

namespace App\Filament\Resources;

use App\Features\Admin\Actions\EndPkBattle;
use App\Features\Admin\Support\AdminPermission;
use App\Features\PkBattle\Models\PkBattle;
use App\Filament\Concerns\AuthorizesAdminAccess;
use App\Filament\Resources\PkBattleResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PkBattleResource extends Resource
{
    use AuthorizesAdminAccess;

    protected static ?string $model = PkBattle::class;

    protected static ?string $navigationIcon = 'heroicon-o-fire';

    protected static ?string $navigationGroup = 'Live';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'PK Battles';

    public static function canViewAny(): bool
    {
        return static::canAccessWithPermission(AdminPermission::MANAGE_LIVE_ROOMS);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return static::canAccessWithPermission(AdminPermission::MANAGE_LIVE_ROOMS);
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('status')->disabled(),
                Forms\Components\TextInput::make('duration_seconds')->numeric()->disabled(),
                Forms\Components\TextInput::make('host_a_id')->label('Host A ID')->disabled(),
                Forms\Components\TextInput::make('host_b_id')->label('Host B ID')->disabled(),
                Forms\Components\TextInput::make('winner_id')->label('Winner ID')->disabled(),
                Forms\Components\TextInput::make('room_a_id')->label('Room A ID')->disabled(),
                Forms\Components\TextInput::make('room_b_id')->label('Room B ID')->disabled(),
                Forms\Components\DateTimePicker::make('started_at')->disabled(),
                Forms\Components\DateTimePicker::make('ended_at')->disabled(),
                Forms\Components\DateTimePicker::make('created_at')->disabled(),
            ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('status')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        PkBattle::STATUS_RUNNING => 'success',
                        PkBattle::STATUS_WAITING => 'warning',
                        PkBattle::STATUS_FINISHED => 'gray',
                        default => 'danger',
                    }),
                Tables\Columns\TextColumn::make('hostA.username')->label('Host A')->searchable(),
                Tables\Columns\TextColumn::make('hostB.username')->label('Host B')->searchable(),
                Tables\Columns\TextColumn::make('roomA.title')->label('Room A')->limit(24),
                Tables\Columns\TextColumn::make('roomB.title')->label('Room B')->limit(24),
                Tables\Columns\TextColumn::make('winner.username')->label('Winner'),
                Tables\Columns\TextColumn::make('duration_seconds')->label('Duration')->numeric(),
                Tables\Columns\TextColumn::make('started_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('ended_at')->dateTime()->toggleable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(array_combine(PkBattle::STATUSES, PkBattle::STATUSES)),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('force_end')
                    ->label('Force end')
                    ->icon('heroicon-o-stop-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (PkBattle $record): bool => in_array($record->status, PkBattle::ACTIVE_STATUSES, true))
                    ->action(function (PkBattle $record): void {
                        app(EndPkBattle::class)->execute(auth()->user(), $record);
                        Notification::make()->title('PK battle closed')->success()->send();
                    }),
            ])
            ->bulkActions([])
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'hostA', 'hostB', 'winner', 'roomA', 'roomB',
            ]));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPkBattles::route('/'),
            'view' => Pages\ViewPkBattle::route('/{record}'),
        ];
    }
}
