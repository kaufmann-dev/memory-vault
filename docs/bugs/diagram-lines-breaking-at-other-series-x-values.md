## Symptom

Diagram lines rendered as disconnected segments even when a single series had continuous measurements. The breakage was most visible when different series used different x-values.

## Confirmed Root Cause

`src/lib/components/SimpleLineChart.svelte` was building one shared wide dataset for all series. That inserted `null` y-values for every x-position owned by another series. LayerChart's `Spline` component treats `null` values as undefined points and splits the path at each gap, so every series was broken into fragments.

## Exact Changes Made

- Stopped building a merged shared row set in `src/lib/components/SimpleLineChart.svelte`.
- Changed each chart series to pass its own sorted `series.data` array directly to LayerChart.
- Set the line chart to use explicit `x="x"` and `y="y"` accessors so each series renders only from its own valid points.
- Kept the existing responsive sizing, legend, and tooltip behavior unchanged.
