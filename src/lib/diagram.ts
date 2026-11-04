import type { DiagramField, DiagramMeasurement, DiagramPayload } from '#lib/types.js';

export const defaultXAxis = () => ({ type: 'datetime' as const, label: 'Date', unit: '' });

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

/** Factor that maps the largest absolute value to 100; 1 when there is no non-zero value. */
export function peakScale(values: Array<number | null>) {
  const finite = values.filter((value): value is number => value !== null && Number.isFinite(value));
  const peak = Math.max(0, ...finite.map(Math.abs));
  return peak > 0 ? 100 / peak : 1;
}

export function chartSeries(payload: DiagramPayload) {
  return payload.fields.map((field) => {
    const raw = points(payload, field);
    const scale = payload.relativeToPeak ? peakScale(raw.map((point) => point.y)) : null;
    return {
      label: field.unit ? `${field.label} (${field.unit})` : field.label,
      unit: field.unit,
      scale,
      points: scale === null ? raw : raw.map((point) => ({ ...point, y: point.y === null ? null : point.y * scale })),
      color: field.color
    };
  });
}
