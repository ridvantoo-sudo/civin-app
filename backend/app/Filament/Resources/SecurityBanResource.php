<?php

namespace App\Filament\Resources;

use App\Features\Security\Models\SecurityBan;
use App\Features\Security\Services\SecurityAdminService;
use App\Features\Security\Support\SecurityAction;
use App\Features\Security\Support\SecurityPermission;
use App\Features\Users\Models\User;
use App\Filament\Concerns\AuthorizesSecurityAccess;
use App\Filament\Resources\SecurityBanResource\Pages;
use Carbon\CarbonImmutable;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Ban Center.
 */
class SecurityBanResource extends Resource
{
    use AuthorizesSecurityAccess;

    protected static ?string $model = SecurityBan::class;

    protected static ?string $navigationIcon = 'heroicon-o-no-symbol';

    protected static ?string $navigationGroup = 'Security';

    protected static ?string $navigationLabel = 'Ban Center';

    protected static ?string $modelLabel = 'security restriction';

    protected static ?string $slug = 'security-bans';

    protected static ?int $navigationSort = 7;

    public static function canViewAny(): bool
    {
        return static::canViewSecurity();
    }

    public static function canCreate(): bool
    {
        return static::allowsSecurity(SecurityPermission::MANAGE_SECURITY_BANS);
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\Select::make('user_id')
                ->label('Account')
                ->required()
                ->searchable()
                ->getSearchResultsUsing(fn (string $search): array => User::query()
                    ->where('username', 'like', "%{$search}%")
                    ->limit(25)
                    ->pluck('username', 'id')
                    ->all())
                ->getOptionLabelUsing(fn ($value): ?string => User::query()->find($value)?->username),
            Forms\Components\Select::make('scope')
                ->required()
                ->default(SecurityBan::SCOPE_PLATFORM)
                ->options(array_combine(SecurityBan::scopes(), array_map('ucfirst', SecurityBan::scopes()))),
            Forms\Components\Select::make('action')
                ->required()
                ->default(SecurityAction::SUSPEND)
                ->options([
                    SecurityAction::SUSPEND => 'Suspend',
                    SecurityAction::BAN => 'Ban',
                    SecurityAction::THROTTLE => 'Throttle',
                ]),
            Forms\Components\TextInput::make('reason')->required()->maxLength(191),
            Forms\Components\DateTimePicker::make('expires_at')
                ->label('Expires at')
                ->helperText('Leave empty for an indefinite restriction'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('user'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label('Issued')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('user.username')->label('Account')->placeholder('—')->searchable(),
                Tables\Columns\TextColumn::make('scope')->badge(),
                Tables\Columns\TextColumn::make('action')
                    ->badge()
                    ->color(fn (string $state): string => $state === SecurityAction::BAN ? 'danger' : 'warning'),
                Tables\Columns\TextColumn::make('reason')->wrap()->limit(80)->searchable(),
                Tables\Columns\IconColumn::make('is_automatic')->label('Auto')->boolean(),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('expires_at')->dateTime()->placeholder('Never'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    SecurityBan::STATUS_ACTIVE => 'Active',
                    SecurityBan::STATUS_EXPIRED => 'Expired',
                    SecurityBan::STATUS_LIFTED => 'Lifted',
                ]),
                Tables\Filters\SelectFilter::make('scope')->options(
                    array_combine(SecurityBan::scopes(), array_map('ucfirst', SecurityBan::scopes())),
                ),
                Tables\Filters\TernaryFilter::make('is_automatic')->label('Issued by engine'),
            ])
            ->actions([
                Tables\Actions\Action::make('lift')
                    ->icon('heroicon-o-lock-open')
                    ->color('success')
                    ->requiresConfirmation()
                    ->form([Forms\Components\Textarea::make('reason')->required()->maxLength(500)])
                    ->visible(fn (SecurityBan $record): bool => $record->status === SecurityBan::STATUS_ACTIVE
                        && static::allowsSecurity(SecurityPermission::MANAGE_SECURITY_BANS))
                    ->action(fn (SecurityBan $record, array $data) => app(SecurityAdminService::class)
                        ->liftBan($record, static::actorId(), (string) ($data['reason'] ?? ''))),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSecurityBans::route('/'),
            'create' => Pages\CreateSecurityBan::route('/create'),
        ];
    }

    public static function issueFromForm(array $data): SecurityBan
    {
        return app(SecurityAdminService::class)->issueBan(
            userId: (string) $data['user_id'],
            scope: (string) $data['scope'],
            action: (string) $data['action'],
            reason: (string) $data['reason'],
            actorId: static::actorId(),
            expiresAt: isset($data['expires_at']) && $data['expires_at'] !== null
                ? CarbonImmutable::parse((string) $data['expires_at'])
                : null,
        );
    }

    private static function actorId(): ?string
    {
        $id = Auth::id();

        return $id === null ? null : (string) $id;
    }
}
