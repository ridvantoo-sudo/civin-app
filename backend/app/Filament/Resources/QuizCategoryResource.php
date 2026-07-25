<?php

namespace App\Filament\Resources;

use App\Features\Admin\Support\AdminPermission;
use App\Features\Games\Plugins\Quiz\Models\QuizCategory;
use App\Filament\Concerns\AuthorizesAdminAccess;
use App\Filament\Resources\QuizCategoryResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class QuizCategoryResource extends Resource
{
    use AuthorizesAdminAccess;

    protected static ?string $model = QuizCategory::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationGroup = 'Games';

    protected static ?int $navigationSort = 32;

    protected static ?string $navigationLabel = 'Quiz Categories';

    public static function canViewAny(): bool
    {
        return static::canAccessWithPermission(AdminPermission::MANAGE_GAMES);
    }

    public static function canCreate(): bool
    {
        return static::canAccessWithPermission(AdminPermission::MANAGE_GAMES);
    }

    public static function canEdit($record): bool
    {
        return static::canAccessWithPermission(AdminPermission::MANAGE_GAMES);
    }

    public static function canDelete($record): bool
    {
        return static::canAccessWithPermission(AdminPermission::MANAGE_GAMES);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('slug')->required()->maxLength(64)->disabledOn('edit'),
            Forms\Components\TextInput::make('name')->required()->maxLength(120),
            Forms\Components\Textarea::make('description')->maxLength(500),
            Forms\Components\TextInput::make('icon')->maxLength(64),
            Forms\Components\Toggle::make('is_custom'),
            Forms\Components\TextInput::make('sort_order')->numeric()->default(0),
            Forms\Components\Select::make('status')->options(['active' => 'Active', 'inactive' => 'Inactive'])->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('slug'),
            Tables\Columns\TextColumn::make('name')->searchable(),
            Tables\Columns\IconColumn::make('is_custom')->boolean(),
            Tables\Columns\TextColumn::make('status')->badge(),
            Tables\Columns\TextColumn::make('sort_order'),
        ])->actions([Tables\Actions\EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListQuizCategories::route('/'),
            'create' => Pages\CreateQuizCategory::route('/create'),
            'edit' => Pages\EditQuizCategory::route('/{record}/edit'),
        ];
    }
}
