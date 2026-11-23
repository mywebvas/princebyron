<?php

namespace App\Filament\Resources\BookingRequests;

use App\Filament\Resources\BookingRequests\Pages\CreateBookingRequest;
use App\Filament\Resources\BookingRequests\Pages\EditBookingRequest;
use App\Filament\Resources\BookingRequests\Pages\ListBookingRequests;
use App\Models\AuditEvent;
use App\Models\BookingRequest;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class BookingRequestResource extends Resource
{
    protected static ?string $model = BookingRequest::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';
    protected static string|UnitEnum|null   $navigationGroup = 'Ministry & Bookings';
    protected static ?int                  $navigationSort  = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Request Status & Reference')->schema([
                    TextInput::make('reference')
                        ->label('Booking Reference')
                        ->disabled()
                        ->dehydrated(false),

                    Select::make('status')
                        ->label('Workflow Status')
                        ->options([
                            BookingRequest::STATUS_NEW       => 'New Inquiry',
                            BookingRequest::STATUS_REVIEWING => 'Under Review',
                            BookingRequest::STATUS_QUOTED    => 'Quotation Sent',
                            BookingRequest::STATUS_ACCEPTED  => 'Accepted / Confirmed',
                            BookingRequest::STATUS_DECLINED  => 'Declined',
                            BookingRequest::STATUS_CANCELLED => 'Cancelled',
                        ])
                        ->required(),

                    DateTimePicker::make('owner_reviewed_at')
                        ->label('Reviewed At')
                        ->nullable(),
                ])->columns(3),

                Section::make('Contact & Organisation')->schema([
                    TextInput::make('contact_name')
                        ->label('Contact Person')
                        ->required()
                        ->maxLength(255),

                    TextInput::make('contact_email')
                        ->label('Contact Email')
                        ->email()
                        ->required()
                        ->maxLength(255),

                    TextInput::make('contact_phone')
                        ->label('Phone Number')
                        ->maxLength(50),

                    TextInput::make('organisation')
                        ->label('Church / Organisation')
                        ->maxLength(255),
                ])->columns(2),

                Section::make('Event Logistics')->schema([
                    TextInput::make('event_name')
                        ->label('Event Name')
                        ->maxLength(255),

                    TextInput::make('event_type')
                        ->label('Event Type')
                        ->maxLength(100),

                    DatePicker::make('event_date')
                        ->label('Target Date')
                        ->nullable(),

                    TextInput::make('expected_audience')
                        ->label('Expected Attendance')
                        ->numeric()
                        ->nullable(),

                    TextInput::make('event_city')
                        ->label('City')
                        ->maxLength(100),

                    TextInput::make('event_state')
                        ->label('State / Province')
                        ->maxLength(100),

                    TextInput::make('event_country')
                        ->label('Country')
                        ->maxLength(2),
                ])->columns(3),

                Section::make('Event Scope & Internal Notes')->schema([
                    Textarea::make('event_description')
                        ->label('Event Description')
                        ->rows(3)
                        ->columnSpanFull(),

                    Textarea::make('requirements')
                        ->label('Specific Technical or Ministry Requirements')
                        ->rows(2)
                        ->columnSpanFull(),

                    Textarea::make('owner_notes')
                        ->label('Owner Internal Notes')
                        ->rows(3)
                        ->helperText('Private ministry notes; never shared with inquiry contact.')
                        ->columnSpanFull(),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference')
                    ->label('Reference')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                TextColumn::make('contact_name')
                    ->label('Contact')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('organisation')
                    ->label('Organisation')
                    ->searchable(),

                TextColumn::make('event_date')
                    ->label('Event Date')
                    ->date()
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        BookingRequest::STATUS_ACCEPTED  => 'success',
                        BookingRequest::STATUS_QUOTED    => 'info',
                        BookingRequest::STATUS_REVIEWING => 'warning',
                        BookingRequest::STATUS_DECLINED  => 'danger',
                        BookingRequest::STATUS_CANCELLED => 'gray',
                        default                          => 'primary',
                    }),

                TextColumn::make('created_at')
                    ->label('Received')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        BookingRequest::STATUS_NEW       => 'New',
                        BookingRequest::STATUS_REVIEWING => 'Reviewing',
                        BookingRequest::STATUS_QUOTED    => 'Quoted',
                        BookingRequest::STATUS_ACCEPTED  => 'Accepted',
                        BookingRequest::STATUS_DECLINED  => 'Declined',
                        BookingRequest::STATUS_CANCELLED => 'Cancelled',
                    ]),
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
            'index'  => ListBookingRequests::route('/'),
            'create' => CreateBookingRequest::route('/create'),
            'edit'   => EditBookingRequest::route('/{record}/edit'),
        ];
    }
}
