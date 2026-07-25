<?php

namespace App\Filament\Resources;

use App\Features\Admin\Support\AdminPermission;
use App\Features\Games\Plugins\Quiz\Models\QuizConfig;
use App\Filament\Concerns\AuthorizesAdminAccess;
use App\Filament\Resources\QuizConfigResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class QuizConfigResource extends Resource
{
    use AuthorizesAdminAccess;

    protected static ?string $model = QuizConfig::class;

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $navigationGroup = 'Games';

    protected static ?int $navigationSort = 35;

    protected static ?string $navigationLabel = 'Quiz Config';

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
            Forms\Components\Textarea::make('description')->maxLength(500)->columnSpanFull(),
            Forms\Components\TextInput::make('version')->numeric()->minValue(1)->required(),
            Forms\Components\TextInput::make('min_bet_coins')->numeric()->minValue(0)->required(),
            Forms\Components\TextInput::make('max_bet_coins')->numeric()->minValue(1)->required(),
            Forms\Components\TextInput::make('default_bet_coins')->numeric()->minValue(0)->required(),
            Forms\Components\TextInput::make('default_question_count')->numeric()->minValue(1)->required(),
            Forms\Components\TextInput::make('max_question_count')->numeric()->minValue(1)->required(),
            Forms\Components\TextInput::make('default_time_per_question')->numeric()->minValue(1)->required(),
            Forms\Components\TextInput::make('min_time_per_question')->numeric()->minValue(1)->required(),
            Forms\Components\TextInput::make('max_time_per_question')->numeric()->minValue(1)->required(),
            Forms\Components\TextInput::make('default_winner_count')->numeric()->minValue(1)->required(),
            Forms\Components\TextInput::make('max_winner_count')->numeric()->minValue(1)->required(),
            Forms\Components\TextInput::make('max_players')->numeric()->minValue(1)->required(),
            Forms\Components\TextInput::make('max_created_per_day')->numeric()->minValue(1)->nullable(),
            Forms\Components\TextInput::make('vip_reward_multiplier')->numeric()->minValue(1)->required(),
            Forms\Components\CheckboxList::make('modes_enabled')
                ->options(array_combine(QuizConfig::MODES, QuizConfig::MODES))
                ->columns(3)
                ->columnSpanFull(),
            Forms\Components\CheckboxList::make('question_types_enabled')
                ->options(array_combine(QuizConfig::QUESTION_TYPES, QuizConfig::QUESTION_TYPES))
                ->columns(3)
                ->columnSpanFull(),
            Forms\Components\CheckboxList::make('difficulties_enabled')
                ->options(array_combine(QuizConfig::DIFFICULTIES, QuizConfig::DIFFICULTIES))
                ->columns(3)
                ->columnSpanFull(),
            Forms\Components\CheckboxList::make('reward_types_enabled')
                ->options(array_combine(QuizConfig::REWARD_TYPES, QuizConfig::REWARD_TYPES))
                ->columns(3)
                ->columnSpanFull(),
            Forms\Components\KeyValue::make('scoring_rules')
                ->keyLabel('Rule')
                ->valueLabel('Value')
                ->columnSpanFull(),
            Forms\Components\Toggle::make('enabled')->required(),
            Forms\Components\Toggle::make('is_default'),
            Forms\Components\Select::make('status')->options([
                'active' => 'Active',
                'inactive' => 'Inactive',
            ])->required(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('slug')->searchable(),
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\IconColumn::make('enabled')->boolean(),
                Tables\Columns\IconColumn::make('is_default')->boolean(),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('updated_at')->dateTime(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListQuizConfigs::route('/'),
            'create' => Pages\CreateQuizConfig::route('/create'),
            'edit' => Pages\EditQuizConfig::route('/{record}/edit'),
        ];
    }
}
