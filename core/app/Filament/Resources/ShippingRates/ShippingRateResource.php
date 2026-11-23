<?php

namespace App\Filament\Resources\ShippingRates;

use App\Filament\Resources\ShippingRates\Pages\CreateShippingRate;
use App\Filament\Resources\ShippingRates\Pages\EditShippingRate;
use App\Filament\Resources\ShippingRates\Pages\ListShippingRates;
use App\Models\ShippingRate;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

class ShippingRateResource extends Resource
{
    protected static ?string $model = ShippingRate::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-truck';
    protected static string|UnitEnum|null   $navigationGroup = 'Commerce & Orders';
    protected static ?int                  $navigationSort  = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Shipping Zone & Rate Rules')->schema([
                    TextInput::make('name')
                        ->label('Rate Name')
                        ->required()
                        ->maxLength(255)
                        ->helperText('e.g. US Domestic Standard, International Priority'),

                    TextInput::make('country_code')
                        ->label('Country Code (ISO 2)')
                        ->maxLength(2)
                        ->helperText('2-letter ISO country code (e.g. US, CA, GB). Leave blank for Worldwide / Wildcard rate.'),

                    TextInput::make('rate_cents')
                        ->label('Shipping Cost (USD Cents)')
                        ->numeric()
                        ->required()
                        ->minValue(0)
                        ->helperText('Flat shipping rate in cents (e.g. 500 = $5.00 USD). Computed server-side.'),

                    TextInput::make('free_above_cents')
                        ->label('Free Shipping Threshold (USD Cents)')
                        ->numeric()
                        ->nullable()
                        ->minValue(0)
                        ->helperText('Orders with subtotal at or above this amount ship free (e.g. 5000 = $50.00). Leave blank if never free.'),

                    Toggle::make('is_active')
                        ->label('Active Zone')
                        ->default(true),

                    TextInput::make('sort_order')
                        ->label('Evaluation Sort Order')
                        ->numeric()
                        ->default(0),
                ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('country_code')
                    ->label('Zone')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? strtoupper($state) : 'Worldwide (Any)'),

                TextColumn::make('rate_cents')
                    ->label('Flat Rate')
                    ->formatStateUsing(fn ($state) => '$' . number_format($state / 100, 2) . ' USD')
                    ->sortable(),

                TextColumn::make('free_above_cents')
                    ->label('Free Above')
                    ->formatStateUsing(fn ($state) => $state ? '$' . number_format($state / 100, 2) . ' USD' : 'Never')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                TextColumn::make('sort_order')
                    ->label('Order')
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Active Status'),
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
            'index'  => ListShippingRates::route('/'),
            'create' => CreateShippingRate::route('/create'),
            'edit'   => EditShippingRate::route('/{record}/edit'),
        ];
    }
}
