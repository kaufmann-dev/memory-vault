<script lang="ts">
  type Point = {
    x: number | null | undefined;
    xLabel: string;
    y: number | null;
  };

  type Series = {
    label: string;
    points: Point[];
    color: string;
  };

  type Axis = {
    type: 'datetime' | 'number';
    label: string;
    unit: string;
  };

  type ChartPoint = {
    key: string;
    seriesLabel: string;
    color: string;
    x: number;
    xLabel: string;
    y: number;
  };

  let {
    series = [],
    xAxis = { type: 'datetime', label: 'Date', unit: '' },
    height = 320
  }: { series?: Series[]; xAxis?: Axis; height?: number } = $props();

  const width = 900;
  const padding = { top: 22, right: 28, bottom: 54, left: 58 };
  const chartWidth = width - padding.left - padding.right;
  const tooltipWidth = 260;
  const tooltipHeight = 50;

  let activePointKey: string | null = $state(null);

  let allPoints = $derived.by(() => {
    const points: ChartPoint[] = [];
    series.forEach((item, seriesIndex) => {
      item.points.forEach((point, pointIndex) => {
        if (point.x === null || point.x === undefined || point.y === null) return;
        if (!Number.isFinite(point.x) || !Number.isFinite(point.y)) return;
        points.push({
          key: `${seriesIndex}-${pointIndex}`,
          seriesLabel: item.label,
          color: item.color,
          x: point.x,
          xLabel: point.xLabel,
          y: point.y
        });
      });
    });
    return points;
  });
  let xValues = $derived(allPoints.map((point) => point.x));
  let yValues = $derived(allPoints.map((point) => point.y));
  let minX = $derived(xValues.length ? Math.min(...xValues) : 0);
  let maxX = $derived(xValues.length ? Math.max(...xValues) : 1);
  let minYRaw = $derived(yValues.length ? Math.min(...yValues) : 0);
  let maxYRaw = $derived(yValues.length ? Math.max(...yValues) : 1);
  let yPadding = $derived(Math.max((maxYRaw - minYRaw) * 0.08, maxYRaw === minYRaw ? 1 : 0));
  let minY = $derived(Math.min(0, minYRaw - yPadding));
  let maxY = $derived(maxYRaw + yPadding);
  let xRange = $derived(maxX - minX || 1);
  let yRange = $derived(maxY - minY || 1);
  let xTicks = $derived(makeTicks(minX, maxX, 6));
  let yTicks = $derived(makeTicks(minY, maxY, 5));
  let activePoint = $derived(allPoints.find((point) => point.key === activePointKey) ?? null);

  function makeTicks(min: number, max: number, count: number) {
    if (count <= 1 || min === max) return [min];
    return Array.from({ length: count }, (_, index) => min + ((max - min) / (count - 1)) * index);
  }

  function xFor(value: number) {
    return padding.left + ((value - minX) / xRange) * chartWidth;
  }

  function yFor(value: number) {
    return height - padding.bottom - ((value - minY) / yRange) * (height - padding.top - padding.bottom);
  }

  function pathFor(points: Point[]) {
    return points
      .filter(
        (point) =>
          point.x !== null &&
          point.x !== undefined &&
          point.y !== null &&
          Number.isFinite(point.x) &&
          Number.isFinite(point.y)
      )
      .map((point) => `${xFor(point.x as number)},${yFor(point.y as number)}`)
      .join(' ');
  }

  function formatNumber(value: number) {
    return new Intl.NumberFormat(undefined, { maximumFractionDigits: 2 }).format(value);
  }

  function formatXTick(value: number) {
    if (xAxis.type === 'datetime') {
      return new Intl.DateTimeFormat(undefined, { month: 'short', day: 'numeric' }).format(new Date(value));
    }
    return formatNumber(value);
  }

  function tooltipX(point: ChartPoint) {
    return Math.min(width - tooltipWidth - 8, Math.max(8, xFor(point.x) - tooltipWidth / 2));
  }

  function tooltipY(point: ChartPoint) {
    return Math.max(8, yFor(point.y) - tooltipHeight - 12);
  }
</script>

<div class="chart-shell">
  {#if allPoints.length}
    <svg viewBox={`0 0 ${width} ${height}`} class="chart" role="img" aria-label={`${xAxis.label} chart`}>
      <rect
        x={padding.left}
        y={padding.top}
        width={chartWidth}
        height={height - padding.top - padding.bottom}
        class="chart__plot"
      ></rect>

      {#each yTicks as tick (tick)}
        <line x1={padding.left} y1={yFor(tick)} x2={width - padding.right} y2={yFor(tick)} class="chart__grid"></line>
        <text x={padding.left - 10} y={yFor(tick) + 4} text-anchor="end" class="chart__tick">{formatNumber(tick)}</text>
      {/each}

      {#each xTicks as tick (tick)}
        <line x1={xFor(tick)} y1={padding.top} x2={xFor(tick)} y2={height - padding.bottom} class="chart__grid"></line>
        <text x={xFor(tick)} y={height - padding.bottom + 22} text-anchor="middle" class="chart__tick">{formatXTick(tick)}</text>
      {/each}

      <line x1={padding.left} y1={height - padding.bottom} x2={width - padding.right} y2={height - padding.bottom} class="chart__axis"></line>
      <line x1={padding.left} y1={padding.top} x2={padding.left} y2={height - padding.bottom} class="chart__axis"></line>
      <text x={padding.left + chartWidth / 2} y={height - 12} text-anchor="middle" class="chart__axis-label">
        {xAxis.unit ? `${xAxis.label} (${xAxis.unit})` : xAxis.label}
      </text>

      {#each series as item (item.label)}
        <polyline
          fill="none"
          stroke={item.color}
          stroke-width="2.25"
          stroke-linecap="round"
          stroke-linejoin="round"
          points={pathFor(item.points)}
        />
      {/each}

      {#each allPoints as point (point.key)}
        <circle
          cx={xFor(point.x)}
          cy={yFor(point.y)}
          r="4"
          fill="var(--background)"
          stroke={point.color}
          stroke-width="2.25"
          role="button"
          tabindex="0"
          aria-label={`${point.seriesLabel}, ${point.xLabel}, ${formatNumber(point.y)}`}
          onpointerenter={() => (activePointKey = point.key)}
          onpointerleave={() => (activePointKey = null)}
          onfocus={() => (activePointKey = point.key)}
          onblur={() => (activePointKey = null)}
        ></circle>
      {/each}

      {#if activePoint}
        <g class="chart__tooltip" transform={`translate(${tooltipX(activePoint)}, ${tooltipY(activePoint)})`}>
          <rect width={tooltipWidth} height={tooltipHeight} rx="7"></rect>
          <text x="12" y="20">{activePoint.xLabel}</text>
          <circle cx="17" cy="34" r="4" fill={activePoint.color}></circle>
          <text x="28" y="38">{activePoint.seriesLabel}: {formatNumber(activePoint.y)}</text>
        </g>
      {/if}
    </svg>

    <div class="chart-legend">
      {#each series as item (item.label)}
        <span class="chart-legend__item">
          <span class="chart-legend__swatch" style={`background: ${item.color}`}></span>
          {item.label}
        </span>
      {/each}
    </div>
  {:else}
    <div class="chart-empty">No chart data yet.</div>
  {/if}
</div>

<style>
  .chart-shell {
    padding: 1rem;
  }

  .chart {
    display: block;
    width: 100%;
    height: auto;
    overflow: visible;
  }

  .chart__plot {
    fill: color-mix(in srgb, var(--surface) 72%, transparent);
  }

  .chart__grid {
    stroke: var(--border);
    stroke-width: 1;
    opacity: 0.72;
  }

  .chart__axis {
    stroke: var(--border-strong);
    stroke-width: 1.25;
  }

  .chart__tick,
  .chart__axis-label {
    fill: var(--muted);
    font-size: 0.75rem;
  }

  .chart__axis-label {
    font-weight: 600;
  }

  .chart__tooltip rect {
    fill: color-mix(in srgb, var(--foreground) 92%, transparent);
  }

  .chart__tooltip text {
    fill: var(--background);
    font-size: 0.75rem;
    font-weight: 700;
  }

  .chart-legend {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem 1rem;
    margin-top: 0.75rem;
    color: var(--muted);
    font-size: 0.75rem;
  }

  .chart-legend__item {
    display: inline-flex;
    gap: 0.45rem;
    align-items: center;
  }

  .chart-legend__swatch {
    width: 1rem;
    height: 0.5rem;
    border-radius: 999px;
  }

  .chart-empty {
    display: flex;
    min-height: 12rem;
    align-items: center;
    justify-content: center;
    color: var(--muted);
    font-size: 0.875rem;
  }
</style>
