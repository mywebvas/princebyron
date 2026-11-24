<?php

namespace App\Filament\Resources\ContentSections;

use App\Filament\Resources\ContentSections\Pages\CreateContentSection;
use App\Filament\Resources\ContentSections\Pages\EditContentSection;
use App\Filament\Resources\ContentSections\Pages\ListContentSections;
use App\Models\ContentSection;
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

class ContentSectionResource extends Resource
{
    protected static ?string $model = ContentSection::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';
    protected static string|UnitEnum|null   $navigationGroup = 'Site Content & CMS';
    protected static ?int                  $navigationSort  = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Section Identification')->schema([
                    TextInput::make('section_key')
                        ->label('Section Key')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->disabled(fn (string $operation): bool => $operation === 'edit')
                        ->dehydrated()
                        ->helperText('Unique programmatic key (e.g. home_hero, about_bio, booking_banner).'),

                    Select::make('page')
                        ->label('Page')
                        ->options([
                            'home'    => 'Home',
                            'about'   => 'About',
                            'music'   => 'Music',
                            'store'   => 'Store',
                            'booking' => 'Booking',
                            // Legal pages (Phase 6). Without this option the seeded
                            // rows would be uneditable, and pb:readiness would fail
                            // on policies the owner had no way to write.
                            'legal'   => 'Legal / Policies',
                        ])
                        ->required(),
                ])->columns(2),

                Section::make('Structured Content')->schema([
                    TextInput::make('eyebrow')
                        ->label('Eyebrow / Subheading')
                        ->maxLength(100)
                        ->helperText('Short category badge or contextual intro.'),

                    TextInput::make('title')
                        ->label('Section Title')
                        ->required()
                        ->maxLength(255),

                    Textarea::make('body')
                        ->label('Body Copy')
                        ->rows(4)
                        ->maxLength(2000)
                        ->helperText('Structured plain text. Any HTML tags will be stripped automatically on save.')
                        ->columnSpanFull(),
                ])->columns(2),

                Section::make('Call to Action & Media')->schema([
                    TextInput::make('button_text')
                        ->label('Button Text')
                        ->maxLength(100),

                    TextInput::make('button_url')
                        ->label('Button Target URL')
                        ->maxLength(255),

                    TextInput::make('image_path')
                        ->label('Image Asset Path')
                        ->maxLength(500)
                        ->helperText('Relative path to approved asset (e.g. /assets/hero-byron.jpg).'),
                ])->columns(3),

                Section::make('Appearance & Ordering')->schema([
                    Select::make('brand_color')
                        ->label('Allowlisted Brand Color')
                        ->options(ContentSection::ALLOWED_COLORS)
                        ->default(ContentSection::COLOR_PRIMARY_DARK)
                        ->required()
                        ->helperText('Restricted to approved Prince Byron brand palette colors only.'),

                    Toggle::make('is_visible')
                        ->label('Visible on Site')
                        ->default(true),

                    TextInput::make('sort_order')
                        ->label('Sort Order')
                        ->numeric()
                        ->default(0),
                ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('page')
                    ->badge()
                    ->sortable(),

                TextColumn::make('section_key')
                    ->label('Key')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                TextColumn::make('title')
                    ->searchable()
                    ->limit(35),

                TextColumn::make('brand_color')
                    ->label('Brand Color')
                    ->badge()
                    ->formatStateUsing(fn ($state) => ContentSection::ALLOWED_COLORS[$state] ?? $state),

                IconColumn::make('is_visible')
                    ->label('Visible')
                    ->boolean(),

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
                SelectFilter::make('page')
                    ->options([
                        'home'    => 'Home',
                        'about'   => 'About',
                        'music'   => 'Music',
                        'store'   => 'Store',
                        'booking' => 'Booking',
                    ]),
                TernaryFilter::make('is_visible')
                    ->label('Visibility'),
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
            'index'  => ListContentSections::route('/'),
            'create' => CreateContentSection::route('/create'),
            'edit'   => EditContentSection::route('/{record}/edit'),
        ];
    }
}
