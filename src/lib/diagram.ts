import type { DiagramField, DiagramMeasurement, DiagramPayload } from '#lib/types.js';

export const defaultXAxis = () => ({ type: 'datetime' as const, label: 'Date', unit: '' });

export function normalizeDiagramPayload(payload: DiagramPayload): DiagramPayload {
  return {
    ...payload,
    xAxis: payload.xAxis ?? defaultXAxis(),
    fields: payload.fields,
    measurements: payload.measurements.map((measurement) => ({
      ...measurement,
      x: measurement.x ?? null,
      values: { ...measurement.values }
    }))
  };
}

export function xAxis(payload: DiagramPayload) {
  return payload.xAxis ?? defaultXAxis();
}

export function isNumberAxis(payload: DiagramPayload) {
  return xAxis(payload).type === 'number';
}

export function sortedMeasurements(payload: DiagramPayload) {
  return [...payload.measurements].sort((a, b) => {
    if (isNumberAxis(payload)) return (a.x ?? 0) - (b.x ?? 0);
    return a.date.localeCompare(b.date);
  });
}

export function xLabel(payload: DiagramPayload, measurement: DiagramMeasurement) {
  if (!isNumberAxis(payload)) return new Date(measurement.date).toLocaleString();
  const axis = xAxis(payload);
  return `${measurement.x ?? ''}${axis.unit ? ` ${axis.unit}` : ''}`;
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
