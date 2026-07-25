<?php

namespace App\Filament\Resources;

use App\Features\Admin\Services\AdminAuditLogger;
use App\Features\Admin\Support\AdminPermission;
use App\Features\Wallet\DTOs\ReviewOfflineRechargeData;
use App\Features\Wallet\Models\OfflineRechargeRequest;
use App\Features\Wallet\Services\WalletService;
use App\Filament\Concerns\AuthorizesAdminAccess;
use App\Filament\Resources\OfflineRechargeResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OfflineRechargeResource extends Resource
{
    use AuthorizesAdminAccess;

    protected static ?string $model = OfflineRechargeRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-check';

    protected static ?string $navigationGroup = 'Economy';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'Offline Recharge';

    protected static ?string $pluralModelLabel = 'Offline Recharges';

    public static function canViewAny(): bool
    {
        return static::canAccessWithPermission(AdminPermission::APPROVE_OFFLINE_RECHARGES)
            || static::canAccessWithPermission(AdminPermission::MANAGE_WALLETS);
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
                Forms\Components\TextInput::make('coins')->disabled(),
                Forms\Components\TextInput::make('amount')->disabled(),
                Forms\Components\TextInput::make('currency')->disabled(),
                Forms\Components\TextInput::make('reference_number')->disabled(),
                Forms\Components\TextInput::make('status')->disabled(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.username')->label('User')->searchable(),
                Tables\Columns\TextColumn::make('coins')->numeric()->sortable(),
                Tables\Columns\TextColumn::make('amount')->numeric()->sortable(),
                Tables\Columns\TextColumn::make('currency'),
                Tables\Columns\TextColumn::make('reference_number')->searchable()->copyable(),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        OfflineRechargeRequest::STATUS_APPROVED => 'success',
                        OfflineRechargeRequest::STATUS_REJECTED => 'danger',
                        default => 'warning',
                    }),
                Tables\Columns\TextColumn::make('approver.username')->label('Reviewed by'),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        OfflineRechargeRequest::STATUS_PENDING => 'Pending',
                        OfflineRechargeRequest::STATUS_APPROVED => 'Approved',
                        OfflineRechargeRequest::STATUS_REJECTED => 'Rejected',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('viewReceipt')
                    ->label('Receipt')
                    ->icon('heroicon-o-photo')
                    ->url(fn (OfflineRechargeRequest $record): ?string => $record->receiptUrl())
                    ->openUrlInNewTab()
                    ->visible(fn (OfflineRechargeRequest $record): bool => filled($record->receipt_path)),
                Tables\Actions\Action::make('approve')
                    ->color('success')
                    ->icon('heroicon-o-check')
                    ->requiresConfirmation()
                    ->modalHeading('Approve offline recharge')
                    ->modalDescription('This will credit the user wallet with the requested coins.')
                    ->visible(fn (OfflineRechargeRequest $record): bool => $record->status === OfflineRechargeRequest::STATUS_PENDING
                        && (auth()->user()?->can(AdminPermission::APPROVE_OFFLINE_RECHARGES) ?? false))
                    ->action(function (OfflineRechargeRequest $record): void {
                        $admin = auth()->user();
                        app(WalletService::class)->reviewOfflineRecharge(
                            $record,
                            $admin,
                            new ReviewOfflineRechargeData(OfflineRechargeRequest::STATUS_APPROVED),
                        );
                        app(AdminAuditLogger::class)->log($admin, 'offline_recharge.approved', $record);
                        Notification::make()->title('Offline recharge approved')->success()->send();
                    }),
                Tables\Actions\Action::make('reject')
                    ->color('danger')
                    ->icon('heroicon-o-x-mark')
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('notes')->label('Reason')->required(),
                    ])
                    ->visible(fn (OfflineRechargeRequest $record): bool => $record->status === OfflineRechargeRequest::STATUS_PENDING
                        && (auth()->user()?->can(AdminPermission::APPROVE_OFFLINE_RECHARGES) ?? false))
                    ->action(function (OfflineRechargeRequest $record, array $data): void {
                        $admin = auth()->user();
                        app(WalletService::class)->reviewOfflineRecharge(
                            $record,
                            $admin,
                            new ReviewOfflineRechargeData(
                                OfflineRechargeRequest::STATUS_REJECTED,
                                $data['notes'] ?? null,
                            ),
                        );
                        app(AdminAuditLogger::class)->log($admin, 'offline_recharge.rejected', $record, [
                            'notes' => $data['notes'] ?? null,
                        ]);
                        Notification::make()->title('Offline recharge rejected')->success()->send();
                    }),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['user', 'approver']));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOfflineRecharges::route('/'),
        ];
    }
}
