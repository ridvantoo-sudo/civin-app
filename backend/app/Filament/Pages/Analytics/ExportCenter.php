<?php

namespace App\Filament\Pages\Analytics;

use App\Features\GameAnalytics\DTO\ExportRequestData;
use App\Features\GameAnalytics\Services\AnalyticsQueryService;
use App\Features\GameAnalytics\Services\ExportService;
use App\Filament\Pages\Analytics\Concerns\AuthorizesAnalyticsAccess;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportCenter extends Page implements HasForms
{
    use AuthorizesAnalyticsAccess;
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-down-tray';

    protected static ?string $navigationGroup = 'Analytics';

    protected static ?string $navigationLabel = 'Export Center';

    protected static ?string $title = 'Export Center';

    protected static ?string $slug = 'export-center';

    protected static ?int $navigationSort = 7;

    protected static string $view = 'filament.pages.analytics.export-center';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'format' => 'csv',
            'report_type' => 'overview',
            'from' => now()->subDays(30)->toDateString(),
            'to' => now()->toDateString(),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Export options')
                    ->description('Downloads are built from analytics aggregate tables only.')
                    ->schema([
                        Forms\Components\Select::make('report_type')
                            ->label('Report type')
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
                        Forms\Components\Select::make('format')
                            ->label('Format')
                            ->options([
                                'csv' => 'CSV',
                                'xls' => 'Excel (.xls)',
                                'pdf' => 'PDF',
                            ])
                            ->required()
                            ->native(false),
                        Forms\Components\DatePicker::make('from')
                            ->label('From')
                            ->required()
                            ->native(false),
                        Forms\Components\DatePicker::make('to')
                            ->label('To')
                            ->required()
                            ->native(false),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    public function export(ExportService $exportService, AnalyticsQueryService $query): StreamedResponse
    {
        $state = $this->form->getState();

        $result = $exportService->export(new ExportRequestData(
            format: (string) $state['format'],
            reportType: (string) $state['report_type'],
            filters: $query->defaultFilters(
                from: (string) $state['from'],
                to: (string) $state['to'],
            ),
        ));

        return response()->streamDownload(
            function () use ($result): void {
                echo $result['contents'];
            },
            $result['filename'],
            ['Content-Type' => $result['mime']],
        );
    }
}
