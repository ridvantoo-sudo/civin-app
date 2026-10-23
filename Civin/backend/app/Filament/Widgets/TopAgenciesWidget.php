<?php

namespace App\Filament\Widgets;

use App\Features\Agency\Models\Agency;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class TopAgenciesWidget extends BaseWidget
{
    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Top Agencies';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Agency::query()
                    ->orderByDesc('total_gross_earnings')
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('owner.username')->label('Owner'),
                Tables\Columns\TextColumn::make('hosts_count')->label('Hosts')->numeric(),
                Tables\Columns\TextColumn::make('total_gross_earnings')->label('Gross earnings')->numeric(),
                Tables\Columns\TextColumn::make('total_commission')->label('Commission')->numeric(),
                Tables\Columns\TextColumn::make('status')->badge(),
            ])
            ->paginated(false);
    }
}
