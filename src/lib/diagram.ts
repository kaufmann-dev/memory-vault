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

export const SERIES_COLORS = ['#2563eb', '#dc2626', '#0f766e', '#ca8a04', '#9333ea', '#0891b2'];

const topLevelKeys = ['title', 'description', 'xAxis', 'relativeToPeak', 'series', 'measurements'];
const xAxisKeys = ['type', 'label', 'unit'];
const seriesKeys = ['name', 'unit', 'color'];
const colorPattern = /^#[0-9a-fA-F]{6}$/;
const dateTimePattern = /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})$/;

type JsonObject = Record<string, unknown>;

function asObject(value: unknown, allowedKeys: string[], fail: (message: string) => never, what: string): JsonObject {
  if (typeof value !== 'object' || value === null || Array.isArray(value)) fail(`${what} must be an object.`);
  const object = value as JsonObject;
  const unknownKey = Object.keys(object).find((key) => !allowedKeys.includes(key));
  if (unknownKey) fail(`"${unknownKey}" is not an allowed key.`);
  return object;
}

function optionalString(object: JsonObject, key: string, fail: (message: string) => never) {
  const value = object[key];
  if (value === undefined) return '';
  if (typeof value !== 'string') fail(`"${key}" must be a string.`);
  return (value as string).trim();
}

function requiredString(object: JsonObject, key: string, fail: (message: string) => never) {
  const value = optionalString(object, key, fail);
  if (!value) fail(`"${key}" must be a non-empty string.`);
  return value;
}

/** Normalizes local `YYYY-MM-DD HH:mm` (or `T`-separated) text to `YYYY-MM-DDTHH:mm`; null when invalid. */
function normalizeDateTime(value: string) {
  const match = value.match(dateTimePattern);
  if (!match) return null;
  const [year, month, day, hours, minutes] = match.slice(1).map(Number);
  const date = new Date(year, month - 1, day, hours, minutes);
  const matches =
    date.getFullYear() === year &&
    date.getMonth() === month - 1 &&
    date.getDate() === day &&
    date.getHours() === hours &&
    date.getMinutes() === minutes;
  return matches ? `${match[1]}-${match[2]}-${match[3]}T${match[4]}:${match[5]}` : null;
}

function parseSeries(value: unknown, index: number, seen: Set<string>): DiagramField {
  const fail = (message: string): never => {
    throw new Error(`Series ${index + 1}: ${message}`);
  };
  const item = asObject(value, seriesKeys, fail, 'Each series');
  const name = requiredString(item, 'name', fail);
  if (seen.has(name)) fail(`"${name}" is used more than once; series names must be unique.`);
  seen.add(name);
  const color = item.color === undefined ? SERIES_COLORS[index % SERIES_COLORS.length] : item.color;
  if (typeof color !== 'string' || !colorPattern.test(color)) fail('"color" must be a #rrggbb hex color.');
  return { id: crypto.randomUUID(), label: name, unit: optionalString(item, 'unit', fail), color: color as string };
}

function parseMeasurement(
  value: unknown,
  index: number,
  numberAxis: boolean,
  fields: DiagramField[],
  defaultDate: string
): DiagramMeasurement {
  const fail = (message: string): never => {
    throw new Error(`Measurement ${index + 1}: ${message}`);
  };
  const item = asObject(value, [numberAxis ? 'x' : 'date', 'values'], fail, 'Each measurement');

  if (typeof item.values !== 'object' || item.values === null || Array.isArray(item.values)) {
    fail('"values" must be an object.');
  }
  const rawValues = item.values as JsonObject;
  const unknownSeries = Object.keys(rawValues).find((key) => !fields.some((field) => field.label === key));
  if (unknownSeries) fail(`"${unknownSeries}" in "values" does not match any series name.`);
  const values: Record<string, number | null> = {};
  for (const field of fields) {
    const raw = rawValues[field.label];
    if (raw !== undefined && raw !== null && (typeof raw !== 'number' || !Number.isFinite(raw))) {
      fail(`"${field.label}" must be a number or null.`);
    }
    values[field.id] = (raw as number | null | undefined) ?? null;
  }
  if (!Object.values(values).some((entry) => entry !== null)) fail('at least one series value is required.');

  if (numberAxis) {
    if (typeof item.x !== 'number' || !Number.isFinite(item.x)) fail('"x" must be a number.');
    return { id: crypto.randomUUID(), date: defaultDate, x: item.x as number, values };
  }

  const date = typeof item.date === 'string' ? normalizeDateTime(item.date.trim()) : null;
  if (!date) fail('"date" must be a valid YYYY-MM-DD HH:mm date and time.');
  return { id: crypto.randomUUID(), date: date as string, x: null, values };
}

/** Parses one JSON object describing a complete diagram, including its series and measurements. */
export function parseDiagramImport(text: string, defaultDate: string): DiagramPayload {
  let data: unknown;
  try {
    data = JSON.parse(text);
  } catch (error) {
    throw new Error(`Invalid JSON: ${error instanceof Error ? error.message : 'could not parse the text.'}`);
  }

  const fail = (message: string): never => {
    throw new Error(message);
  };
  const root = asObject(data, topLevelKeys, fail, 'The diagram');
  const title = requiredString(root, 'title', fail);
  const description = optionalString(root, 'description', fail);

  const xAxisFail = (message: string): never => fail(`xAxis: ${message}`);
  const xAxis = asObject(root.xAxis, xAxisKeys, xAxisFail, '"xAxis"');
  if (xAxis.type !== 'datetime' && xAxis.type !== 'number') xAxisFail('"type" must be "datetime" or "number".');
  const numberAxis = xAxis.type === 'number';

  if (root.relativeToPeak !== undefined && typeof root.relativeToPeak !== 'boolean') {
    fail('"relativeToPeak" must be true or false.');
  }

  if (!Array.isArray(root.series) || root.series.length === 0) fail('"series" must be a non-empty array.');
  const seen = new Set<string>();
  const fields = (root.series as unknown[]).map((value, index) => parseSeries(value, index, seen));

  if (root.measurements !== undefined && !Array.isArray(root.measurements)) fail('"measurements" must be an array.');
  const measurements = ((root.measurements as unknown[] | undefined) ?? []).map((value, index) =>
    parseMeasurement(value, index, numberAxis, fields, defaultDate)
  );

  return {
    title,
    description,
    xAxis: {
      type: numberAxis ? 'number' : 'datetime',
      label: requiredString(xAxis, 'label', xAxisFail),
      unit: optionalString(xAxis, 'unit', xAxisFail)
    },
    fields,
    measurements,
    relativeToPeak: (root.relativeToPeak as boolean | undefined) ?? false
  };
}
