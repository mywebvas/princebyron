<?php

namespace App\Filament\Widgets;

use App\Models\AuditEvent;
use App\Models\BookingRequest;
use App\Models\Order;
use App\Models\Setting;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OwnerWorkQueueWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $pendingWires = Order::where('status', 'awaiting_bank_verification')->count();
        $newBookings = BookingRequest::where('status', 'new')->count();
        $liveRatesConfigured = Setting::where('key', 'shipping_rates_configured')->value('value') === 'true';
        $auditTotal = AuditEvent::count();

        return [
            Stat::make('Pending Bank Verifications', (string) $pendingWires)
                ->description($pendingWires > 0 ? 'Requires independent owner reconciliation' : 'All incoming bank wires reconciled')
                ->descriptionIcon($pendingWires > 0 ? 'heroicon-m-clock' : 'heroicon-m-check-badge')
                ->color($pendingWires > 0 ? 'warning' : 'success')
                ->url(url('/admin/orders')),

            Stat::make('New Ministry RFQs', (string) $newBookings)
                ->description($newBookings > 0 ? 'Inbound ministry appearance requests' : 'All booking requests reviewed')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color($newBookings > 0 ? 'info' : 'gray')
                ->url(url('/admin/booking-requests')),

            Stat::make('Shipping Configuration', $liveRatesConfigured ? 'Active Live Rates' : 'Demo Mode (Unconfigured)')
                ->description($liveRatesConfigured ? 'Owner live destinations active' : 'Owner decision pending before activation')
                ->descriptionIcon($liveRatesConfigured ? 'heroicon-m-check-circle' : 'heroicon-m-exclamation-triangle')
                ->color($liveRatesConfigured ? 'success' : 'warning')
                ->url(url('/admin/shipping-rates')),

            Stat::make('Immutable Audit Trail', number_format($auditTotal) . ' Logged Events')
                ->description('Append-only security log (ASVS L2)')
                ->descriptionIcon('heroicon-m-shield-check')
                ->color('success')
                ->url(url('/admin/audit-events')),
        ];
    }
}
