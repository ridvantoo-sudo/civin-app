<?php

namespace App\Filament\Resources;

use App\Features\Admin\Support\AdminPermission;
use App\Features\Ads\Models\AdCampaign;
use App\Filament\Concerns\AuthorizesAdminAccess;
use App\Filament\Resources\AdCampaignResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AdCampaignResource extends Resource
{
    use AuthorizesAdminAccess;

    protected static ?string $model = AdCampaign::class;

    protected static ?string $navigationIcon = 'heroicon-o-megaphone';

    protected static ?string $navigationGroup = 'Economy';

    protected static ?int $navigationSort = 6;

    protected static ?string $navigationLabel = 'Ads';

    protected static ?string $modelLabel = 'Ad campaign';

    public static function canViewAny(): bool
    {
        return static::canAccessWithPermission(AdminPermission::MANAGE_ADS);
    }

    public static function canCreate(): bool
    {
        return static::canAccessWithPermission(AdminPermission::MANAGE_ADS);
    }

    public static function canEdit($record): bool
    {
        return static::canAccessWithPermission(AdminPermission::MANAGE_ADS);
    }

    public static function canDelete($record): bool
    {
        return static::canAccessWithPermission(AdminPermission::MANAGE_ADS);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Creative')
                    ->schema([
                        Forms\Components\TextInput::make('title')->required()->maxLength(160),
                        Forms\Components\Textarea::make('body')->rows(3)->maxLength(2000),
                        Forms\Components\Select::make('placement')
                            ->options([
                                AdCampaign::PLACEMENT_FEED_POST => 'Feed post',
                                AdCampaign::PLACEMENT_LIVE_BANNER => 'Live banner',
                                AdCampaign::PLACEMENT_LIVE_INTERSTITIAL => 'Live interstitial',
                                AdCampaign::PLACEMENT_LIVE_REWARDED => 'Live rewarded',
                                AdCampaign::PLACEMENT_CHAT_BANNER => 'Chat banner',
                                AdCampaign::PLACEMENT_CHAT_ANNOUNCEMENT => 'Chat announcement',
                            ])
                            ->required(),
                        Forms\Components\TextInput::make('media_url')->url()->maxLength(2048),
                        Forms\Components\Select::make('media_type')
                            ->options([
                                AdCampaign::MEDIA_IMAGE => 'Image',
                                AdCampaign::MEDIA_VIDEO => 'Video',
                            ])
                            ->default(AdCampaign::MEDIA_IMAGE)
                            ->required(),
                        Forms\Components\TextInput::make('cta_label')->maxLength(80),
                        Forms\Components\TextInput::make('cta_url')->url()->maxLength(2048),
                    ])->columns(2),
                Forms\Components\Section::make('Targeting & schedule')
                    ->schema([
                        Forms\Components\Select::make('target_type')
                            ->options([
                                AdCampaign::TARGET_ALL => 'All users',
                                AdCampaign::TARGET_COUNTRY => 'Country',
                                AdCampaign::TARGET_LEVEL_RANGE => 'Level range',
                                AdCampaign::TARGET_LIVE_ROOM => 'Live room',
                                AdCampaign::TARGET_VOICE_ROOM => 'Voice room',
                                AdCampaign::TARGET_FAMILY => 'Family chat',
                            ])
                            ->default(AdCampaign::TARGET_ALL)
                            ->required(),
                        Forms\Components\KeyValue::make('target_payload')
                            ->keyLabel('Key')
                            ->valueLabel('Value')
                            ->nullable(),
                        Forms\Components\DateTimePicker::make('starts_at'),
                        Forms\Components\DateTimePicker::make('ends_at'),
                        Forms\Components\TextInput::make('priority')->numeric()->minValue(0)->default(0),
                        Forms\Components\TextInput::make('budget_impressions')->numeric()->minValue(1),
                        Forms\Components\TextInput::make('budget_clicks')->numeric()->minValue(1),
                    ])->columns(2),
                Forms\Components\Section::make('Status')
                    ->schema([
                        Forms\Components\Toggle::make('is_admin_campaign')->default(true),
                        Forms\Components\Select::make('status')
                            ->options([
                                AdCampaign::STATUS_DRAFT => 'Draft',
                                AdCampaign::STATUS_PENDING_REVIEW => 'Pending review',
                                AdCampaign::STATUS_ACTIVE => 'Active',
                                AdCampaign::STATUS_PAUSED => 'Paused',
                                AdCampaign::STATUS_ENDED => 'Ended',
                                AdCampaign::STATUS_REJECTED => 'Rejected',
                            ])
                            ->required(),
                        Forms\Components\TextInput::make('rejection_reason')->maxLength(500),
                        Forms\Components\TextInput::make('impressions_count')->numeric()->disabled(),
                        Forms\Components\TextInput::make('clicks_count')->numeric()->disabled(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')->searchable()->limit(40),
                Tables\Columns\TextColumn::make('placement')->badge(),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\IconColumn::make('is_admin_campaign')->boolean()->label('Admin'),
                Tables\Columns\TextColumn::make('impressions_count')->label('Impr.')->numeric()->sortable(),
                Tables\Columns\TextColumn::make('clicks_count')->label('Clicks')->numeric()->sortable(),
                Tables\Columns\TextColumn::make('priority')->sortable(),
                Tables\Columns\TextColumn::make('starts_at')->dateTime()->toggleable(),
                Tables\Columns\TextColumn::make('ends_at')->dateTime()->toggleable(),
                Tables\Columns\TextColumn::make('updated_at')->since()->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('placement')
                    ->options(array_combine(AdCampaign::placements(), AdCampaign::placements())),
                Tables\Filters\SelectFilter::make('status')
                    ->options(array_combine(AdCampaign::statuses(), AdCampaign::statuses())),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('activate')
                    ->label('Activate')
                    ->icon('heroicon-o-play')
                    ->visible(fn (AdCampaign $record): bool => $record->status !== AdCampaign::STATUS_ACTIVE)
                    ->action(fn (AdCampaign $record) => $record->update(['status' => AdCampaign::STATUS_ACTIVE])),
                Tables\Actions\Action::make('pause')
                    ->label('Pause')
                    ->icon('heroicon-o-pause')
                    ->visible(fn (AdCampaign $record): bool => $record->status === AdCampaign::STATUS_ACTIVE)
                    ->action(fn (AdCampaign $record) => $record->update(['status' => AdCampaign::STATUS_PAUSED])),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAdCampaigns::route('/'),
            'create' => Pages\CreateAdCampaign::route('/create'),
            'edit' => Pages\EditAdCampaign::route('/{record}/edit'),
        ];
    }
}
