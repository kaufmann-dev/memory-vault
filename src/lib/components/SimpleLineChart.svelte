<script lang="ts">
  type Point = {
    x: string;
    y: number | null;
  };

  type Series = {
    label: string;
    points: Point[];
    color: string;
  };

  let { series = [], height = 260 }: { series?: Series[]; height?: number } = $props();

  const width = 720;
  const padding = 36;

  let allPoints = $derived(series.flatMap((item) => item.points).filter((point) => point.y !== null) as Array<{
    x: string;
    y: number;
  }>);
  let times = $derived(allPoints.map((point) => new Date(point.x).getTime()).filter(Number.isFinite));
  let values = $derived(allPoints.map((point) => point.y).filter(Number.isFinite));
  let minTime = $derived(times.length ? Math.min(...times) : 0);
  let maxTime = $derived(times.length ? Math.max(...times) : 1);
  let minValue = $derived(values.length ? Math.min(...values) : 0);
  let maxValue = $derived(values.length ? Math.max(...values) : 1);
  let valueRange = $derived(maxValue - minValue || 1);
  let timeRange = $derived(maxTime - minTime || 1);

  function xFor(value: string) {
    return padding + ((new Date(value).getTime() - minTime) / timeRange) * (width - padding * 2);
  }

  function yFor(value: number) {
    return height - padding - ((value - minValue) / valueRange) * (height - padding * 2);
  }

  function pathFor(points: Point[]) {
    return points
      .filter((point) => point.y !== null)
      .map((point) => `${xFor(point.x)},${yFor(point.y as number)}`)
      .join(' ');
  }
</script>

<div class="rounded-xl border p-4" style="border-color: var(--border); background: var(--surface)">
  {#if allPoints.length}
    <svg viewBox={`0 0 ${width} ${height}`} class="h-auto w-full overflow-visible" role="img">
      <line x1={padding} y1={height - padding} x2={width - padding} y2={height - padding} stroke="var(--border)" />
      <line x1={padding} y1={padding} x2={padding} y2={height - padding} stroke="var(--border)" />
      {#each series as item (item.label)}
        <polyline
          fill="none"
          stroke={item.color}
          stroke-width="2"
          stroke-linecap="round"
          stroke-linejoin="round"
          points={pathFor(item.points)}
        />
      {/each}
    </svg>
    <div class="mt-3 flex flex-wrap gap-3 text-xs" style="color: var(--muted)">
      {#each series as item (item.label)}
        <span class="inline-flex items-center gap-2">
          <span class="h-2 w-4 rounded-full" style={`background: ${item.color}`}></span>
          {item.label}
        </span>
      {/each}
    </div>
  {:else}
    <div class="flex h-48 items-center justify-center text-sm" style="color: var(--muted)">No chart data yet.</div>
  {/if}
</div>
