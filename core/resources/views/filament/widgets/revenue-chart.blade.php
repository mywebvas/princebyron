<div class="filament-widget revenue-chart-widget">

  <div class="widget-header">
    <h3>Revenue Trend</h3>
    <div class="widget-controls">
      <span class="widget-total">${{ $chartData['total'] }}</span>
      <form method="GET" class="widget-group-form" style="display:inline; margin-left:16px;">
        <label class="group-label" style="font-size:0.75rem; color:#7E7066; margin-right:6px;">Group by</label>
        <select name="group" onchange="this.form.submit()"
          style="font-size:0.78rem; padding:4px 8px; border:1.5px solid var(--border-input); border-radius:var(--radius-sm); background:var(--surface-card); color:var(--text-primary);">
          <option value="day"   {{ $chartData['group'] === 'day'   ? 'selected' : '' }}>Day</option>
          <option value="week"  {{ $chartData['group'] === 'week'  ? 'selected' : '' }}>Week</option>
          <option value="month" {{ $chartData['group'] === 'month' ? 'selected' : '' }}>Month</option>
        </select>
      </form>
    </div>
  </div>

  <div class="widget-chart">
    {!! $svg !!}
  </div>

  <div class="widget-footer">
    <span class="widget-period">{{ ucfirst($chartData['group']) }} grouping · {{ count($chartData['labels']) }} periods</span>
    <span class="widget-max">Peak: ${{ number_format($chartData['max'], 2) }}</span>
  </div>

</div>

<style>
  .revenue-chart-widget {
    width: 100%;
    max-width: 560px;
    font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
  }

  .widget-header {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    margin-bottom: 12px;
  }

  .widget-header h3 {
    font-size: 1rem;
    font-weight: 600;
    margin: 0;
    color: #181310;
  }

  .widget-controls {
    display: flex;
    align-items: baseline;
    gap: 12px;
  }

  .widget-total {
    font-size: 1.1rem;
    font-weight: 700;
    color: #9E472A;
  }

  .widget-chart {
    width: 100%;
    height: 200px;
    overflow: visible;
  }

  .widget-footer {
    display: flex;
    justify-content: space-between;
    margin-top: 8px;
    font-size: 0.75rem;
    color: #7E7066;
  }
</style>
