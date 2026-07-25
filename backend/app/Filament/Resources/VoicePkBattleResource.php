<?php

namespace App\Filament\Resources;

use App\Features\Admin\Actions\EndVoicePkBattle;
use App\Features\Admin\Support\AdminPermission;
use App\Features\VoicePkBattle\Models\VoicePkBattle;
use App\Filament\Concerns\AuthorizesAdminAccess;
use App\Filament\Resources\VoicePkBattleResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class VoicePkBattleResource extends Resource
{
    use AuthorizesAdminAccess;

    protected static ?string $model = VoicePkBattle::class;

    protected static ?string $navigationIcon = 'heroicon-o-speaker-wave';

    protected static ?string $navigationGroup = 'Live';

    protected static ?int $navigationSort = 7;

    protected static ?string $navigationLabel = 'Voice PK';

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
        return false;
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
                Forms\Components\TextInput::make('initiator_id')->label('Initiator ID')->disabled(),
                Forms\Components\TextInput::make('winner_room_id')->label('Winner room ID')->disabled(),
                Forms\Components\Toggle::make('is_draw')->disabled(),
                Forms\Components\DateTimePicker::make('started_at')->disabled(),
                Forms\Components\DateTimePicker::make('ends_at')->disabled(),
                Forms\Components\DateTimePicker::make('ended_at')->disabled(),
            ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('status')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        VoicePkBattle::STATUS_RUNNING => 'success',
                        VoicePkBattle::STATUS_WAITING => 'warning',
                        VoicePkBattle::STATUS_FINISHED => 'gray',
                        default => 'danger',
                    }),
                Tables\Columns\TextColumn::make('initiator.username')->label('Initiator')->searchable(),
                Tables\Columns\TextColumn::make('teams_count')->counts('teams')->label('Teams'),
                Tables\Columns\IconColumn::make('is_draw')->boolean()->label('Draw'),
                Tables\Columns\TextColumn::make('winnerRoom.title')->label('Winner room')->limit(24),
                Tables\Columns\TextColumn::make('duration_seconds')->numeric()->label('Duration'),
                Tables\Columns\TextColumn::make('started_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('ended_at')->dateTime()->toggleable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    VoicePkBattle::STATUS_WAITING => 'WAITING',
                    VoicePkBattle::STATUS_RUNNING => 'RUNNING',
                    VoicePkBattle::STATUS_FINISHED => 'FINISHED',
                    VoicePkBattle::STATUS_CANCELLED => 'CANCELLED',
                ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('force_end')
                    ->label('Force end')
                    ->icon('heroicon-o-stop-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (VoicePkBattle $record): bool => in_array($record->status, VoicePkBattle::ACTIVE_STATUSES, true))
                    ->action(function (VoicePkBattle $record): void {
                        app(EndVoicePkBattle::class)->execute(auth()->user(), $record);
                        Notification::make()->title('Voice PK closed')->success()->send();
                    }),
            ])
            ->bulkActions([])
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['initiator', 'winnerRoom']));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVoicePkBattles::route('/'),
            'view' => Pages\ViewVoicePkBattle::route('/{record}'),
        ];
    }
}
