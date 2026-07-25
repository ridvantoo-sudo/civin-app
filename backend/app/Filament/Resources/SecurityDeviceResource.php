<?php

namespace App\Filament\Resources;

use App\Features\Security\Models\SecurityDevice;
use App\Features\Security\Services\SecurityDeviceService;
use App\Features\Security\Support\SecurityPermission;
use App\Filament\Concerns\AuthorizesSecurityAccess;
use App\Filament\Resources\SecurityDeviceResource\Pages;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Device inventory behind the Device Dashboard. Only hashes and attestation
 * flags are stored, never raw hardware identifiers.
 */
class SecurityDeviceResource extends Resource
{
    use AuthorizesSecurityAccess;

    protected static ?string $model = SecurityDevice::class;

    protected static ?string $navigationIcon = 'heroicon-o-device-tablet';

    protected static ?string $navigationGroup = 'Security';

    protected static ?string $navigationLabel = 'Devices';

    protected static ?string $modelLabel = 'security device';

    protected static ?string $slug = 'security-devices';

    protected static ?int $navigationSort = 11;

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
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('user'))
            ->defaultSort('risk_score', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('user.username')->label('Account')->placeholder('—')->searchable(),
                Tables\Columns\TextColumn::make('platform')->badge()->color('gray'),
                Tables\Columns\TextColumn::make('model')->placeholder('—')->searchable(),
                Tables\Columns\TextColumn::make('os_version')->label('OS')->placeholder('—'),
                Tables\Columns\TextColumn::make('app_version')->label('App')->placeholder('—'),
                Tables\Columns\IconColumn::make('is_emulator')->label('Emu')->boolean(),
                Tables\Columns\IconColumn::make('is_rooted')->label('Root')->boolean(),
                Tables\Columns\IconColumn::make('is_tampered')->label('Tampered')->boolean(),
                Tables\Columns\IconColumn::make('is_cloned')->label('Cloned')->boolean(),
                Tables\Columns\TextColumn::make('linked_account_count')->label('Accounts')->sortable(),
                Tables\Columns\TextColumn::make('risk_score')->numeric(1)->sortable(),
                Tables\Columns\IconColumn::make('is_trusted')->label('Trusted')->boolean(),
                Tables\Columns\IconColumn::make('is_blocked')->label('Blocked')->boolean(),
                Tables\Columns\TextColumn::make('last_seen_at')->since()->label('Last seen'),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_emulator')->label('Emulator'),
                Tables\Filters\TernaryFilter::make('is_cloned')->label('Cloned'),
                Tables\Filters\TernaryFilter::make('is_blocked')->label('Blocked'),
                Tables\Filters\TernaryFilter::make('is_trusted')->label('Trusted'),
            ])
            ->actions([
                Tables\Actions\Action::make('trust')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (SecurityDevice $record): bool => ! $record->is_trusted
                        && static::allowsSecurity(SecurityPermission::MANAGE_SECURITY_BANS))
                    ->action(fn (SecurityDevice $record) => app(SecurityDeviceService::class)
                        ->trust($record, static::actorId())),
                Tables\Actions\Action::make('block')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->form([Forms\Components\Textarea::make('reason')->required()->maxLength(500)])
                    ->visible(fn (SecurityDevice $record): bool => ! $record->is_blocked
                        && static::allowsSecurity(SecurityPermission::MANAGE_SECURITY_BANS))
                    ->action(fn (SecurityDevice $record, array $data) => app(SecurityDeviceService::class)
                        ->block($record, static::actorId(), (string) ($data['reason'] ?? ''))),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSecurityDevices::route('/'),
        ];
    }

    private static function actorId(): ?string
    {
        $id = Auth::id();

        return $id === null ? null : (string) $id;
    }
}
