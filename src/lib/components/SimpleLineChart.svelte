<script lang="ts">
  import * as Chart from '$lib/components/ui/chart/index.js';
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

  const chartConfig = $derived(
    Object.fromEntries(series.map((item, index) => [`s${index}`, { label: item.label, color: item.color }]))
  ) as Chart.ChartConfig;

  const seriesDefs = $derived(
    series.map((item, index) => ({ key: `s${index}`, label: item.label, color: item.color }))
  );

  type Row = { x: Date | number; _x: number } & Record<string, number>;

  const data = $derived.by(() => {
    const rows = new Map<number, Row>();
    series.forEach((item, index) => {
      for (const point of item.points) {
        if (point.x === null || point.x === undefined || !Number.isFinite(point.x)) continue;
        const xValue = point.x;
        let row = rows.get(xValue);
        if (!row) {
          row = { x: xAxis.type === 'datetime' ? new Date(xValue) : xValue, _x: xValue } as Row;
          rows.set(xValue, row);
        }
        if (point.y !== null && Number.isFinite(point.y)) row[`s${index}`] = point.y;
      }
    });
    return [...rows.values()].sort((a, b) => a._x - b._x);
  });

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

{#if data.length}
  <Chart.Container config={chartConfig} class="aspect-auto w-full" style="height: {height}px;">
    <LineChart
      {data}
      x="x"
      series={seriesDefs}
      legend
      props={{
        spline: { motion: 'none', class: 'stroke-2' },
        xAxis: { format: formatX },
        yAxis: { format: formatNumber },
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
{:else}
  <div class="text-muted-foreground flex min-h-48 items-center justify-center text-sm">No chart data yet.</div>
{/if}
