<?php

namespace App\Filament\Resources;

use App\Features\Security\Models\SecurityAlert;
use App\Features\Security\Services\SecurityAdminService;
use App\Features\Security\Support\SecurityPermission;
use App\Filament\Concerns\AuthorizesSecurityAccess;
use App\Filament\Resources\SecurityAlertResource\Pages;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Alert Center.
 */
class SecurityAlertResource extends Resource
{
    use AuthorizesSecurityAccess;

    protected static ?string $model = SecurityAlert::class;

    protected static ?string $navigationIcon = 'heroicon-o-bell-alert';

    protected static ?string $navigationGroup = 'Security';

    protected static ?string $navigationLabel = 'Alert Center';

    protected static ?string $modelLabel = 'security alert';

    protected static ?string $slug = 'security-alerts';

    protected static ?int $navigationSort = 6;

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

    public static function getNavigationBadge(): ?string
    {
        try {
            $open = SecurityAlert::query()->open()->count();
        } catch (\Throwable) {
            // Badge must never take down the whole admin shell (missing table, locked DB, etc.).
            return null;
        }

        return $open > 0 ? (string) $open : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('user'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label('Raised')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('severity')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'critical' => 'danger',
                        'high' => 'warning',
                        'medium' => 'info',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('title')->label('Detection')->wrap()->searchable(),
                Tables\Columns\TextColumn::make('signal')->badge()->color('gray')->searchable(),
                Tables\Columns\TextColumn::make('surface')->badge()->color('gray'),
                Tables\Columns\TextColumn::make('action')->badge(),
                Tables\Columns\TextColumn::make('confidence')->numeric(1)->suffix('%')->sortable(),
                Tables\Columns\TextColumn::make('user.username')->label('Account')->placeholder('—')->searchable(),
                Tables\Columns\TextColumn::make('status')->badge(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    SecurityAlert::STATUS_OPEN => 'Open',
                    SecurityAlert::STATUS_ACKNOWLEDGED => 'Acknowledged',
                    SecurityAlert::STATUS_RESOLVED => 'Resolved',
                    SecurityAlert::STATUS_DISMISSED => 'Dismissed',
                ]),
                Tables\Filters\SelectFilter::make('severity')->options([
                    'critical' => 'Critical',
                    'high' => 'High',
                    'medium' => 'Medium',
                    'low' => 'Low',
                    'info' => 'Info',
                ]),
                Tables\Filters\SelectFilter::make('surface')->options(
                    fn (): array => SecurityAlert::query()
                        ->distinct()
                        ->orderBy('surface')
                        ->pluck('surface', 'surface')
                        ->all(),
                ),
            ])
            ->actions([
                Tables\Actions\Action::make('acknowledge')
                    ->icon('heroicon-o-eye')
                    ->visible(fn (SecurityAlert $record): bool => $record->status === SecurityAlert::STATUS_OPEN
                        && static::allowsSecurity(SecurityPermission::REVIEW_SECURITY_CASES))
                    ->action(fn (SecurityAlert $record) => app(SecurityAdminService::class)
                        ->acknowledgeAlert($record, static::actorId())),
                Tables\Actions\Action::make('resolve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->form([Forms\Components\Textarea::make('note')->maxLength(500)])
                    ->visible(fn (SecurityAlert $record): bool => ! in_array($record->status, [
                        SecurityAlert::STATUS_RESOLVED,
                        SecurityAlert::STATUS_DISMISSED,
                    ], true) && static::allowsSecurity(SecurityPermission::REVIEW_SECURITY_CASES))
                    ->action(fn (SecurityAlert $record, array $data) => app(SecurityAdminService::class)
                        ->resolveAlert($record, static::actorId(), (string) ($data['note'] ?? ''))),
                Tables\Actions\Action::make('dismiss')
                    ->icon('heroicon-o-x-circle')
                    ->color('gray')
                    ->form([Forms\Components\Textarea::make('reason')->required()->maxLength(500)])
                    ->visible(fn (SecurityAlert $record): bool => ! in_array($record->status, [
                        SecurityAlert::STATUS_RESOLVED,
                        SecurityAlert::STATUS_DISMISSED,
                    ], true) && static::allowsSecurity(SecurityPermission::REVIEW_SECURITY_CASES))
                    ->action(fn (SecurityAlert $record, array $data) => app(SecurityAdminService::class)
                        ->dismissAlert($record, static::actorId(), (string) ($data['reason'] ?? ''))),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSecurityAlerts::route('/'),
        ];
    }

    private static function actorId(): ?string
    {
        $id = Auth::id();

        return $id === null ? null : (string) $id;
    }
}
