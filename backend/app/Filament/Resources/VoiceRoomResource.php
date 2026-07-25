<?php

namespace App\Filament\Resources;

use App\Features\Admin\Actions\TerminateVoiceRoom;
use App\Features\Admin\Support\AdminPermission;
use App\Features\VoiceRoom\Models\VoiceRoom;
use App\Filament\Concerns\AuthorizesAdminAccess;
use App\Filament\Resources\VoiceRoomResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class VoiceRoomResource extends Resource
{
    use AuthorizesAdminAccess;

    protected static ?string $model = VoiceRoom::class;

    protected static ?string $navigationIcon = 'heroicon-o-microphone';

    protected static ?string $navigationGroup = 'Live';

    protected static ?int $navigationSort = 6;

    protected static ?string $navigationLabel = 'Voice Rooms';

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
        return static::canAccessWithPermission(AdminPermission::MANAGE_LIVE_ROOMS);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('title')->required()->maxLength(150),
                Forms\Components\Textarea::make('description')->rows(3)->columnSpanFull(),
                Forms\Components\TextInput::make('thumbnail')->url()->maxLength(2048),
                Forms\Components\Select::make('status')
                    ->options([
                        VoiceRoom::STATUS_LIVE => 'Live',
                        VoiceRoom::STATUS_ENDED => 'Ended',
                    ])
                    ->disabled(),
                Forms\Components\TextInput::make('seat_count')->numeric()->disabled(),
                Forms\Components\TextInput::make('participant_count')->numeric()->disabled(),
                Forms\Components\DateTimePicker::make('started_at')->disabled(),
                Forms\Components\DateTimePicker::make('ended_at')->disabled(),
            ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('host.username')->label('Host')->searchable(),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->color(fn (string $state): string => $state === VoiceRoom::STATUS_LIVE ? 'success' : 'gray'),
                Tables\Columns\TextColumn::make('participant_count')->numeric()->sortable()->label('Participants'),
                Tables\Columns\TextColumn::make('seat_count')->numeric()->label('Seats'),
                Tables\Columns\TextColumn::make('started_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('ended_at')->dateTime()->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        VoiceRoom::STATUS_LIVE => 'Live',
                        VoiceRoom::STATUS_ENDED => 'Ended',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('terminate')
                    ->label('Terminate')
                    ->icon('heroicon-o-stop-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (VoiceRoom $record): bool => $record->status === VoiceRoom::STATUS_LIVE)
                    ->action(function (VoiceRoom $record): void {
                        app(TerminateVoiceRoom::class)->execute(auth()->user(), $record);
                        Notification::make()->title('Voice room terminated')->success()->send();
                    }),
            ])
            ->bulkActions([])
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('host'));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVoiceRooms::route('/'),
            'edit' => Pages\EditVoiceRoom::route('/{record}/edit'),
        ];
    }
}
