<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SongResource\Pages;
use App\Models\Song;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Owner-only Filament resource for managing the music catalog.
 *
 * Owner may configure: title, free/paid, price, published status, format availability.
 * Audio file storage paths are placeholder text fields until storage integration (Phase 4).
 * Prices are stored as integer cents — the form accepts dollars and converts.
 */
class SongResource extends Resource
{
    protected static ?string $model = Song::class;
    protected static string | BackedEnum | null $navigationIcon  = 'heroicon-o-musical-note';
    protected static string | UnitEnum | null $navigationGroup = 'Music Catalog';
    protected static ?int    $navigationSort  = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Song Details')->schema([
                Forms\Components\TextInput::make('title')
                    ->required()->maxLength(255),

                Forms\Components\TextInput::make('slug')
                    ->required()->maxLength(255)->unique(ignoreRecord: true)
                    ->helperText('URL-friendly identifier. Auto-generated from title if left blank.'),

                Forms\Components\Textarea::make('description')
                    ->rows(3)->maxLength(1000)->columnSpanFull(),
            ])->columns(2),

            Section::make('Pricing & Availability')->schema([
                Forms\Components\Toggle::make('is_free')
                    ->label('Free download (no purchase required)')
                    ->live(),

                Forms\Components\TextInput::make('price_cents')
                    ->label('Price (USD cents, e.g. 199 = $1.99)')
                    ->numeric()->minValue(0)->maxValue(99999)
                    ->helperText('Enter price in cents. Leave blank when free.')
                    ->visible(fn (Forms\Get $get) => ! $get('is_free')),

                Forms\Components\Toggle::make('is_published')
                    ->label('Published (visible to public)'),

                Forms\Components\Toggle::make('has_mp3')->label('MP3 available'),
                Forms\Components\Toggle::make('has_wav')->label('WAV available'),

                Forms\Components\TextInput::make('sort_order')
                    ->numeric()->default(0)->label('Sort order'),
            ])->columns(2),

            Section::make('Storage Paths (Phase 4 — placeholders)')
                ->description('Enter file storage paths after upload integration is configured.')
                ->collapsed()
                ->schema([
                    Forms\Components\TextInput::make('preview_storage_path')
                        ->label('Preview clip path (private storage)')->maxLength(500),
                    Forms\Components\TextInput::make('download_storage_path_mp3')
                        ->label('MP3 download path (private storage)')->maxLength(500),
                    Forms\Components\TextInput::make('download_storage_path_wav')
                        ->label('WAV download path (private storage)')->maxLength(500),
                    Forms\Components\TextInput::make('cover_storage_path')
                        ->label('Cover art path (public storage)')->maxLength(500),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sort_order')->sortable()->label('#'),
                Tables\Columns\TextColumn::make('title')->searchable()->sortable(),
                Tables\Columns\IconColumn::make('is_free')->boolean()->label('Free'),
                Tables\Columns\TextColumn::make('price_cents')
                    ->label('Price')
                    ->formatStateUsing(fn ($state) => $state ? '$' . number_format($state / 100, 2) : '—'),
                Tables\Columns\IconColumn::make('is_published')->boolean()->label('Published'),
                Tables\Columns\IconColumn::make('has_mp3')->boolean()->label('MP3'),
                Tables\Columns\IconColumn::make('has_wav')->boolean()->label('WAV'),
                Tables\Columns\TextColumn::make('updated_at')->since()->sortable(),
            ])
            ->defaultSort('sort_order')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_published'),
                Tables\Filters\TernaryFilter::make('is_free'),
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
            'index'  => Pages\ListSongs::route('/'),
            'create' => Pages\CreateSong::route('/create'),
            'edit'   => Pages\EditSong::route('/{record}/edit'),
        ];
    }
}
