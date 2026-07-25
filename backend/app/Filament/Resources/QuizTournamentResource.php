<?php

namespace App\Filament\Resources;

use App\Features\Admin\Support\AdminPermission;
use App\Features\Games\Plugins\Quiz\Models\QuizTournament;
use App\Filament\Concerns\AuthorizesAdminAccess;
use App\Filament\Resources\QuizTournamentResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class QuizTournamentResource extends Resource
{
    use AuthorizesAdminAccess;

    protected static ?string $model = QuizTournament::class;

    protected static ?string $navigationIcon = 'heroicon-o-trophy';

    protected static ?string $navigationGroup = 'Games';

    protected static ?int $navigationSort = 34;

    protected static ?string $navigationLabel = 'Quiz Tournaments';

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
            Forms\Components\TextInput::make('name')->required()->maxLength(160),
            Forms\Components\Textarea::make('description')->maxLength(1000)->columnSpanFull(),
            Forms\Components\Select::make('status')->options([
                'scheduled' => 'Scheduled', 'active' => 'Active', 'finished' => 'Finished', 'cancelled' => 'Cancelled',
            ])->required(),
            Forms\Components\TextInput::make('rounds')->numeric()->minValue(1)->required(),
            Forms\Components\TextInput::make('max_players')->numeric()->minValue(2)->required(),
            Forms\Components\TextInput::make('entry_fee_coins')->numeric()->minValue(0)->required(),
            Forms\Components\TextInput::make('reward_pool')->numeric()->minValue(0)->required(),
            Forms\Components\Select::make('reward_type')->options([
                'coins' => 'Coins', 'diamonds' => 'Diamonds', 'gift' => 'Gift', 'vip_days' => 'VIP Days',
            ])->required(),
            Forms\Components\DateTimePicker::make('scheduled_at'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->searchable(),
            Tables\Columns\TextColumn::make('status')->badge(),
            Tables\Columns\TextColumn::make('rounds'),
            Tables\Columns\TextColumn::make('reward_pool'),
            Tables\Columns\TextColumn::make('scheduled_at')->dateTime(),
        ])->actions([Tables\Actions\EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListQuizTournaments::route('/'),
            'create' => Pages\CreateQuizTournament::route('/create'),
            'edit' => Pages\EditQuizTournament::route('/{record}/edit'),
        ];
    }
}
