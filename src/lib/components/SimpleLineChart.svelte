<script lang="ts">
  import * as Chart from '#lib/components/ui/chart/index.js';
  import { LineChart } from 'layerchart';

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

  let {
    series = [],
    xAxis = { type: 'datetime', label: 'Date', unit: '' },
    height = 320
  }: { series?: Series[]; xAxis?: Axis; height?: number } = $props();

  const mobileHeight = $derived(Math.max(220, Math.round(height * 0.75)));

  const chartConfig = $derived(
    Object.fromEntries(series.map((item, index) => [`s${index}`, { label: item.label, color: item.color }]))
  ) as Chart.ChartConfig;

  const seriesDefs = $derived(
    series.map((item, index) => ({
      key: `s${index}`,
      label: item.label,
      color: item.color,
      data: item.points
        .filter((point) => {
          return (
            point.x !== null &&
            point.x !== undefined &&
            Number.isFinite(point.x) &&
            point.y !== null &&
            Number.isFinite(point.y)
          );
        })
        .map((point) => ({
          x: xAxis.type === 'datetime' ? new Date(point.x as number) : (point.x as number),
          y: point.y as number,
          _x: point.x as number
        }))
        .sort((a, b) => a._x - b._x)
    }))
  );
  const hasData = $derived(seriesDefs.some((item) => item.data.length > 0));

  function formatNumber(value: number) {
    return new Intl.NumberFormat(undefined, { maximumFractionDigits: 2 }).format(value);
  }

  function formatX(value: Date | number) {
    if (xAxis.type === 'datetime' || value instanceof Date) {
      return new Intl.DateTimeFormat(undefined, { month: 'short', day: 'numeric' }).format(new Date(value));
    }
    return formatNumber(value as number);
  }
</script>

{#if hasData}
  <div class="min-w-0">
    <Chart.Container
      config={chartConfig}
      class="aspect-auto h-[var(--chart-height-mobile)] min-w-0 w-full sm:h-[var(--chart-height)]"
      style="--chart-height-mobile: {mobileHeight}px; --chart-height: {height}px;"
    >
      <LineChart
        x="x"
        y="y"
        yBaseline={null}
        series={seriesDefs}
        props={{
          spline: { motion: 'none', class: 'stroke-2' },
          xAxis: {
            format: formatX,
            tickSpacing: xAxis.type === 'datetime' ? 88 : 72
          },
          yAxis: {
            format: formatNumber,
            tickSpacing: 48
          },
          highlight: { points: { r: 4 } }
        }}
      >
        {#snippet tooltip()}
          <Chart.Tooltip
            labelFormatter={(value: Date | number) =>
              xAxis.type === 'datetime' || value instanceof Date
                ? new Date(value).toLocaleString()
                : `${formatNumber(value as number)}${xAxis.unit ? ` ${xAxis.unit}` : ''}`}
          />
        {/snippet}
      </LineChart>
    </Chart.Container>

    {#if series.length}
      <ul class="mt-4 flex flex-wrap gap-2" aria-label="Series">
        {#each series as item, index (`${item.label}-${index}`)}
          <li class="bg-muted/35 border-border/70 flex max-w-full items-center gap-2 rounded-full border px-3 py-1.5 text-xs sm:text-sm">
            <span class="size-2.5 shrink-0 rounded-full" style="background-color: {item.color};"></span>
            <span class="min-w-0 break-words">{item.label}</span>
          </li>
        {/each}
      </ul>
    {/if}
  </div>
{:else}
  <div class="text-muted-foreground flex min-h-48 items-center justify-center text-sm">No chart data yet.</div>
{/if}
