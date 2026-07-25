<?php

namespace App\Filament\Resources;

use App\Features\Admin\Actions\ModerateLiveMessage;
use App\Features\Admin\Support\AdminPermission;
use App\Features\LiveChat\Models\LiveMessage;
use App\Filament\Concerns\AuthorizesAdminAccess;
use App\Filament\Resources\LiveMessageResource\Pages;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LiveMessageResource extends Resource
{
    use AuthorizesAdminAccess;

    protected static ?string $model = LiveMessage::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationGroup = 'Live';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Live Chat';

    public static function canViewAny(): bool
    {
        return static::canAccessWithPermission(AdminPermission::MODERATE_CHAT);
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
        return static::canAccessWithPermission(AdminPermission::MODERATE_CHAT);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('room.title')->label('Room')->searchable()->limit(30),
                Tables\Columns\TextColumn::make('user.username')->label('User')->searchable(),
                Tables\Columns\TextColumn::make('type')->badge(),
                Tables\Columns\TextColumn::make('message')->limit(60)->searchable(),
                Tables\Columns\TextColumn::make('deleted_at')->dateTime()->label('Deleted')->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options(array_combine(LiveMessage::TYPES, LiveMessage::TYPES)),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\Action::make('delete_message')
                    ->label('Remove')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (LiveMessage $record): bool => $record->deleted_at === null
                        && $record->type === LiveMessage::TYPE_TEXT)
                    ->action(function (LiveMessage $record): void {
                        app(ModerateLiveMessage::class)->execute(auth()->user(), $record);
                        Notification::make()->title('Message removed')->success()->send();
                    }),
            ])
            ->bulkActions([])
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['room', 'user']));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLiveMessages::route('/'),
        ];
    }
}
