<?php

namespace App\Filament\Resources\Settings;

use App\Filament\Resources\Settings\Pages\CreateSetting;
use App\Filament\Resources\Settings\Pages\EditSetting;
use App\Filament\Resources\Settings\Pages\ListSettings;
use App\Models\Setting;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

class SettingResource extends Resource
{
    protected static ?string $model = Setting::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static string|UnitEnum|null   $navigationGroup = 'Settings & Config';
    protected static ?int                  $navigationSort  = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Setting Definition')->schema([
                    Select::make('group')
                        ->label('Namespace Group')
                        ->options([
                            'general'    => 'General Site Configuration',
                            'commerce'   => 'Commerce & Pricing',
                            'payment'    => 'Payment & Banking',
                            'shipping'   => 'Shipping Policies',
                            'social'     => 'Social Media Links',
                            'appearance' => 'Appearance & Layout',
                        ])
                        ->required(),

                    TextInput::make('key')
                        ->label('Setting Key')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->disabled(fn (string $operation): bool => $operation === 'edit')
                        ->dehydrated()
                        ->maxLength(100)
                        ->helperText('Unique config key (e.g. site_name, bank_instructions).'),

                    TextInput::make('label')
                        ->label('Admin Label')
                        ->maxLength(255)
                        ->helperText('Human-readable description for owner.'),

                    Select::make('type')
                        ->label('Data Type')
                        ->options([
                            Setting::TYPE_STRING    => 'String (Plain text)',
                            Setting::TYPE_BOOLEAN   => 'Boolean (1 or 0)',
                            Setting::TYPE_INTEGER   => 'Integer',
                            Setting::TYPE_JSON      => 'JSON Structure',
                            Setting::TYPE_ENCRYPTED => 'Encrypted Secret',
                        ])
                        ->required(),

                    Toggle::make('is_public')
                        ->label('Exposed to Public Frontend')
                        ->helperText('Enable only for safe frontend settings (e.g. site name, social links). Never for secrets.'),
                ])->columns(2),

                Section::make('Setting Value')->schema([
                    Textarea::make('value')
                        ->label('Value')
                        ->rows(4)
                        ->helperText('Structured value. If type is Encrypted, it will be encrypted at rest.')
                        ->columnSpanFull(),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('group')
                    ->badge()
                    ->sortable(),

                TextColumn::make('key')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                TextColumn::make('label')
                    ->searchable(),

                TextColumn::make('type')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('value')
                    ->label('Value')
                    ->limit(40)
                    ->formatStateUsing(fn ($state, Setting $record) => $record->type === Setting::TYPE_ENCRYPTED ? '•••••••• (Encrypted)' : $state),

                IconColumn::make('is_public')
                    ->label('Public')
                    ->boolean(),

                TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('group')
            ->filters([
                SelectFilter::make('group')
                    ->options([
                        'general'    => 'General',
                        'commerce'   => 'Commerce',
                        'payment'    => 'Payment',
                        'shipping'   => 'Shipping',
                        'social'     => 'Social',
                        'appearance' => 'Appearance',
                    ]),
                TernaryFilter::make('is_public')
                    ->label('Public State'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListSettings::route('/'),
            'create' => CreateSetting::route('/create'),
            'edit'   => EditSetting::route('/{record}/edit'),
        ];
    }
}
