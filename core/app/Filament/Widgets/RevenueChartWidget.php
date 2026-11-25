<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use App\Models\User;
use Filament\Widgets\Widget;

/*
|--------------------------------------------------------------------------
| Revenue chart widget — server-side SVG, zero client dependencies
|--------------------------------------------------------------------------
|
| Renders a compact revenue trend chart as inline SVG. No chart library is
| shipped to the browser — the SVG is generated once per request and inlined
| directly into the dashboard HTML.
|
| Data source: orders with captured funds (paid, fulfillment_pending,
| fulfilled). Grouped by day (default), week, or month.
|
| Access: owner and any staff member with view_reports permission.
*/

class RevenueChartWidget extends Widget
{
    protected static ?int $sort = 2;

    protected static string $view = 'filament.widgets.revenue-chart';

    // These public properties are automatically available to the Blade view.
    public array $chartData = [];
    public string $svg = '';
    public string $group = 'day'; // day | week | month

    public function mount(): void
    {
        $user = auth()->user();

        if (! $user || ! $user->can(User::PERM_VIEW_REPORTS)) {
            abort(403);
        }

        $this->group = request('group', 'day');
        if (! in_array($this->group, ['day', 'week', 'month'], true)) {
            $this->group = 'day';
        }

        $this->chartData = $this->chartData();
        $this->svg = $this->renderSvg($this->chartData);
    }

    public function chartData(): array
    {
        $now = now();

        // Match the grouping to the number of periods and the date format.
        return match ($this->group) {
            'week'  => $this->chartByWeek($now),
            'month' => $this->chartByMonth($now),
            default => $this->chartByDay($now),
        };
    }

    protected function chartByDay(\Carbon\Carbon $now): array
    {
        $days = 30;
        $startDate = $now->copy()->subDays($days - 1)->startOfDay();

        // Range-based WHERE on created_at can use the ('status', 'created_at')
        // composite index. DATE() wrapping would prevent index usage.
        $rows = Order::whereIn('status', [
            Order::STATUS_PAID,
            Order::STATUS_FULFILLMENT_PENDING,
            Order::STATUS_FULFILLED,
        ])
            ->where('created_at', '>=', $startDate)
            ->selectRaw('DATE(created_at) as day, SUM(total_cents) as cents')
            ->groupBy('day')
            ->get()
            ->keyBy('day');

        $labels = [];
        $values = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $day = $now->copy()->subDays($i)->format('Y-m-d');
            $labels[] = $now->copy()->subDays($i)->format('M d');
            $values[] = (float) ($rows[$day]?->cents ?? 0) / 100;
        }

        return $this->finalizeData($labels, $values);
    }

    protected function chartByWeek(\Carbon\Carbon $now): array
    {
        $weeks = 12;
        $startDate = $now->copy()->subWeeks($weeks - 1)->startOfWeek()->startOfDay();

        $rows = Order::whereIn('status', [
            Order::STATUS_PAID,
            Order::STATUS_FULFILLMENT_PENDING,
            Order::STATUS_FULFILLED,
        ])
            ->where('created_at', '>=', $startDate)
            ->selectRaw('YEARWEEK(created_at, 1) as period, SUM(total_cents) as cents')
            ->groupBy('period')
            ->get()
            ->keyBy('period');

        $labels = [];
        $values = [];

        for ($i = $weeks - 1; $i >= 0; $i--) {
            $weekStart = $now->copy()->subWeeks($i)->startOfWeek();
            $period = (int) $weekStart->format('%Y%U'); // MySQL YEARWEEK mode 1
            $labels[] = $weekStart->format('M d');
            $values[] = (float) ($rows[(string) $period]?->cents ?? 0) / 100;
        }

        return $this->finalizeData($labels, $values);
    }

    protected function chartByMonth(\Carbon\Carbon $now): array
    {
        $months = 12;
        $startDate = $now->copy()->subMonths($months - 1)->startOfMonth()->startOfDay();

        $rows = Order::whereIn('status', [
            Order::STATUS_PAID,
            Order::STATUS_FULFILLMENT_PENDING,
            Order::STATUS_FULFILLED,
        ])
            ->where('created_at', '>=', $startDate)
            ->selectRaw('DATE_FORMAT(created_at, "%Y-%m") as period, SUM(total_cents) as cents')
            ->groupBy('period')
            ->get()
            ->keyBy('period');

        $labels = [];
        $values = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $period = $now->copy()->subMonths($i)->format('Y-m');
            $labels[] = $now->copy()->subMonths($i)->format('M Y');
            $values[] = (float) ($rows[$period]?->cents ?? 0) / 100;
        }

        return $this->finalizeData($labels, $values);
    }

    protected function finalizeData(array $labels, array $values): array
    {
        $total = array_sum($values);
        $max = max($values) ?: 1;

        return [
            'labels' => $labels,
            'values' => $values,
            'total'  => number_format($total, 2),
            'max'    => $max,
            'group'  => $this->group,
        ];
    }

    public function renderSvg(array $data): string
    {
        $width = 560;
        $height = 200;
        $padding = 40;
        $plotWidth = $width - 2 * $padding;
        $plotHeight = $height - 2 * $padding;

        $values = $data['values'];
        $labels = $data['labels'];
        $max = $data['max'];
        $count = count($values);

        $points = [];
        foreach ($values as $i => $value) {
            $x = $padding + ($i / max($count - 1, 1)) * $plotWidth;
            $y = $height - $padding - ($value / $max) * $plotHeight;
            $points[] = "{$x},{$y}";
        }

        $pointString = implode(' ', $points);
        $lastY = $height - $padding - ($values[count($values) - 1] / $max) * $plotHeight;

        return <<<SVG
<svg width="{$width}" height="{$height}" viewBox="0 0 {$width} {$height}" xmlns="http://www.w3.org/2000/svg" role="img" aria-labelledby="revenueChartTitle">
  <title id="revenueChartTitle">Revenue trend — last 30 days</title>
  <defs>
    <linearGradient id="revenueGradient" x1="0" x2="0" y1="0" y2="1">
      <stop offset="0%" stop-color="#DFA759" stop-opacity="0.25"/>
      <stop offset="100%" stop-color="#DFA759" stop-opacity="0.02"/>
    </linearGradient>
  </defs>

  <path d="M{$padding},{$height - $padding} L{$pointString} L{$padding + $plotWidth},{$height - $padding} Z"
        fill="url(#revenueGradient)" fill-opacity="0.15"/>

  <polyline points="{$pointString}"
            fill="none"
            stroke="#DFA759"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"/>

  <circle cx="{$padding + $plotWidth}" cy="{$lastY}"
          r="3" fill="#DFA759"/>

  <text x="{$padding}" y="{$height - 8}" font-size="9" fill="#7E7066" font-family="system-ui, -apple-system, sans-serif">${$data['total']} total</text>
</svg>
SVG;
    }
}
