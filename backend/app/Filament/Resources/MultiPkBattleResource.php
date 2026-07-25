<?php

namespace App\Filament\Resources;

use App\Features\Admin\Actions\EndMultiPkBattle;
use App\Features\Admin\Support\AdminPermission;
use App\Features\MultiPkBattle\Models\MultiPkBattle;
use App\Filament\Concerns\AuthorizesAdminAccess;
use App\Filament\Resources\MultiPkBattleResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MultiPkBattleResource extends Resource
{
    use AuthorizesAdminAccess;

    protected static ?string $model = MultiPkBattle::class;

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationGroup = 'Live';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Multi PK';

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
                Forms\Components\TextInput::make('mode')->disabled(),
                Forms\Components\TextInput::make('status')->disabled(),
                Forms\Components\TextInput::make('duration_seconds')->numeric()->disabled(),
                Forms\Components\TextInput::make('initiator_id')->label('Initiator ID')->disabled(),
                Forms\Components\TextInput::make('winner_id')->label('Winner ID')->disabled(),
                Forms\Components\TextInput::make('winning_team')->numeric()->disabled(),
                Forms\Components\DateTimePicker::make('started_at')->disabled(),
                Forms\Components\DateTimePicker::make('ended_at')->disabled(),
            ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('status')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        MultiPkBattle::STATUS_RUNNING => 'success',
                        MultiPkBattle::STATUS_WAITING => 'warning',
                        MultiPkBattle::STATUS_FINISHED => 'gray',
                        default => 'danger',
                    }),
                Tables\Columns\TextColumn::make('mode')->badge(),
                Tables\Columns\TextColumn::make('initiator.username')->label('Initiator')->searchable(),
                Tables\Columns\TextColumn::make('participants_count')->counts('participants')->label('Players'),
                Tables\Columns\TextColumn::make('winner.username')->label('Winner'),
                Tables\Columns\TextColumn::make('winning_team')->label('Team'),
                Tables\Columns\TextColumn::make('duration_seconds')->numeric()->label('Duration'),
                Tables\Columns\TextColumn::make('started_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('ended_at')->dateTime()->toggleable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    MultiPkBattle::STATUS_WAITING => 'WAITING',
                    MultiPkBattle::STATUS_RUNNING => 'RUNNING',
                    MultiPkBattle::STATUS_FINISHED => 'FINISHED',
                    MultiPkBattle::STATUS_CANCELLED => 'CANCELLED',
                ]),
                Tables\Filters\SelectFilter::make('mode')->options([
                    MultiPkBattle::MODE_TEAM_2V2 => 'TEAM_2V2',
                    MultiPkBattle::MODE_FFA_4 => 'FFA_4',
                ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('force_end')
                    ->label('Force end')
                    ->icon('heroicon-o-stop-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (MultiPkBattle $record): bool => in_array($record->status, [
                        MultiPkBattle::STATUS_WAITING,
                        MultiPkBattle::STATUS_RUNNING,
                    ], true))
                    ->action(function (MultiPkBattle $record): void {
                        app(EndMultiPkBattle::class)->execute(auth()->user(), $record);
                        Notification::make()->title('Multi PK closed')->success()->send();
                    }),
            ])
            ->bulkActions([])
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['initiator', 'winner']));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMultiPkBattles::route('/'),
            'view' => Pages\ViewMultiPkBattle::route('/{record}'),
        ];
    }
}
