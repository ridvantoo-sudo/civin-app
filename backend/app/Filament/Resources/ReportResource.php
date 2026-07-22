<?php

namespace App\Filament\Resources;

use App\Features\Admin\Services\AdminAuditLogger;
use App\Features\Admin\Support\AdminPermission;
use App\Features\Reports\DTOs\ReviewReportData;
use App\Features\Reports\Models\Report;
use App\Features\Reports\Services\ReportService;
use App\Filament\Concerns\AuthorizesAdminAccess;
use App\Filament\Resources\ReportResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ReportResource extends Resource
{
    use AuthorizesAdminAccess;

    protected static ?string $model = Report::class;

    protected static ?string $navigationIcon = 'heroicon-o-flag';

    protected static ?string $navigationGroup = 'Moderation';

    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        return static::canAccessWithPermission(AdminPermission::REVIEW_REPORTS);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return static::canAccessWithPermission(AdminPermission::REVIEW_REPORTS);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('category')->disabled(),
                Forms\Components\Textarea::make('details')->disabled()->columnSpanFull(),
                Forms\Components\TextInput::make('status')->disabled(),
                Forms\Components\Textarea::make('review_notes')->disabled()->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reporter.username')->label('Reporter')->searchable(),
                Tables\Columns\TextColumn::make('reportedUser.username')->label('Reported')->searchable(),
                Tables\Columns\TextColumn::make('category')->badge(),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'resolved' => 'success',
                        'dismissed' => 'gray',
                        'reviewing' => 'info',
                        default => 'warning',
                    }),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('reviewed_at')->dateTime()->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'reviewing' => 'Reviewing',
                        'resolved' => 'Resolved',
                        'dismissed' => 'Dismissed',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('review')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->form([
                        Forms\Components\Select::make('status')
                            ->options([
                                'reviewing' => 'Reviewing',
                                'resolved' => 'Resolved',
                                'dismissed' => 'Dismissed',
                            ])
                            ->required(),
                        Forms\Components\Textarea::make('notes')->label('Review notes'),
                    ])
                    ->visible(fn (Report $record): bool => in_array($record->status, ['pending', 'reviewing'], true))
                    ->action(function (Report $record, array $data): void {
                        $admin = auth()->user();
                        app(ReportService::class)->review(
                            $record,
                            $admin,
                            new ReviewReportData($data['status'], $data['notes'] ?? null),
                        );
                        app(AdminAuditLogger::class)->log($admin, 'report.reviewed', $record, $data);
                        Notification::make()->title('Report reviewed')->success()->send();
                    }),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['reporter', 'reportedUser', 'reviewer']));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReports::route('/'),
            'view' => Pages\ViewReport::route('/{record}'),
        ];
    }
}
