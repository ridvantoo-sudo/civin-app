<?php

namespace App\Filament\Widgets\Security;

use App\Features\Security\Models\SecurityScore;
use App\Filament\Widgets\Security\Concerns\SecurityWidget;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class TopRiskyAccountsWidget extends TableWidget
{
    use SecurityWidget;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Highest-risk accounts';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => SecurityScore::query()->with('user')->orderByDesc('risk_score'))
            ->defaultPaginationPageOption(10)
            ->columns([
                Tables\Columns\TextColumn::make('user.username')
                    ->label('Account')
                    ->placeholder('—')
                    ->searchable(),
                Tables\Columns\TextColumn::make('band')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        SecurityScore::BAND_CRITICAL => 'danger',
                        SecurityScore::BAND_HIGH => 'warning',
                        SecurityScore::BAND_ELEVATED => 'info',
                        default => 'success',
                    }),
                Tables\Columns\TextColumn::make('risk_score')->numeric(1)->sortable(),
                Tables\Columns\TextColumn::make('fraud_score')->numeric(1)->sortable(),
                Tables\Columns\TextColumn::make('bot_score')->numeric(1)->sortable(),
                Tables\Columns\TextColumn::make('trust_score')->numeric(1)->sortable(),
                Tables\Columns\TextColumn::make('alert_count')->label('Alerts')->sortable(),
                Tables\Columns\TextColumn::make('last_signal_at')->label('Last signal')->since(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('band')->options([
                    SecurityScore::BAND_CRITICAL => 'Critical',
                    SecurityScore::BAND_HIGH => 'High',
                    SecurityScore::BAND_ELEVATED => 'Elevated',
                    SecurityScore::BAND_LOW => 'Low',
                ]),
            ]);
    }
}
