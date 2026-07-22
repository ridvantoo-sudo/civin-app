<?php

namespace App\Filament\Resources;

use App\Features\Admin\Support\AdminPermission;
use App\Features\Agency\Models\Agency;
use App\Filament\Concerns\AuthorizesAdminAccess;
use App\Filament\Resources\AgencyResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class AgencyResource extends Resource
{
    use AuthorizesAdminAccess;

    protected static ?string $model = Agency::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationGroup = 'Community';

    protected static ?int $navigationSort = 3;

    public static function canViewAny(): bool
    {
        return static::canAccessWithPermission(AdminPermission::MANAGE_AGENCIES);
    }

    public static function canCreate(): bool
    {
        return static::canAccessWithPermission(AdminPermission::MANAGE_AGENCIES);
    }

    public static function canEdit($record): bool
    {
        return static::canAccessWithPermission(AdminPermission::MANAGE_AGENCIES);
    }

    public static function canDelete($record): bool
    {
        return static::canAccessWithPermission(AdminPermission::MANAGE_AGENCIES);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('owner_id')
                    ->relationship('owner', 'username')
                    ->searchable()
                    ->required()
                    ->disabledOn('edit'),
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(150)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Forms\Set $set, ?string $state) => $set('slug', Str::slug((string) $state))),
                Forms\Components\TextInput::make('slug')->required()->maxLength(160)->unique(ignoreRecord: true),
                Forms\Components\Textarea::make('description')->rows(3)->columnSpanFull(),
                Forms\Components\TextInput::make('logo')->url()->maxLength(2048),
                Forms\Components\TextInput::make('commission_rate')->numeric()->minValue(0)->maxValue(100)->required(),
                Forms\Components\Select::make('status')
                    ->options([
                        Agency::STATUS_ACTIVE => 'Active',
                        Agency::STATUS_INACTIVE => 'Inactive',
                        Agency::STATUS_SUSPENDED => 'Suspended',
                    ])
                    ->required(),
            ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('owner.username')->label('Owner'),
                Tables\Columns\TextColumn::make('commission_rate')->suffix('%'),
                Tables\Columns\TextColumn::make('hosts_count')->numeric()->sortable(),
                Tables\Columns\TextColumn::make('total_gross_earnings')->numeric()->sortable(),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        Agency::STATUS_ACTIVE => 'success',
                        Agency::STATUS_SUSPENDED => 'danger',
                        default => 'gray',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        Agency::STATUS_ACTIVE => 'Active',
                        Agency::STATUS_INACTIVE => 'Inactive',
                        Agency::STATUS_SUSPENDED => 'Suspended',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAgencies::route('/'),
            'create' => Pages\CreateAgency::route('/create'),
            'edit' => Pages\EditAgency::route('/{record}/edit'),
        ];
    }
}
