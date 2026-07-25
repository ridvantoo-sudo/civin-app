<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class DevicesRelationManager extends RelationManager
{
    protected static string $relationship = 'devices';

    protected static ?string $title = 'Devices';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('device_uuid')->disabled(),
                Forms\Components\TextInput::make('platform')->disabled(),
                Forms\Components\TextInput::make('name')->disabled(),
                Forms\Components\TextInput::make('app_version')->disabled(),
                Forms\Components\TextInput::make('os_version')->disabled(),
                Forms\Components\TextInput::make('ip_address')->disabled(),
                Forms\Components\DateTimePicker::make('last_seen_at')->disabled(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('device_uuid')
            ->columns([
                Tables\Columns\TextColumn::make('platform')->badge(),
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('device_uuid')->limit(12)->toggleable(),
                Tables\Columns\TextColumn::make('app_version'),
                Tables\Columns\TextColumn::make('ip_address')->toggleable(),
                Tables\Columns\TextColumn::make('last_seen_at')->dateTime()->sortable(),
            ])
            ->headerActions([])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('revokeSessions')
                    ->label('Revoke sessions')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function ($record): void {
                        $record->refreshTokens()->delete();
                        Notification::make()->title('Device sessions revoked')->success()->send();
                    }),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
