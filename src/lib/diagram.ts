import type { DiagramField, DiagramMeasurement, DiagramPayload } from '#lib/types.js';

export const defaultXAxis = () => ({ type: 'datetime' as const, label: 'Date', unit: '' });

/** Temporary: stored diagram payloads may predate the x axis and per-measurement x values. */
export type StoredDiagramPayload = Omit<DiagramPayload, 'xAxis' | 'measurements'> & {
  xAxis?: DiagramPayload['xAxis'];
  measurements: Array<Omit<DiagramMeasurement, 'x'> & { x?: number | null }>;
};

/** Temporary: fills in the x axis and measurement x values; returns null when already current. */
export function migrateDiagramPayload(payload: StoredDiagramPayload): DiagramPayload | null {
  if (payload.xAxis && payload.measurements.every((measurement) => measurement.x !== undefined)) return null;
  return {
    ...payload,
    xAxis: payload.xAxis ?? defaultXAxis(),
    measurements: payload.measurements.map((measurement) => ({ ...measurement, x: measurement.x ?? null }))
  };
}

export function isNumberAxis(payload: DiagramPayload) {
  return payload.xAxis.type === 'number';
}

export function sortedMeasurements(payload: DiagramPayload) {
  return [...payload.measurements].sort((a, b) => {
    if (isNumberAxis(payload)) return (a.x ?? 0) - (b.x ?? 0);
    return a.date.localeCompare(b.date);
  });
}

export function xLabel(payload: DiagramPayload, measurement: DiagramMeasurement) {
  if (!isNumberAxis(payload)) return new Date(measurement.date).toLocaleString();
  const { unit } = payload.xAxis;
  return `${measurement.x ?? ''}${unit ? ` ${unit}` : ''}`;
}

export function points(payload: DiagramPayload, field: DiagramField) {
  return sortedMeasurements(payload).map((measurement) => ({
    x: isNumberAxis(payload) ? measurement.x : new Date(measurement.date).getTime(),
    xLabel: xLabel(payload, measurement),
    y: measurement.values[field.id] ?? null
  }));
}

export function chartSeries(payload: DiagramPayload) {
  return payload.fields.map((field) => ({
    label: field.unit ? `${field.label} (${field.unit})` : field.label,
    points: points(payload, field),
    color: field.color
  }));
}
