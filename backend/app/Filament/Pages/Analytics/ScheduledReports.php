<?php

namespace App\Filament\Pages\Analytics;

use App\Features\GameAnalytics\Models\ScheduledReport;
use App\Filament\Pages\Analytics\Concerns\AuthorizesAnalyticsAccess;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ScheduledReports extends Page implements HasForms, HasTable
{
    use AuthorizesAnalyticsAccess;
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationGroup = 'Analytics';

    protected static ?string $navigationLabel = 'Scheduled Reports';

    protected static ?string $title = 'Scheduled Reports';

    protected static ?string $slug = 'scheduled-reports';

    protected static ?int $navigationSort = 8;

    protected static string $view = 'filament.pages.analytics.scheduled-reports';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => ScheduledReport::query()->latest())
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('report_type')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('frequency')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active'),
                Tables\Columns\TextColumn::make('formats')
                    ->formatStateUsing(fn ($state): string => is_array($state) ? implode(', ', $state) : (string) $state)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('next_run_at')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('last_run_at')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('last_status')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'success' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    })
                    ->placeholder('—'),
            ])
            ->actions([
                Tables\Actions\Action::make('toggle')
                    ->label(fn (ScheduledReport $record): string => $record->is_active ? 'Pause' : 'Activate')
                    ->icon(fn (ScheduledReport $record): string => $record->is_active ? 'heroicon-o-pause' : 'heroicon-o-play')
                    ->action(function (ScheduledReport $record): void {
                        $record->update(['is_active' => ! $record->is_active]);
                    }),
                Tables\Actions\DeleteAction::make(),
            ])
            ->emptyStateHeading('No scheduled reports')
            ->emptyStateDescription('Create a report schedule to email analytics exports on a cadence.')
            ->striped();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label('New schedule')
                ->icon('heroicon-o-plus')
                ->form([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(120),
                    Forms\Components\Select::make('report_type')
                        ->options([
                            'overview' => 'Overview',
                            'revenue' => 'Revenue',
                            'games' => 'Games',
                            'players' => 'Players',
                            'retention' => 'Retention',
                            'live' => 'Live',
                            'agencies' => 'Agencies',
                            'vip' => 'VIP',
                        ])
                        ->required()
                        ->native(false),
                    Forms\Components\Select::make('frequency')
                        ->options([
                            'daily' => 'Daily',
                            'weekly' => 'Weekly',
                            'monthly' => 'Monthly',
                        ])
                        ->required()
                        ->native(false),
                    Forms\Components\TagsInput::make('recipients')
                        ->placeholder('email@example.com')
                        ->required(),
                    Forms\Components\CheckboxList::make('formats')
                        ->options([
                            'csv' => 'CSV',
                            'xls' => 'Excel',
                            'pdf' => 'PDF',
                        ])
                        ->columns(3)
                        ->required(),
                    Forms\Components\Toggle::make('is_active')
                        ->default(true),
                ])
                ->action(function (array $data): void {
                    ScheduledReport::query()->create([
                        'name' => $data['name'],
                        'report_type' => $data['report_type'],
                        'frequency' => $data['frequency'],
                        'recipients' => array_values($data['recipients'] ?? []),
                        'formats' => array_values($data['formats'] ?? ['csv']),
                        'filters' => [],
                        'is_active' => (bool) ($data['is_active'] ?? true),
                        'next_run_at' => now()->addDay()->startOfHour(),
                        'created_by' => auth()->id(),
                    ]);

                    Notification::make()
                        ->title('Scheduled report created')
                        ->success()
                        ->send();
                }),
        ];
    }
}
