<?php

namespace App\Filament\Resources\AuditEvents;

use App\Filament\Resources\AuditEvents\Pages\ListAuditEvents;
use App\Models\AuditEvent;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class AuditEventResource extends Resource
{
    protected static ?string $model = AuditEvent::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';
    protected static string|UnitEnum|null   $navigationGroup = 'Security & Audit';
    protected static ?int                  $navigationSort  = 1;

    /**
     |--------------------------------------------------------------------------
     | Security: Audit records are strictly immutable (ASVS 5.0 L2)
     |--------------------------------------------------------------------------
     */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Event Summary')->schema([
                    TextInput::make('action')->label('Action')->disabled(),
                    TextInput::make('created_at')->label('Timestamp')->disabled(),
                    TextInput::make('actor.email')->label('Actor Email')->disabled(),
                    TextInput::make('ip_address')->label('IP Address')->disabled(),
                    TextInput::make('auditable_type')->label('Target Model')->disabled(),
                    TextInput::make('auditable_id')->label('Target ID')->disabled(),
                    TextInput::make('user_agent')->label('User Agent')->columnSpanFull()->disabled(),
                ])->columns(3),

                Section::make('Audit Payload Changes')->schema([
                    KeyValue::make('old_values')->label('Previous Values')->disabled(),
                    KeyValue::make('new_values')->label('Updated Values')->disabled(),
                ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Timestamp')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('action')
                    ->badge()
                    ->color(fn (string $state): string => match (true) {
                        str_contains($state, 'verified') || str_contains($state, 'paid') => 'success',
                        str_contains($state, 'cancelled') || str_contains($state, 'denied') => 'danger',
                        default => 'info',
                    })
                    ->searchable()
                    ->sortable(),

                TextColumn::make('actor.name')
                    ->label('Actor')
                    ->default('System')
                    ->searchable(),

                TextColumn::make('auditable_type')
                    ->label('Target Type')
                    ->formatStateUsing(fn ($state) => class_basename((string) $state))
                    ->sortable(),

                TextColumn::make('auditable_id')
                    ->label('Target ID')
                    ->sortable(),

                TextColumn::make('ip_address')
                    ->label('IP Address')
                    ->searchable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('action')
                    ->options([
                        'order.bank_transfer_verified' => 'Bank Transfer Verified',
                        'order.fulfillment_pending'    => 'Order Ready for Packing',
                        'order.fulfilled'              => 'Order Fulfilled',
                        'order.cancelled'              => 'Order Cancelled',
                        'catalog.price_changed'        => 'Price Changed',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAuditEvents::route('/'),
        ];
    }
}
