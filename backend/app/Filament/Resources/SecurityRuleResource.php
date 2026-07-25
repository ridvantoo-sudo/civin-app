<?php

namespace App\Filament\Resources;

use App\Features\Security\Models\SecurityRule;
use App\Features\Security\Rules\RuleProvider;
use App\Features\Security\Support\SecurityAction;
use App\Features\Security\Support\SecurityPermission;
use App\Features\Security\Support\SecuritySeverity;
use App\Filament\Concerns\AuthorizesSecurityAccess;
use App\Filament\Resources\SecurityRuleResource\Pages;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Rules Manager.
 *
 * Rows here override the defaults in config/security_platform.php by key.
 * Saving flushes the compiled rule set, so changes are live immediately.
 */
class SecurityRuleResource extends Resource
{
    use AuthorizesSecurityAccess;

    protected static ?string $model = SecurityRule::class;

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?string $navigationGroup = 'Security';

    protected static ?string $navigationLabel = 'Rules Manager';

    protected static ?string $modelLabel = 'security rule';

    protected static ?string $slug = 'security-rules';

    protected static ?int $navigationSort = 9;

    public static function canViewAny(): bool
    {
        return static::canViewSecurity();
    }

    public static function canCreate(): bool
    {
        return static::allowsSecurity(SecurityPermission::MANAGE_SECURITY_RULES);
    }

    public static function canEdit($record): bool
    {
        return static::allowsSecurity(SecurityPermission::MANAGE_SECURITY_RULES);
    }

    public static function canDelete($record): bool
    {
        return static::allowsSecurity(SecurityPermission::MANAGE_SECURITY_RULES);
    }

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('key')
                ->required()
                ->maxLength(96)
                ->helperText('Matches a default rule key to override it, or introduces a new rule.')
                ->datalist(fn (RuleProvider $rules): array => array_map(
                    static fn ($rule): string => $rule->key,
                    $rules->all(),
                )),
            Forms\Components\TextInput::make('name')->required()->maxLength(191),
            Forms\Components\TextInput::make('signal')
                ->required()
                ->maxLength(96)
                ->helperText('Signal identifier emitted by a scanner, e.g. wallet.fraud'),
            Forms\Components\Select::make('severity')
                ->required()
                ->default(SecuritySeverity::MEDIUM)
                ->options(array_combine(SecuritySeverity::all(), array_map('ucfirst', SecuritySeverity::all()))),
            Forms\Components\Select::make('action')
                ->required()
                ->default(SecurityAction::WARN)
                ->options(array_combine(SecurityAction::all(), array_map('ucfirst', SecurityAction::all()))),
            Forms\Components\TextInput::make('threshold')
                ->numeric()
                ->required()
                ->default(70)
                ->minValue(0)
                ->maxValue(100)
                ->helperText('Minimum detector confidence, 0-100, before this rule fires.'),
            Forms\Components\TextInput::make('priority')
                ->numeric()
                ->required()
                ->default(100)
                ->helperText('Lower runs first when several rules match.'),
            Forms\Components\Toggle::make('is_enabled')->default(true),
            Forms\Components\Textarea::make('description')->maxLength(500)->columnSpanFull(),
            Forms\Components\KeyValue::make('conditions')
                ->label('Extra conditions')
                ->helperText('Optional equality checks against the event, e.g. surface = wallet')
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('priority', 'asc')
            ->columns([
                Tables\Columns\TextColumn::make('key')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('name')->wrap()->searchable(),
                Tables\Columns\TextColumn::make('signal')->badge()->color('gray')->searchable(),
                Tables\Columns\TextColumn::make('severity')->badge(),
                Tables\Columns\TextColumn::make('action')->badge(),
                Tables\Columns\TextColumn::make('threshold')->numeric(1)->sortable(),
                Tables\Columns\TextColumn::make('priority')->sortable(),
                Tables\Columns\IconColumn::make('is_enabled')->label('Enabled')->boolean(),
                Tables\Columns\TextColumn::make('match_count')->label('Matches')->sortable(),
                Tables\Columns\TextColumn::make('last_matched_at')->label('Last match')->since()->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_enabled')->label('Enabled'),
                Tables\Filters\SelectFilter::make('action')->options(
                    array_combine(SecurityAction::all(), array_map('ucfirst', SecurityAction::all())),
                ),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSecurityRules::route('/'),
            'create' => Pages\CreateSecurityRule::route('/create'),
            'edit' => Pages\EditSecurityRule::route('/{record}/edit'),
        ];
    }
}
