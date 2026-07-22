<?php

namespace App\Filament\Resources;

use App\Features\Admin\Services\AdminAuditLogger;
use App\Features\Admin\Support\AdminPermission;
use App\Features\Wallet\DTOs\ReviewWithdrawData;
use App\Features\Wallet\Models\WithdrawRequest;
use App\Features\Wallet\Services\WalletService;
use App\Filament\Concerns\AuthorizesAdminAccess;
use App\Filament\Resources\WithdrawalResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class WithdrawalResource extends Resource
{
    use AuthorizesAdminAccess;

    protected static ?string $model = WithdrawRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Economy';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'Withdrawal';

    protected static ?string $pluralModelLabel = 'Withdrawals';

    public static function canViewAny(): bool
    {
        return static::canAccessWithPermission(AdminPermission::APPROVE_WITHDRAWALS)
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
                Forms\Components\TextInput::make('diamonds')->disabled(),
                Forms\Components\TextInput::make('amount')->disabled(),
                Forms\Components\TextInput::make('status')->disabled(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.username')->label('User')->searchable(),
                Tables\Columns\TextColumn::make('diamonds')->numeric()->sortable(),
                Tables\Columns\TextColumn::make('amount')->numeric()->sortable(),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        WithdrawRequest::STATUS_APPROVED => 'success',
                        WithdrawRequest::STATUS_REJECTED => 'danger',
                        default => 'warning',
                    }),
                Tables\Columns\TextColumn::make('approver.username')->label('Reviewed by'),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        WithdrawRequest::STATUS_PENDING => 'Pending',
                        WithdrawRequest::STATUS_APPROVED => 'Approved',
                        WithdrawRequest::STATUS_REJECTED => 'Rejected',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->color('success')
                    ->icon('heroicon-o-check')
                    ->requiresConfirmation()
                    ->visible(fn (WithdrawRequest $record): bool => $record->status === WithdrawRequest::STATUS_PENDING
                        && (auth()->user()?->can(AdminPermission::APPROVE_WITHDRAWALS) ?? false))
                    ->action(function (WithdrawRequest $record): void {
                        $admin = auth()->user();
                        app(WalletService::class)->reviewWithdraw(
                            $record,
                            $admin,
                            new ReviewWithdrawData(WithdrawRequest::STATUS_APPROVED),
                        );
                        app(AdminAuditLogger::class)->log($admin, 'withdrawal.approved', $record);
                        Notification::make()->title('Withdrawal approved')->success()->send();
                    }),
                Tables\Actions\Action::make('reject')
                    ->color('danger')
                    ->icon('heroicon-o-x-mark')
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('notes')->label('Reason')->required(),
                    ])
                    ->visible(fn (WithdrawRequest $record): bool => $record->status === WithdrawRequest::STATUS_PENDING
                        && (auth()->user()?->can(AdminPermission::APPROVE_WITHDRAWALS) ?? false))
                    ->action(function (WithdrawRequest $record, array $data): void {
                        $admin = auth()->user();
                        app(WalletService::class)->reviewWithdraw(
                            $record,
                            $admin,
                            new ReviewWithdrawData(WithdrawRequest::STATUS_REJECTED, $data['notes'] ?? null),
                        );
                        app(AdminAuditLogger::class)->log($admin, 'withdrawal.rejected', $record, [
                            'notes' => $data['notes'] ?? null,
                        ]);
                        Notification::make()->title('Withdrawal rejected')->success()->send();
                    }),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['user', 'approver']));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWithdrawals::route('/'),
        ];
    }
}
