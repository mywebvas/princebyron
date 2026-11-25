<div class="filament-widget revenue-chart-widget">

  <div class="widget-header">
    <h3>Revenue Trend</h3>
    <div class="widget-controls">
      <span class="widget-total">${{ $chartData['total'] }}</span>
      <form method="GET" class="widget-group-form">
        <label class="group-label" for="revenue-group">Group by</label>
        <select id="revenue-group" name="group" onchange="this.form.submit()">
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
