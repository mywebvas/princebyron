<?php

namespace App\Filament\Resources\Orders;

use App\Filament\Resources\Orders\Pages\CreateOrder;
use App\Filament\Resources\Orders\Pages\EditOrder;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Models\AuditEvent;
use App\Models\Order;
use App\Models\Payment;
use App\Services\AnonymiseCustomer;
use App\Services\GrantEntitlementsForOrder;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use UnitEnum;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-receipt-percent';
    protected static string|UnitEnum|null   $navigationGroup = 'Commerce & Orders';
    protected static ?int                  $navigationSort  = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Order Summary')->schema([
                    TextInput::make('reference')
                        ->label('Order Reference')
                        ->disabled()
                        ->dehydrated(false),

                    Select::make('status')
                        ->label('Order Status')
                        ->options([
                            Order::STATUS_PENDING_PAYMENT     => 'Pending Payment',
                            Order::STATUS_AWAITING_BANK       => 'Awaiting Bank Verification',
                            Order::STATUS_PAID                => 'Paid (Funds Verified)',
                            Order::STATUS_FULFILLMENT_PENDING => 'Fulfillment Pending',
                            Order::STATUS_FULFILLED           => 'Fulfilled',
                            Order::STATUS_CANCELLED           => 'Cancelled',
                            Order::STATUS_REFUNDED            => 'Refunded',
                            Order::STATUS_FAILED              => 'Failed',
                        ])
                        ->disabled()
                        ->helperText('Status transitions are guarded server-side and recorded in audit log.'),

                    TextInput::make('currency')
                        ->label('Locked Currency')
                        ->disabled(),

                    TextInput::make('total_cents')
                        ->label('Total (USD Cents)')
                        ->formatStateUsing(fn ($state) => $state ? '$' . number_format($state / 100, 2) : '$0.00')
                        ->disabled(),

                    TextInput::make('customer_name')
                        ->label('Customer Name')
                        ->disabled(),

                    TextInput::make('customer_email')
                        ->label('Customer Email')
                        ->disabled(),
                ])->columns(3),

                Section::make('Shipping Address')->schema([
                    TextInput::make('shipping_name')->disabled(),
                    TextInput::make('shipping_address_line1')->disabled(),
                    TextInput::make('shipping_address_line2')->disabled(),
                    TextInput::make('shipping_city')->disabled(),
                    TextInput::make('shipping_state')->disabled(),
                    TextInput::make('shipping_postal_code')->disabled(),
                    TextInput::make('shipping_country')->disabled(),
                ])->columns(3)->collapsed(),

                Section::make('Internal Notes')->schema([
                    Textarea::make('notes')
                        ->label('Owner Internal Notes')
                        ->rows(3)
                        ->helperText('Private owner notes; never visible to customer.')
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

                TextColumn::make('customer_name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('customer_email')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        Order::STATUS_PAID, Order::STATUS_FULFILLED => 'success',
                        Order::STATUS_AWAITING_BANK                 => 'warning',
                        Order::STATUS_FULFILLMENT_PENDING           => 'info',
                        Order::STATUS_CANCELLED, Order::STATUS_FAILED => 'danger',
                        default                                     => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        Order::STATUS_PENDING_PAYMENT     => 'Pending Payment',
                        Order::STATUS_AWAITING_BANK       => 'Awaiting Bank Check',
                        Order::STATUS_PAID                => 'Paid',
                        Order::STATUS_FULFILLMENT_PENDING => 'Fulfillment Pending',
                        Order::STATUS_FULFILLED           => 'Fulfilled',
                        Order::STATUS_CANCELLED           => 'Cancelled',
                        Order::STATUS_REFUNDED            => 'Refunded',
                        Order::STATUS_FAILED              => 'Failed',
                        default                           => $state,
                    }),

                TextColumn::make('total_cents')
                    ->label('Total')
                    ->formatStateUsing(fn ($state, Order $record) => '$' . number_format($state / 100, 2) . ' ' . $record->currency)
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Placed At')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        Order::STATUS_PENDING_PAYMENT     => 'Pending Payment',
                        Order::STATUS_AWAITING_BANK       => 'Awaiting Bank Verification',
                        Order::STATUS_PAID                => 'Paid',
                        Order::STATUS_FULFILLMENT_PENDING => 'Fulfillment Pending',
                        Order::STATUS_FULFILLED           => 'Fulfilled',
                        Order::STATUS_CANCELLED           => 'Cancelled',
                        Order::STATUS_REFUNDED            => 'Refunded',
                    ]),
            ])
            ->recordActions([
                // Crucial: Owner independent bank transfer verification
                Action::make('verify_bank_transfer')
                    ->label('Verify Bank Funds')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Confirm Bank Transfer Receipt')
                    ->modalDescription('Verify that the exact order funds have cleared on your official bank statement before proceeding. Never rely solely on customer-provided proof.')
                    ->form([
                        TextInput::make('bank_reference')
                            ->label('Bank Statement Reference / Transaction ID')
                            ->required()
                            ->maxLength(100)
                            ->helperText('Reference number from your official bank statement or wire receipt.'),
                        Textarea::make('verification_notes')
                            ->label('Verification Notes (Optional)')
                            ->rows(2)
                            ->maxLength(500),
                    ])
                    ->visible(fn (Order $record): bool => $record->status === Order::STATUS_AWAITING_BANK)
                    ->action(function (Order $record, array $data): void {
                        // Declared outside the transaction so the notification
                        // below can report what was actually granted.
                        $entitlements = ['granted' => 0, 'skipped_guest' => false];

                        DB::transaction(function () use ($record, $data, &$entitlements) {
                            $oldStatus = $record->status;
                            $record->transitionTo(Order::STATUS_PAID);
                            $record->save();

                            Payment::create([
                                'order_id'           => $record->id,
                                'provider'           => Payment::PROVIDER_BANK,
                                'provider_reference' => $data['bank_reference'],
                                'provider_event_id'  => 'bank_' . Str::uuid(),
                                'status'             => Payment::STATUS_SUCCEEDED,
                                'currency'           => $record->currency,
                                'amount_cents'       => $record->total_cents,
                                'signature_verified' => true,
                                'provider_metadata'  => [
                                    'verified_by_actor_id' => auth()->id(),
                                    'verified_by_email'    => auth()->user()?->email,
                                    'verified_at'          => now()->toIso8601String(),
                                    'notes'                => $data['verification_notes'] ?? null,
                                ],
                            ]);

                            // P1-4: the customer paid, so they must get what
                            // they paid for. This used to be omitted here while
                            // both webhooks granted access, so a bank-transfer
                            // customer was marked Paid and never received their
                            // download.
                            $entitlements = app(GrantEntitlementsForOrder::class)->grant($record->fresh());

                            AuditEvent::create([
                                'actor_id'       => auth()->id(),
                                'action'         => 'order.bank_transfer_verified',
                                'auditable_type' => Order::class,
                                'auditable_id'   => $record->id,
                                'old_values'     => ['status' => $oldStatus],
                                'new_values'     => [
                                    'status'            => Order::STATUS_PAID,
                                    'bank_reference'    => $data['bank_reference'],
                                    'amount_cents'      => $record->total_cents,
                                    'entitlements_granted' => $entitlements['granted'],
                                ],
                                'ip_address'     => request()->ip(),
                                'user_agent'     => request()->userAgent(),
                            ]);
                        });

                        if ($entitlements['skipped_guest']) {
                            Notification::make()
                                ->title('Bank payment verified — no account attached')
                                ->body("Order #{$record->reference} is marked Paid, but it is a guest order so no digital downloads were issued. Ask the customer to register with {$record->customer_email}, then grant access from this order.")
                                ->warning()
                                ->persistent()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Bank Payment Verified')
                                ->body($entitlements['granted'] > 0
                                    ? "Order #{$record->reference} is marked Paid and {$entitlements['granted']} download entitlement(s) were issued."
                                    : "Order #{$record->reference} is marked Paid. No digital items on this order.")
                                ->success()
                                ->send();
                        }
                    }),

                // Ready for fulfillment
                Action::make('mark_fulfillment_pending')
                    ->label('Ready to Pack')
                    ->icon('heroicon-o-archive-box')
                    ->color('info')
                    ->requiresConfirmation()
                    ->visible(fn (Order $record): bool => $record->status === Order::STATUS_PAID)
                    ->action(function (Order $record): void {
                        DB::transaction(function () use ($record) {
                            $oldStatus = $record->status;
                            $record->transitionTo(Order::STATUS_FULFILLMENT_PENDING);
                            $record->save();

                            AuditEvent::create([
                                'actor_id'       => auth()->id(),
                                'action'         => 'order.fulfillment_pending',
                                'auditable_type' => Order::class,
                                'auditable_id'   => $record->id,
                                'old_values'     => ['status' => $oldStatus],
                                'new_values'     => ['status' => Order::STATUS_FULFILLMENT_PENDING],
                                'ip_address'     => request()->ip(),
                                'user_agent'     => request()->userAgent(),
                            ]);
                        });

                        Notification::make()
                            ->title('Order Ready for Fulfillment')
                            ->success()
                            ->send();
                    }),

                // Mark fulfilled
                Action::make('mark_fulfilled')
                    ->label('Mark Shipped / Fulfilled')
                    ->icon('heroicon-o-truck')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Order $record): bool => $record->status === Order::STATUS_FULFILLMENT_PENDING)
                    ->action(function (Order $record): void {
                        DB::transaction(function () use ($record) {
                            $oldStatus = $record->status;
                            $record->transitionTo(Order::STATUS_FULFILLED);
                            $record->save();

                            AuditEvent::create([
                                'actor_id'       => auth()->id(),
                                'action'         => 'order.fulfilled',
                                'auditable_type' => Order::class,
                                'auditable_id'   => $record->id,
                                'old_values'     => ['status' => $oldStatus],
                                'new_values'     => ['status' => Order::STATUS_FULFILLED],
                                'ip_address'     => request()->ip(),
                                'user_agent'     => request()->userAgent(),
                            ]);
                        });

                        Notification::make()
                            ->title('Order Fulfilled')
                            ->success()
                            ->send();
                    }),

                // Cancel order
                Action::make('cancel_order')
                    ->label('Cancel')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Order $record): bool => in_array($record->status, [
                        Order::STATUS_PENDING_PAYMENT,
                        Order::STATUS_AWAITING_BANK,
                        Order::STATUS_FAILED,
                    ]))
                    ->action(function (Order $record): void {
                        DB::transaction(function () use ($record) {
                            $oldStatus = $record->status;
                            $record->transitionTo(Order::STATUS_CANCELLED);
                            $record->save();

                            AuditEvent::create([
                                'actor_id'       => auth()->id(),
                                'action'         => 'order.cancelled',
                                'auditable_type' => Order::class,
                                'auditable_id'   => $record->id,
                                'old_values'     => ['status' => $oldStatus],
                                'new_values'     => ['status' => Order::STATUS_CANCELLED],
                                'ip_address'     => request()->ip(),
                                'user_agent'     => request()->userAgent(),
                            ]);
                        });

                        Notification::make()
                            ->title('Order Cancelled')
                            ->warning()
                            ->send();
                    }),

                // P1-7: refunds were only reachable via a provider webhook, so
                // a bank-transfer refund (or any manual refund) required SQL.
                // This is the owner-facing path; it records the payment, revokes
                // digital access and writes an audit event.
                Action::make('refund')
                    ->label('Refund Order')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Record a Refund')
                    ->modalDescription('This marks the order refunded and immediately revokes any digital download entitlements. If money was returned through Stripe or PayPal, record the same refund there so your provider records agree with this system.')
                    ->form([
                        Select::make('reason')
                            ->label('Reason')
                            ->options([
                                'customer_request'   => 'Customer request',
                                'duplicate_order'    => 'Duplicate order',
                                'not_delivered'      => 'Not delivered',
                                'goodwill'           => 'Goodwill gesture',
                                'fraudulent'         => 'Fraudulent payment',
                                'other'              => 'Other',
                            ])
                            ->required(),
                        TextInput::make('provider_reference')
                            ->label('Refund Reference (optional)')
                            ->maxLength(100)
                            ->helperText('Bank refund reference or provider refund ID, for your records.'),
                        Textarea::make('notes')
                            ->label('Internal Notes (optional)')
                            ->rows(2)
                            ->maxLength(500),
                    ])
                    ->visible(fn (Order $record): bool => $record->isRefundable())
                    ->action(function (Order $record, array $data): void {
                        $revoked = 0;
                        $oldStatus = $record->status;

                        DB::transaction(function () use ($record, $data, &$revoked, $oldStatus) {
                            $record->transitionTo(Order::STATUS_REFUNDED);
                            $record->save();

                            Payment::create([
                                'order_id'           => $record->id,
                                'provider'           => Payment::PROVIDER_BANK,
                                'provider_reference' => $data['provider_reference'] ?? null,
                                'provider_event_id'  => 'refund_' . Str::uuid(),
                                'status'             => Payment::STATUS_REFUNDED,
                                'currency'           => $record->currency,
                                'amount_cents'       => -abs($record->total_cents),
                                'signature_verified' => true,
                                'provider_metadata'  => [
                                    'reason'           => $data['reason'],
                                    'notes'            => $data['notes'] ?? null,
                                    'refunded_by_id'   => auth()->id(),
                                    'refunded_by_email'=> auth()->user()?->email,
                                    'refunded_at'      => now()->toIso8601String(),
                                ],
                            ]);

                            $revoked = app(GrantEntitlementsForOrder::class)->revoke($record);

                            AuditEvent::create([
                                'actor_id'       => auth()->id(),
                                'action'         => 'order.refunded',
                                'auditable_type' => Order::class,
                                'auditable_id'   => $record->id,
                                'old_values'     => ['status' => $oldStatus],
                                'new_values'     => [
                                    'status'                => Order::STATUS_REFUNDED,
                                    'reason'                => $data['reason'],
                                    'amount_cents'          => $record->total_cents,
                                    'entitlements_revoked'  => $revoked,
                                ],
                                'ip_address'     => request()->ip(),
                                'user_agent'     => request()->userAgent(),
                            ]);
                        });

                        Notification::make()
                            ->title('Order Refunded')
                            ->body("Order #{$record->reference} marked refunded. {$revoked} download entitlement(s) revoked.")
                            ->success()
                            ->send();
                    }),

                EditAction::make(),

                // P1-5 follow-through: Sprint 2 refused to delete a customer who
                // has orders and the error message promised "deactivate or
                // anonymise". This is that action, and it is the only lawful way
                // to honour a deletion request without destroying the financial
                // record. Irreversible, so it confirms first and says so.
                Action::make('anonymise_customer')
                    ->label('Anonymise customer')
                    ->icon('heroicon-o-user-minus')
                    ->color('danger')
                    ->visible(fn (Order $record): bool => $record->user_id !== null
                        && $record->user !== null
                        && ! $record->user->isOwner())
                    ->requiresConfirmation()
                    ->modalHeading('Anonymise this customer permanently?')
                    ->modalDescription('Their name, email, phone, password and postal address are replaced and the account is deactivated. Orders, line items and amounts are kept because they are financial records, and the downloads they owned are revoked. This cannot be undone.')
                    ->modalSubmitActionLabel('Anonymise permanently')
                    ->schema([
                        Select::make('reason')
                            ->label('Reason for the request')
                            ->options([
                                AnonymiseCustomer::REASON_DELETION_REQUEST => 'Customer asked for their account to be deleted',
                                AnonymiseCustomer::REASON_INACTIVE => 'Account unused for a long period',
                                AnonymiseCustomer::REASON_LEGAL => 'Legal or regulatory requirement',
                            ])
                            ->required(),

                        TextInput::make('reference_note')
                            ->label('Request reference (optional)')
                            ->maxLength(120)
                            ->helperText('e.g. the support ticket number. Stored in the audit log.'),
                    ])
                    ->action(function (Order $record, array $data) {
                        $customer = $record->user;

                        $result = app(AnonymiseCustomer::class)->handle(
                            $customer,
                            $data['reason'],
                            $data['reference_note'] ?? null,
                        );

                        Notification::make()
                            ->title('Customer anonymised')
                            ->body("{$result['reference']} — {$result['orders']} order(s) retained, {$result['entitlements']} download entitlement(s) revoked.")
                            ->success()
                            ->persistent()
                            ->send();
                    }),
            ])
            // P1-5: no bulk delete on financial records. An order is evidence
            // that money moved; the model itself now refuses deletion.
            ;
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListOrders::route('/'),
            'create' => CreateOrder::route('/create'),
            'edit'   => EditOrder::route('/{record}/edit'),
        ];
    }

    /**
     * Eager load the customer relationship so the `anonymise_customer`
     * action visible callback doesn't trigger N+1 queries on the order list.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['user']);
    }
}
