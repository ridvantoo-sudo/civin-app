<?php

namespace App\Filament\Resources;

use App\Features\Security\Models\SecurityLog;
use App\Filament\Concerns\AuthorizesSecurityAccess;
use App\Filament\Resources\SecurityLogResource\Pages;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Security Logs — the append-only audit trail. Deliberately read-only: an
 * audit trail an administrator can edit is not an audit trail.
 */
class SecurityLogResource extends Resource
{
    use AuthorizesSecurityAccess;

    protected static ?string $model = SecurityLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Security';

    protected static ?string $navigationLabel = 'Security Logs';

    protected static ?string $modelLabel = 'security log';

    protected static ?string $slug = 'security-logs';

    protected static ?int $navigationSort = 10;

    public static function canViewAny(): bool
    {
        return static::canViewSecurity();
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

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['user', 'actor']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('channel')->badge(),
                Tables\Columns\TextColumn::make('level')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'error', 'critical' => 'danger',
                        'warning' => 'warning',
                        'notice' => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('action')->searchable(),
                Tables\Columns\TextColumn::make('message')->wrap()->limit(80)->searchable(),
                Tables\Columns\TextColumn::make('actor.username')->label('Admin')->placeholder('system'),
                Tables\Columns\TextColumn::make('user.username')->label('Account')->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('channel')->options([
                    SecurityLog::CHANNEL_ENGINE => 'Engine',
                    SecurityLog::CHANNEL_ADMIN => 'Admin',
                    SecurityLog::CHANNEL_API => 'API',
                    SecurityLog::CHANNEL_RETENTION => 'Retention',
                ]),
                Tables\Filters\SelectFilter::make('level')->options([
                    'info' => 'Info',
                    'notice' => 'Notice',
                    'warning' => 'Warning',
                    'error' => 'Error',
                ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\TextEntry::make('created_at')->dateTime(),
            Infolists\Components\TextEntry::make('channel')->badge(),
            Infolists\Components\TextEntry::make('level')->badge(),
            Infolists\Components\TextEntry::make('action'),
            Infolists\Components\TextEntry::make('message')->columnSpanFull(),
            Infolists\Components\TextEntry::make('actor.username')->label('Admin')->placeholder('system'),
            Infolists\Components\TextEntry::make('user.username')->label('Account')->placeholder('—'),
            Infolists\Components\KeyValueEntry::make('context')->columnSpanFull(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSecurityLogs::route('/'),
        ];
    }
}
