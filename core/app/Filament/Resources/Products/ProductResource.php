<?php

namespace App\Filament\Resources\Products;

use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Models\Product;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
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

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shopping-bag';
    protected static string|UnitEnum|null   $navigationGroup = 'Merchandise & Store';
    protected static ?int                  $navigationSort  = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Product Details')->schema([
                    TextInput::make('name')
                        ->label('Product Name')
                        ->required()
                        ->maxLength(255),

                    TextInput::make('slug')
                        ->label('URL Slug')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(255)
                        ->helperText('Product identifier in store URL (/store/{slug}).'),

                    Select::make('type')
                        ->label('Product Type')
                        ->options([
                            'physical' => 'Physical Merchandise',
                            'digital'  => 'Digital Product',
                        ])
                        ->default('physical')
                        ->required(),

                    Toggle::make('is_published')
                        ->label('Published in Store')
                        ->default(true),

                    TextInput::make('sort_order')
                        ->label('Sort Order')
                        ->numeric()
                        ->default(0),

                    Textarea::make('description')
                        ->label('Description')
                        ->rows(3)
                        ->maxLength(1500)
                        ->columnSpanFull(),
                ])->columns(2),

                Section::make('Variants, Sizes & Inventory')->schema([
                    Repeater::make('variants')
                        ->relationship('variants')
                        ->schema([
                            TextInput::make('sku')
                                ->label('SKU')
                                ->required()
                                ->maxLength(60),

                            TextInput::make('label')
                                ->label('Variant Label')
                                ->required()
                                ->maxLength(100)
                                ->helperText('e.g. Size M, Size L, Vinyl, etc.'),

                            TextInput::make('price_cents')
                                ->label('Price (USD Cents)')
                                ->numeric()
                                ->required()
                                ->minValue(0)
                                ->helperText('e.g. 3500 for $35.00 USD.'),

                            TextInput::make('stock_quantity')
                                ->label('Stock Qty')
                                ->numeric()
                                ->default(0)
                                ->required()
                                ->helperText('-1 for unlimited.'),

                            TextInput::make('color')
                                ->label('Color')
                                ->maxLength(50),

                            TextInput::make('size')
                                ->label('Size')
                                ->maxLength(30),

                            Toggle::make('is_active')
                                ->label('Available')
                                ->default(true),
                        ])
                        ->columns(4)
                        ->defaultItems(1)
                        ->collapsible(),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('slug')
                    ->copyable()
                    ->color('gray'),

                TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'physical' => 'warning',
                        'digital'  => 'info',
                        default    => 'gray',
                    }),

                IconColumn::make('is_published')
                    ->label('Published')
                    ->boolean(),

                TextColumn::make('variants_count')
                    ->counts('variants')
                    ->label('Variants'),

                TextColumn::make('sort_order')
                    ->label('Order')
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        'physical' => 'Physical',
                        'digital'  => 'Digital',
                    ]),
                TernaryFilter::make('is_published')
                    ->label('Published'),
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
            'index'  => ListProducts::route('/'),
            'create' => CreateProduct::route('/create'),
            'edit'   => EditProduct::route('/{record}/edit'),
        ];
    }
}
