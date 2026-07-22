<?php

namespace App\Filament\Resources;

use App\Features\Admin\Actions\ModerateLiveMessage;
use App\Features\Admin\Actions\TerminateLiveRoom;
use App\Features\Admin\Support\AdminPermission;
use App\Features\LiveChat\Models\LiveMessage;
use App\Features\LiveStreaming\Models\LiveRoom;
use App\Filament\Concerns\AuthorizesAdminAccess;
use App\Filament\Resources\LiveRoomResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LiveRoomResource extends Resource
{
    use AuthorizesAdminAccess;

    protected static ?string $model = LiveRoom::class;

    protected static ?string $navigationIcon = 'heroicon-o-video-camera';

    protected static ?string $navigationGroup = 'Live';

    protected static ?int $navigationSort = 1;

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
                        'created' => 'Created',
                        'live' => 'Live',
                        'ended' => 'Ended',
                    ])
                    ->required()
                    ->disabled(),
                Forms\Components\TextInput::make('viewer_count')->numeric()->disabled(),
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
                    ->color(fn (string $state): string => match ($state) {
                        'live' => 'success',
                        'ended' => 'gray',
                        default => 'warning',
                    }),
                Tables\Columns\TextColumn::make('viewer_count')->numeric()->sortable(),
                Tables\Columns\TextColumn::make('started_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('ended_at')->dateTime()->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'created' => 'Created',
                        'live' => 'Live',
                        'ended' => 'Ended',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('terminate')
                    ->label('Terminate')
                    ->icon('heroicon-o-stop-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (LiveRoom $record): bool => $record->status === 'live')
                    ->action(function (LiveRoom $record): void {
                        app(TerminateLiveRoom::class)->execute(auth()->user(), $record);
                        Notification::make()->title('Stream terminated')->success()->send();
                    }),
                Tables\Actions\Action::make('moderate_chat')
                    ->label('Moderate chat')
                    ->icon('heroicon-o-chat-bubble-left-ellipsis')
                    ->visible(fn (): bool => auth()->user()?->can(AdminPermission::MODERATE_CHAT) ?? false)
                    ->form([
                        Forms\Components\Select::make('message_id')
                            ->label('Message')
                            ->options(fn (LiveRoom $record): array => LiveMessage::query()
                                ->where('room_id', $record->getKey())
                                ->whereNull('deleted_at')
                                ->where('type', LiveMessage::TYPE_TEXT)
                                ->latest()
                                ->limit(50)
                                ->get()
                                ->mapWithKeys(fn (LiveMessage $message): array => [
                                    $message->getKey() => sprintf('%s: %s', $message->user?->username ?? 'user', str($message->message)->limit(60)),
                                ])
                                ->all())
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (LiveRoom $record, array $data): void {
                        $message = LiveMessage::query()
                            ->where('room_id', $record->getKey())
                            ->whereKey($data['message_id'])
                            ->firstOrFail();

                        app(ModerateLiveMessage::class)->execute(auth()->user(), $message);
                        Notification::make()->title('Message removed')->success()->send();
                    }),
            ])
            ->bulkActions([])
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('host'));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLiveRooms::route('/'),
            'edit' => Pages\EditLiveRoom::route('/{record}/edit'),
        ];
    }
}
