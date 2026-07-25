<?php

namespace App\Filament\Resources;

use App\Features\Admin\Services\AdminAuditLogger;
use App\Features\Admin\Support\AdminPermission;
use App\Features\Kyc\DTOs\ReviewKycData;
use App\Features\Kyc\Models\KycSubmission;
use App\Features\Kyc\Services\KycService;
use App\Filament\Concerns\AuthorizesAdminAccess;
use App\Filament\Resources\KycSubmissionResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class KycSubmissionResource extends Resource
{
    use AuthorizesAdminAccess;

    protected static ?string $model = KycSubmission::class;

    protected static ?string $navigationIcon = 'heroicon-o-identification';

    protected static ?string $navigationGroup = 'Compliance';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'KYC Submission';

    protected static ?string $pluralModelLabel = 'KYC Submissions';

    public static function canViewAny(): bool
    {
        return static::canAccessWithPermission(AdminPermission::REVIEW_KYC);
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
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('full_legal_name')->disabled(),
            Forms\Components\TextInput::make('document_type')->disabled(),
            Forms\Components\TextInput::make('document_number')->disabled(),
            Forms\Components\TextInput::make('status')->disabled(),
            Forms\Components\TextInput::make('face_match_status')->disabled(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.username')->label('User')->searchable(),
                Tables\Columns\TextColumn::make('full_legal_name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('document_type')->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        KycSubmission::DOCUMENT_NATIONAL_ID => 'National ID',
                        KycSubmission::DOCUMENT_PASSPORT => 'Passport',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('document_number')->searchable()->copyable(),
                Tables\Columns\TextColumn::make('face_match_score')->label('Face score')->numeric(decimalPlaces: 1),
                Tables\Columns\TextColumn::make('face_match_status')->label('Face match')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        KycSubmission::FACE_MATCH_MATCHED => 'success',
                        KycSubmission::FACE_MATCH_FAILED => 'danger',
                        default => 'warning',
                    }),
                Tables\Columns\IconColumn::make('withdrawal_verified')->boolean()->label('Withdraw OK'),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        KycSubmission::STATUS_APPROVED => 'success',
                        KycSubmission::STATUS_REJECTED => 'danger',
                        KycSubmission::STATUS_UNDER_REVIEW => 'info',
                        default => 'warning',
                    }),
                Tables\Columns\TextColumn::make('submitted_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        KycSubmission::STATUS_PENDING => 'Pending',
                        KycSubmission::STATUS_UNDER_REVIEW => 'Under review',
                        KycSubmission::STATUS_APPROVED => 'Approved',
                        KycSubmission::STATUS_REJECTED => 'Rejected',
                    ]),
                Tables\Filters\SelectFilter::make('document_type')
                    ->options([
                        KycSubmission::DOCUMENT_NATIONAL_ID => 'National ID',
                        KycSubmission::DOCUMENT_PASSPORT => 'Passport',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('viewFront')
                    ->label('ID front')
                    ->icon('heroicon-o-photo')
                    ->url(fn (KycSubmission $record): ?string => $record->documentFrontUrl())
                    ->openUrlInNewTab()
                    ->visible(fn (KycSubmission $record): bool => filled($record->document_front_path)),
                Tables\Actions\Action::make('viewBack')
                    ->label('ID back')
                    ->icon('heroicon-o-photo')
                    ->url(fn (KycSubmission $record): ?string => $record->documentBackUrl())
                    ->openUrlInNewTab()
                    ->visible(fn (KycSubmission $record): bool => filled($record->document_back_path)),
                Tables\Actions\Action::make('viewSelfie')
                    ->label('Selfie')
                    ->icon('heroicon-o-camera')
                    ->url(fn (KycSubmission $record): ?string => $record->selfieUrl())
                    ->openUrlInNewTab()
                    ->visible(fn (KycSubmission $record): bool => filled($record->selfie_path)),
                Tables\Actions\Action::make('markUnderReview')
                    ->label('Start review')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->visible(fn (KycSubmission $record): bool => $record->status === KycSubmission::STATUS_PENDING
                        && (auth()->user()?->can(AdminPermission::REVIEW_KYC) ?? false))
                    ->action(function (KycSubmission $record): void {
                        $admin = auth()->user();
                        $reviewed = app(KycService::class)->review(
                            $record,
                            $admin,
                            new ReviewKycData(status: KycSubmission::STATUS_UNDER_REVIEW),
                        );
                        app(AdminAuditLogger::class)->log($admin, 'kyc.under_review', $reviewed);
                        Notification::make()->title('KYC marked under review')->success()->send();
                    }),
                Tables\Actions\Action::make('approve')
                    ->color('success')
                    ->icon('heroicon-o-check')
                    ->requiresConfirmation()
                    ->modalHeading('Approve KYC')
                    ->modalDescription('Approves identity verification and enables withdrawals when marked verified.')
                    ->form([
                        Forms\Components\Select::make('face_match_status')
                            ->label('Face match')
                            ->options([
                                KycSubmission::FACE_MATCH_MATCHED => 'Matched',
                                KycSubmission::FACE_MATCH_REQUIRES_MANUAL => 'Manual OK',
                            ])
                            ->default(KycSubmission::FACE_MATCH_MATCHED)
                            ->required(),
                        Forms\Components\TextInput::make('face_match_score')
                            ->label('Face match score')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->default(fn (KycSubmission $record) => $record->face_match_score),
                        Forms\Components\Toggle::make('withdrawal_verified')
                            ->label('Enable withdrawals')
                            ->default(true),
                        Forms\Components\Textarea::make('notes')->label('Notes'),
                    ])
                    ->visible(fn (KycSubmission $record): bool => in_array($record->status, [
                        KycSubmission::STATUS_PENDING,
                        KycSubmission::STATUS_UNDER_REVIEW,
                    ], true) && (auth()->user()?->can(AdminPermission::REVIEW_KYC) ?? false))
                    ->action(function (KycSubmission $record, array $data): void {
                        $admin = auth()->user();
                        $reviewed = app(KycService::class)->review(
                            $record,
                            $admin,
                            new ReviewKycData(
                                status: KycSubmission::STATUS_APPROVED,
                                notes: $data['notes'] ?? null,
                                faceMatchStatus: $data['face_match_status'] ?? KycSubmission::FACE_MATCH_MATCHED,
                                faceMatchScore: isset($data['face_match_score'])
                                    ? (float) $data['face_match_score']
                                    : null,
                                withdrawalVerified: (bool) ($data['withdrawal_verified'] ?? true),
                            ),
                        );
                        app(AdminAuditLogger::class)->log($admin, 'kyc.approved', $reviewed);
                        Notification::make()->title('KYC approved')->success()->send();
                    }),
                Tables\Actions\Action::make('reject')
                    ->color('danger')
                    ->icon('heroicon-o-x-mark')
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Select::make('face_match_status')
                            ->label('Face match')
                            ->options([
                                KycSubmission::FACE_MATCH_FAILED => 'Failed',
                                KycSubmission::FACE_MATCH_REQUIRES_MANUAL => 'Needs resubmit',
                            ])
                            ->default(KycSubmission::FACE_MATCH_FAILED),
                        Forms\Components\Textarea::make('notes')->label('Reason')->required(),
                    ])
                    ->visible(fn (KycSubmission $record): bool => in_array($record->status, [
                        KycSubmission::STATUS_PENDING,
                        KycSubmission::STATUS_UNDER_REVIEW,
                    ], true) && (auth()->user()?->can(AdminPermission::REVIEW_KYC) ?? false))
                    ->action(function (KycSubmission $record, array $data): void {
                        $admin = auth()->user();
                        $reviewed = app(KycService::class)->review(
                            $record,
                            $admin,
                            new ReviewKycData(
                                status: KycSubmission::STATUS_REJECTED,
                                notes: $data['notes'] ?? null,
                                faceMatchStatus: $data['face_match_status'] ?? KycSubmission::FACE_MATCH_FAILED,
                            ),
                        );
                        app(AdminAuditLogger::class)->log($admin, 'kyc.rejected', $reviewed, [
                            'notes' => $data['notes'] ?? null,
                        ]);
                        Notification::make()->title('KYC rejected')->success()->send();
                    }),
            ])
            ->bulkActions([])
            ->defaultSort('submitted_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['user', 'reviewer']));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListKycSubmissions::route('/'),
        ];
    }
}
