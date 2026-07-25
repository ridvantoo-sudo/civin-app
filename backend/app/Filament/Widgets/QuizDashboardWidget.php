<?php

namespace App\Filament\Widgets;

use App\Features\Admin\Support\AdminPermission;
use App\Features\Games\Plugins\Quiz\Models\QuizQuestion;
use App\Features\Games\Plugins\Quiz\Models\QuizSession;
use App\Features\Games\Plugins\Quiz\Models\QuizTournament;
use App\Filament\Concerns\AuthorizesAdminAccess;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class QuizDashboardWidget extends StatsOverviewWidget
{
    use AuthorizesAdminAccess;

    protected static ?int $sort = 30;

    public static function canView(): bool
    {
        return static::canAccessWithPermission(AdminPermission::MANAGE_GAMES);
    }

    protected function getStats(): array
    {
        return [
            Stat::make('Quiz Sessions', QuizSession::query()->count()),
            Stat::make('Active Quizzes', QuizSession::query()->whereIn('status', ['lobby', 'question', 'starting'])->count()),
            Stat::make('Question Bank', QuizQuestion::query()->where('status', 'active')->count()),
            Stat::make('Tournaments', QuizTournament::query()->count()),
        ];
    }
}
