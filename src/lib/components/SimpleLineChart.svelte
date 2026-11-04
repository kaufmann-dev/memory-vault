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
    unit: string;
    /** Factor applied to the plotted values; null when the values are absolute. */
    scale: number | null;
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
  const relative = $derived(series.some((item) => item.scale !== null));

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

  // The y axis and the grid share one tick count so every grid line has a label.
  const yTickCount = 5;
  // Tick labels are 12px text drawn outside the plot area; reserve room for the widest y label
  // on the left and for half of the last x label on the right.
  const tickCharWidth = 7.5;
  const tickLabelGap = 8;
  const chartPadding = $derived.by(() => {
    const xs = seriesDefs.flatMap((item) => item.data.map((point) => point._x));
    const ys = seriesDefs.flatMap((item) => item.data.map((point) => point.y));
    const widestY = Math.max(1, ...yTickLabels(ys).map((label) => label.length));
    const lastX = xAxis.type === 'number' && xs.length ? formatNumber(Math.max(...xs)).length : "Jan '24".length;
    return {
      top: 8,
      right: Math.ceil((lastX * tickCharWidth) / 2),
      bottom: 20,
      left: Math.ceil(widestY * tickCharWidth) + tickLabelGap
    };
  });

  /** Tick step of a d3 linear scale, so the estimated labels match the rendered ones. */
  function tickStep(min: number, max: number, count: number) {
    const rough = (max - min) / count;
    const power = 10 ** Math.floor(Math.log10(rough));
    const error = rough / power;
    return power * (error >= Math.sqrt(50) ? 10 : error >= Math.sqrt(10) ? 5 : error >= Math.SQRT2 ? 2 : 1);
  }

  function yTickLabels(values: number[]) {
    if (!values.length) return [];
    const min = Math.min(...values);
    const max = Math.max(...values);
    if (max === min) return [formatY(min)];
    const step = tickStep(min, max, yTickCount);
    const labels: string[] = [];
    for (let index = Math.floor(min / step); index <= Math.ceil(max / step); index += 1) {
      labels.push(formatY(index * step));
    }
    return labels;
  }

  function formatNumber(value: number) {
    return new Intl.NumberFormat(undefined, { maximumFractionDigits: 2 }).format(value);
  }

  function formatY(value: number) {
    return relative ? `${formatNumber(value)} %` : formatNumber(value);
  }

  function formatRelativeValue(key: string, value: number) {
    const item = series[Number(key.slice(1))];
    if (!item?.scale) return formatNumber(value);
    const raw = `${formatNumber(value / item.scale)}${item.unit ? ` ${item.unit}` : ''}`;
    return `${raw} · ${formatNumber(value)} %`;
  }

</script>

{#snippet relativeTooltipRow({ value, item }: { value: unknown; item: { key: string; label: string; color?: string } })}
  <div class="flex w-full items-center gap-2">
    <div class="size-2.5 shrink-0 rounded-[2px]" style="background-color: {item.color};"></div>
    <div class="flex flex-1 shrink-0 items-center justify-between gap-4 leading-none">
      <span class="text-muted-foreground">{item.label}</span>
      <span class="text-foreground font-mono font-medium tabular-nums">{formatRelativeValue(item.key, value as number)}</span>
    </div>
  </div>
{/snippet}

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
        padding={chartPadding}
        series={seriesDefs}
        props={{
          spline: { motion: 'none', class: 'stroke-2' },
          // Date axes use LayerChart's interval-aware labels (years, months, days, or times).
          xAxis: xAxis.type === 'number' ? { format: formatNumber, tickSpacing: 72 } : {},
          yAxis: { format: formatY, ticks: yTickCount },
          grid: { yTicks: yTickCount },
          highlight: { points: { r: 4 } }
        }}
      >
        {#snippet tooltip()}
          <Chart.Tooltip
            labelFormatter={(value: Date | number) =>
              xAxis.type === 'datetime' || value instanceof Date
                ? new Date(value).toLocaleString()
                : `${formatNumber(value as number)}${xAxis.unit ? ` ${xAxis.unit}` : ''}`}
            formatter={relative ? relativeTooltipRow : undefined}
          />
        {/snippet}
      </LineChart>
    </Chart.Container>

    {#if relative}
      <p class="text-muted-foreground mt-2 text-xs">Each series is shown as a percentage of its own peak.</p>
    {/if}

    {#if series.length}
      <ul class="mt-4 flex flex-wrap gap-2" aria-label="Series">
        {#each series as item, index (`${item.label}-${index}`)}
          <li class="bg-muted/35 border-border/70 flex max-w-full items-center gap-2 rounded-full border px-3 py-1.5 text-xs sm:text-sm">
            <span class="size-2.5 shrink-0 rounded-full" style="background-color: {item.color};"></span>
            <span class="min-w-0 wrap-anywhere">{item.label}</span>
          </li>
        {/each}
      </ul>
    {/if}
  </div>
{:else}
  <div class="text-muted-foreground flex min-h-48 items-center justify-center text-sm">No chart data yet.</div>
{/if}
