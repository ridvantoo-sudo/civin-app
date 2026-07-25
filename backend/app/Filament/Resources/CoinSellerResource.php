<?php

namespace App\Filament\Resources;

use App\Features\Admin\Services\AdminAuditLogger;
use App\Features\Admin\Support\AdminPermission;
use App\Features\CoinSeller\DTOs\AllocateInventoryData;
use App\Features\CoinSeller\DTOs\ReviewCoinSellerData;
use App\Features\CoinSeller\Models\CoinSeller;
use App\Features\CoinSeller\Services\CoinSellerService;
use App\Filament\Concerns\AuthorizesAdminAccess;
use App\Filament\Resources\CoinSellerResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CoinSellerResource extends Resource
{
    use AuthorizesAdminAccess;

    protected static ?string $model = CoinSeller::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Economy';

    protected static ?int $navigationSort = 5;

    protected static ?string $modelLabel = 'Coin Seller';

    protected static ?string $pluralModelLabel = 'Coin Sellers';

    public static function canViewAny(): bool
    {
        return static::canAccessWithPermission(AdminPermission::MANAGE_COIN_SELLERS);
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
        return $form->schema([
            Forms\Components\TextInput::make('display_name')->disabled(),
            Forms\Components\TextInput::make('status')->disabled(),
            Forms\Components\TextInput::make('inventory_balance')->disabled(),
            Forms\Components\TextInput::make('commission_rate')->disabled(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('display_name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('user.username')->label('User')->searchable(),
                Tables\Columns\TextColumn::make('contact_info')->toggleable(),
                Tables\Columns\TextColumn::make('commission_rate')->suffix('%')->sortable(),
                Tables\Columns\TextColumn::make('inventory_balance')->numeric()->sortable(),
                Tables\Columns\TextColumn::make('total_sold')->numeric()->sortable(),
                Tables\Columns\TextColumn::make('total_commission')->numeric()->sortable(),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        CoinSeller::STATUS_ACTIVE => 'success',
                        CoinSeller::STATUS_REJECTED, CoinSeller::STATUS_SUSPENDED => 'danger',
                        default => 'warning',
                    }),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        CoinSeller::STATUS_PENDING => 'Pending',
                        CoinSeller::STATUS_ACTIVE => 'Active',
                        CoinSeller::STATUS_REJECTED => 'Rejected',
                        CoinSeller::STATUS_SUSPENDED => 'Suspended',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->color('success')
                    ->icon('heroicon-o-check')
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\TextInput::make('commission_rate')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->default(5)
                            ->required(),
                        Forms\Components\Textarea::make('notes')->maxLength(2000),
                    ])
                    ->visible(fn (CoinSeller $record): bool => in_array($record->status, [
                        CoinSeller::STATUS_PENDING,
                        CoinSeller::STATUS_SUSPENDED,
                        CoinSeller::STATUS_REJECTED,
                    ], true) && (auth()->user()?->can(AdminPermission::MANAGE_COIN_SELLERS) ?? false))
                    ->action(function (CoinSeller $record, array $data): void {
                        $admin = auth()->user();
                        app(CoinSellerService::class)->review(
                            $record,
                            $admin,
                            new ReviewCoinSellerData(
                                status: CoinSeller::STATUS_ACTIVE,
                                commissionRate: (float) ($data['commission_rate'] ?? 5),
                                notes: $data['notes'] ?? null,
                            ),
                        );
                        app(AdminAuditLogger::class)->log($admin, 'coin_seller.approved', $record);
                        Notification::make()->title('Coin seller approved')->success()->send();
                    }),
                Tables\Actions\Action::make('reject')
                    ->color('danger')
                    ->icon('heroicon-o-x-mark')
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('notes')->label('Reason')->required(),
                    ])
                    ->visible(fn (CoinSeller $record): bool => $record->status === CoinSeller::STATUS_PENDING
                        && (auth()->user()?->can(AdminPermission::MANAGE_COIN_SELLERS) ?? false))
                    ->action(function (CoinSeller $record, array $data): void {
                        $admin = auth()->user();
                        app(CoinSellerService::class)->review(
                            $record,
                            $admin,
                            new ReviewCoinSellerData(
                                status: CoinSeller::STATUS_REJECTED,
                                notes: $data['notes'] ?? null,
                            ),
                        );
                        app(AdminAuditLogger::class)->log($admin, 'coin_seller.rejected', $record, [
                            'notes' => $data['notes'] ?? null,
                        ]);
                        Notification::make()->title('Coin seller rejected')->success()->send();
                    }),
                Tables\Actions\Action::make('suspend')
                    ->color('warning')
                    ->icon('heroicon-o-pause')
                    ->requiresConfirmation()
                    ->visible(fn (CoinSeller $record): bool => $record->status === CoinSeller::STATUS_ACTIVE
                        && (auth()->user()?->can(AdminPermission::MANAGE_COIN_SELLERS) ?? false))
                    ->action(function (CoinSeller $record): void {
                        $admin = auth()->user();
                        app(CoinSellerService::class)->review(
                            $record,
                            $admin,
                            new ReviewCoinSellerData(status: CoinSeller::STATUS_SUSPENDED),
                        );
                        app(AdminAuditLogger::class)->log($admin, 'coin_seller.suspended', $record);
                        Notification::make()->title('Coin seller suspended')->success()->send();
                    }),
                Tables\Actions\Action::make('allocate')
                    ->label('Allocate inventory')
                    ->icon('heroicon-o-plus-circle')
                    ->form([
                        Forms\Components\TextInput::make('coins')->numeric()->minValue(1)->required(),
                        Forms\Components\Textarea::make('notes')->maxLength(2000),
                    ])
                    ->visible(fn (CoinSeller $record): bool => $record->status === CoinSeller::STATUS_ACTIVE
                        && (auth()->user()?->can(AdminPermission::MANAGE_COIN_SELLERS) ?? false))
                    ->action(function (CoinSeller $record, array $data): void {
                        $admin = auth()->user();
                        app(CoinSellerService::class)->allocateInventory(
                            $record,
                            $admin,
                            new AllocateInventoryData(
                                coins: (int) $data['coins'],
                                notes: $data['notes'] ?? null,
                            ),
                        );
                        app(AdminAuditLogger::class)->log($admin, 'coin_seller.inventory_allocated', $record, [
                            'coins' => $data['coins'] ?? null,
                        ]);
                        Notification::make()->title('Inventory allocated')->success()->send();
                    }),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['user', 'reviewer']));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCoinSellers::route('/'),
        ];
    }
}
