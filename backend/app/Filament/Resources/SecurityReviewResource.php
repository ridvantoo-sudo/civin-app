<?php

namespace App\Filament\Resources;

use App\Features\Security\Models\SecurityReview;
use App\Features\Security\Services\SecurityAdminService;
use App\Features\Security\Support\SecurityPermission;
use App\Filament\Concerns\AuthorizesSecurityAccess;
use App\Filament\Resources\SecurityReviewResource\Pages;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Review Queue — everything the engine deliberately refused to auto-enforce.
 */
class SecurityReviewResource extends Resource
{
    use AuthorizesSecurityAccess;

    protected static ?string $model = SecurityReview::class;

    protected static ?string $navigationIcon = 'heroicon-o-inbox-stack';

    protected static ?string $navigationGroup = 'Security';

    protected static ?string $navigationLabel = 'Review Queue';

    protected static ?string $modelLabel = 'review case';

    protected static ?string $slug = 'security-reviews';

    protected static ?int $navigationSort = 8;

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
        $pending = SecurityReview::query()->pending()->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('user'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label('Opened')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('queue')->badge()->color('gray'),
                Tables\Columns\TextColumn::make('reason')->wrap()->limit(80)->searchable(),
                Tables\Columns\TextColumn::make('user.username')->label('Account')->placeholder('—')->searchable(),
                Tables\Columns\TextColumn::make('risk_score')->numeric(1)->sortable(),
                Tables\Columns\TextColumn::make('priority')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'urgent' ? 'danger' : 'gray')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('resolution')->badge()->placeholder('—'),
                Tables\Columns\TextColumn::make('due_at')->dateTime()->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    SecurityReview::STATUS_PENDING => 'Pending',
                    SecurityReview::STATUS_IN_PROGRESS => 'In progress',
                    SecurityReview::STATUS_COMPLETED => 'Completed',
                ]),
                Tables\Filters\SelectFilter::make('queue')->options(
                    fn (): array => SecurityReview::query()
                        ->distinct()
                        ->orderBy('queue')
                        ->pluck('queue', 'queue')
                        ->all(),
                ),
            ])
            ->actions([
                Tables\Actions\Action::make('claim')
                    ->icon('heroicon-o-hand-raised')
                    ->visible(fn (SecurityReview $record): bool => $record->status === SecurityReview::STATUS_PENDING
                        && static::allowsSecurity(SecurityPermission::REVIEW_SECURITY_CASES))
                    ->action(fn (SecurityReview $record) => app(SecurityAdminService::class)
                        ->claimReview($record, static::actorId())),
                Tables\Actions\Action::make('resolve')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->form([
                        Forms\Components\Select::make('resolution')
                            ->required()
                            ->options([
                                SecurityReview::RESOLUTION_CLEARED => 'Cleared — no action',
                                SecurityReview::RESOLUTION_WARNED => 'Warned',
                                SecurityReview::RESOLUTION_SUSPENDED => 'Suspended',
                                SecurityReview::RESOLUTION_BANNED => 'Banned',
                            ]),
                        Forms\Components\Textarea::make('notes')->maxLength(2000),
                    ])
                    ->visible(fn (SecurityReview $record): bool => $record->status !== SecurityReview::STATUS_COMPLETED
                        && static::allowsSecurity(SecurityPermission::REVIEW_SECURITY_CASES))
                    ->action(fn (SecurityReview $record, array $data) => app(SecurityAdminService::class)
                        ->completeReview(
                            $record,
                            (string) $data['resolution'],
                            static::actorId(),
                            (string) ($data['notes'] ?? ''),
                        )),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSecurityReviews::route('/'),
        ];
    }

    private static function actorId(): ?string
    {
        $id = Auth::id();

        return $id === null ? null : (string) $id;
    }
}
