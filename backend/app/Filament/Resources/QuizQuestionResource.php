<?php

namespace App\Filament\Resources;

use App\Features\Admin\Support\AdminPermission;
use App\Features\Games\Plugins\Quiz\Models\QuizQuestion;
use App\Filament\Concerns\AuthorizesAdminAccess;
use App\Filament\Resources\QuizQuestionResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class QuizQuestionResource extends Resource
{
    use AuthorizesAdminAccess;

    protected static ?string $model = QuizQuestion::class;

    protected static ?string $navigationIcon = 'heroicon-o-question-mark-circle';

    protected static ?string $navigationGroup = 'Games';

    protected static ?int $navigationSort = 31;

    protected static ?string $navigationLabel = 'Quiz Questions';

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
            Forms\Components\Select::make('category_id')
                ->relationship('category', 'name')
                ->searchable()
                ->nullable(),
            Forms\Components\Textarea::make('question')->required()->maxLength(500)->columnSpanFull(),
            Forms\Components\Textarea::make('prompt')->maxLength(1000)->columnSpanFull(),
            Forms\Components\Select::make('type')->options([
                'multiple_choice' => 'Multiple Choice',
                'true_false' => 'True / False',
                'image' => 'Image',
                'audio' => 'Audio',
                'video' => 'Video',
                'text' => 'Text',
                'emoji' => 'Emoji',
                'puzzle' => 'Puzzle',
            ])->required(),
            Forms\Components\Select::make('difficulty')->options([
                'easy' => 'Easy', 'medium' => 'Medium', 'hard' => 'Hard', 'expert' => 'Expert', 'custom' => 'Custom',
            ])->required(),
            Forms\Components\TextInput::make('language')->default('en')->required(),
            Forms\Components\TextInput::make('correct_index')->numeric()->minValue(0)->required(),
            Forms\Components\TagsInput::make('options')
                ->helperText('Legacy options array used by the game engine.')
                ->required(),
            Forms\Components\Repeater::make('optionRows')
                ->relationship()
                ->schema([
                    Forms\Components\TextInput::make('option_index')->numeric()->required(),
                    Forms\Components\TextInput::make('label')->required()->maxLength(500),
                    Forms\Components\TextInput::make('media_url')->maxLength(500),
                    Forms\Components\Toggle::make('is_correct'),
                ])
                ->columns(2)
                ->columnSpanFull()
                ->defaultItems(0),
            Forms\Components\TextInput::make('media_url')->maxLength(500),
            Forms\Components\Select::make('media_type')->options([
                'image' => 'Image',
                'audio' => 'Audio',
                'video' => 'Video',
            ])->nullable(),
            Forms\Components\Select::make('status')->options(['active' => 'Active', 'inactive' => 'Inactive'])->required(),
            Forms\Components\Select::make('approval_status')->options([
                'approved' => 'Approved', 'pending' => 'Pending', 'rejected' => 'Rejected',
            ])->required(),
            Forms\Components\TextInput::make('reward_coins')->numeric()->default(20),
            Forms\Components\TextInput::make('bet_coins')->numeric()->default(10),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('question')->limit(40)->searchable(),
            Tables\Columns\TextColumn::make('type')->badge(),
            Tables\Columns\TextColumn::make('difficulty')->badge(),
            Tables\Columns\TextColumn::make('language'),
            Tables\Columns\TextColumn::make('approval_status')->badge(),
            Tables\Columns\TextColumn::make('status')->badge(),
        ])->actions([Tables\Actions\EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListQuizQuestions::route('/'),
            'create' => Pages\CreateQuizQuestion::route('/create'),
            'edit' => Pages\EditQuizQuestion::route('/{record}/edit'),
        ];
    }
}
