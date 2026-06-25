## Symptom

Line charts always started the y-axis at `0`, even when all measurements were clustered in a much narrower range such as `63-79`.

## Confirmed Root Cause

LayerChart's high-level `LineChart` component sets `yBaseline=0` by default for standard cartesian line charts. That forces `0` into the computed y-domain, so the chart always expands down to zero instead of using the lowest actual measurement.

## Exact Changes Made

- Updated `src/lib/components/SimpleLineChart.svelte` to pass `yBaseline={null}` to `LineChart`.
- Kept the existing axis formatting, responsive sizing, and per-series data rendering unchanged.
- This lets LayerChart derive the y-domain from the real series extent instead of forcing zero into view.
