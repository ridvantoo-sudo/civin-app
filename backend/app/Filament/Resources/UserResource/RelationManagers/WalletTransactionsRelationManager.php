<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use App\Features\Wallet\Models\WalletTransaction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class WalletTransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'walletTransactions';

    protected static ?string $title = 'Wallet transactions';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('type')
            ->columns([
                Tables\Columns\TextColumn::make('type')->badge()->searchable(),
                Tables\Columns\TextColumn::make('amount')->numeric()->sortable(),
                Tables\Columns\TextColumn::make('currency')->badge(),
                Tables\Columns\TextColumn::make('reference_type')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        WalletTransaction::TYPE_COIN_PURCHASE => 'Coin purchase',
                        WalletTransaction::TYPE_GIFT_SENT => 'Gift sent',
                        WalletTransaction::TYPE_GIFT_RECEIVED => 'Gift received',
                        WalletTransaction::TYPE_ADMIN_ADJUSTMENT => 'Admin adjustment',
                        WalletTransaction::TYPE_WITHDRAW => 'Withdraw',
                        WalletTransaction::TYPE_OFFLINE_RECHARGE => 'Offline recharge',
                    ]),
            ])
            ->headerActions([])
            ->actions([])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
