<?php

namespace App\Filament\Resources;

use App\Features\Admin\Support\AdminPermission;
use App\Features\Wallet\Models\WalletTransaction;
use App\Filament\Concerns\AuthorizesAdminAccess;
use App\Filament\Resources\WalletTransactionResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class WalletTransactionResource extends Resource
{
    use AuthorizesAdminAccess;

    protected static ?string $model = WalletTransaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationGroup = 'Economy';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Wallet Transactions';

    public static function canViewAny(): bool
    {
        return static::canAccessWithPermission(AdminPermission::MANAGE_WALLETS);
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
                Forms\Components\TextInput::make('type')->disabled(),
                Forms\Components\TextInput::make('amount')->disabled(),
                Forms\Components\TextInput::make('currency')->disabled(),
                Forms\Components\KeyValue::make('metadata')->disabled(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.username')->label('User')->searchable(),
                Tables\Columns\TextColumn::make('type')->badge()->sortable(),
                Tables\Columns\TextColumn::make('amount')->numeric()->sortable(),
                Tables\Columns\TextColumn::make('currency')->badge(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        WalletTransaction::TYPE_COIN_PURCHASE => 'Coin purchase',
                        WalletTransaction::TYPE_GIFT_SENT => 'Gift sent',
                        WalletTransaction::TYPE_GIFT_RECEIVED => 'Gift received',
                        WalletTransaction::TYPE_PK_REWARD => 'PK reward',
                        WalletTransaction::TYPE_WITHDRAW => 'Withdraw',
                        WalletTransaction::TYPE_ADMIN_ADJUSTMENT => 'Admin adjustment',
                    ]),
                Tables\Filters\SelectFilter::make('currency')
                    ->options([
                        WalletTransaction::CURRENCY_COINS => 'Coins',
                        WalletTransaction::CURRENCY_DIAMONDS => 'Diamonds',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('user'));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWalletTransactions::route('/'),
            'view' => Pages\ViewWalletTransaction::route('/{record}'),
        ];
    }
}
