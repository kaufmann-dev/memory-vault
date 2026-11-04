import assert from 'node:assert/strict';
import test from 'node:test';
import { chartSeries, parseDiagramImport, peakScale, SERIES_COLORS } from '../src/lib/diagram.ts';
import type { DiagramPayload } from '../src/lib/types.ts';

const payload = (relativeToPeak: boolean): DiagramPayload => ({
  title: 'Plasma levels',
  description: '',
  xAxis: { type: 'number', label: 'Minutes', unit: 'min' },
  fields: [
    { id: 'a', label: 'Caffeine', unit: 'ng/ml', color: '#ca8a04' },
    { id: 'b', label: 'Guanfacine', unit: 'ng/ml', color: '#0891b2' }
  ],
  measurements: [
    { id: 'm1', date: '2026-01-01T08:00', x: 0, values: { a: 0, b: 0 } },
    { id: 'm2', date: '2026-01-01T08:00', x: 60, values: { a: 3400, b: 2 } },
    { id: 'm3', date: '2026-01-01T08:00', x: 120, values: { a: 1700, b: null } }
  ],
  relativeToPeak
});

test('chartSeries keeps absolute values when relative scaling is off', () => {
  const [caffeine] = chartSeries(payload(false));
  assert.equal(caffeine.scale, null);
  assert.equal(caffeine.unit, 'ng/ml');
  assert.deepEqual(
    caffeine.points.map((point) => point.y),
    [0, 3400, 1700]
  );
});

test('chartSeries scales each series to its own peak', () => {
  const [caffeine, guanfacine] = chartSeries(payload(true));
  assert.deepEqual(
    caffeine.points.map((point) => point.y),
    [0, 100, 50]
  );
  assert.deepEqual(
    guanfacine.points.map((point) => point.y),
    [0, 100, null]
  );
  assert.equal(3400 * (caffeine.scale ?? 0), 100);
});

test('peakScale uses the largest absolute value and ignores missing values', () => {
  assert.equal(peakScale([-50, 25, null, Number.NaN]), 2);
  assert.equal(peakScale([0, 0, null]), 1);
  assert.equal(peakScale([]), 1);
});

const now = '2026-11-04T09:00';

const numberDiagram = (overrides: Record<string, unknown> = {}) =>
  JSON.stringify({
    title: ' Plasma levels ',
    xAxis: { type: 'number', label: 'Minutes', unit: 'min' },
    series: [{ name: 'Caffeine', unit: 'ng/ml', color: '#ca8a04' }, { name: ' Guanfacine ' }],
    measurements: [
      { x: 0, values: { Caffeine: 0, Guanfacine: 0 } },
      { x: 60, values: { Caffeine: 3400 } }
    ],
    ...overrides
  });

const datetimeDiagram = (measurements: unknown[]) =>
  JSON.stringify({
    title: 'Vitals',
    xAxis: { type: 'datetime', label: 'Date' },
    series: [{ name: 'Weight', unit: 'kg' }],
    measurements
  });

test('parseDiagramImport builds a number-axis diagram and applies defaults', () => {
  const payload = parseDiagramImport(numberDiagram(), now);
  assert.equal(payload.title, 'Plasma levels');
  assert.equal(payload.description, '');
  assert.equal(payload.relativeToPeak, false);
  assert.deepEqual(payload.xAxis, { type: 'number', label: 'Minutes', unit: 'min' });
  assert.deepEqual(
    payload.fields.map(({ label, unit, color }) => ({ label, unit, color })),
    [
      { label: 'Caffeine', unit: 'ng/ml', color: '#ca8a04' },
      { label: 'Guanfacine', unit: '', color: SERIES_COLORS[1] }
    ]
  );
  const [caffeine, guanfacine] = payload.fields;
  assert.deepEqual(
    payload.measurements.map((measurement) => ({
      date: measurement.date,
      x: measurement.x,
      caffeine: measurement.values[caffeine.id],
      guanfacine: measurement.values[guanfacine.id]
    })),
    [
      { date: now, x: 0, caffeine: 0, guanfacine: 0 },
      { date: now, x: 60, caffeine: 3400, guanfacine: null }
    ]
  );
  assert.equal(new Set([...payload.fields, ...payload.measurements].map((item) => item.id)).size, 4);
});

test('parseDiagramImport keeps optional settings and allows no measurements', () => {
  const payload = parseDiagramImport(
    numberDiagram({ description: ' Single dose ', relativeToPeak: true, measurements: undefined }),
    now
  );
  assert.equal(payload.description, 'Single dose');
  assert.equal(payload.relativeToPeak, true);
  assert.deepEqual(payload.measurements, []);
});

test('parseDiagramImport normalizes datetime measurements', () => {
  const payload = parseDiagramImport(
    datetimeDiagram([
      { date: '2024-05-01 08:30', values: { Weight: 72.4 } },
      { date: '2024-05-02T07:05', values: { Weight: 72.1 } }
    ]),
    now
  );
  assert.deepEqual(
    payload.measurements.map(({ date, x }) => ({ date, x })),
    [
      { date: '2024-05-01T08:30', x: null },
      { date: '2024-05-02T07:05', x: null }
    ]
  );
});

test('parseDiagramImport rejects invalid input', () => {
  const cases: Array<[string, RegExp]> = [
    ['{', /Invalid JSON/],
    ['[]', /The diagram must be an object/],
    [numberDiagram({ owner: 'me' }), /"owner" is not an allowed key/],
    [numberDiagram({ title: ' ' }), /"title" must be a non-empty string/],
    [numberDiagram({ xAxis: { type: 'log', label: 'X' } }), /xAxis: "type" must be "datetime" or "number"/],
    [numberDiagram({ series: [] }), /"series" must be a non-empty array/],
    [numberDiagram({ series: [{ name: 'A' }, { name: ' A ' }] }), /Series 2: "A" is used more than once/],
    [numberDiagram({ series: [{ name: 'A', color: 'red' }] }), /Series 1: "color" must be a #rrggbb hex color/],
    [numberDiagram({ measurements: [{ x: 0, values: { Coffee: 1 } }] }), /Measurement 1: "Coffee" in "values" does not match/],
    [numberDiagram({ measurements: [{ x: 0, values: { Caffeine: null } }] }), /Measurement 1: at least one series value/],
    [numberDiagram({ measurements: [{ x: 0, values: { Caffeine: '5' } }] }), /Measurement 1: "Caffeine" must be a number or null/],
    [numberDiagram({ measurements: [{ date: '2024-05-01 08:30', values: { Caffeine: 1 } }] }), /Measurement 1: "date" is not an allowed key/],
    [numberDiagram({ measurements: [{ values: { Caffeine: 1 } }] }), /Measurement 1: "x" must be a number/],
    [datetimeDiagram([{ x: 0, values: { Weight: 1 } }]), /Measurement 1: "x" is not an allowed key/],
    [datetimeDiagram([{ date: '2024-02-30 10:00', values: { Weight: 1 } }]), /Measurement 1: "date" must be a valid/],
    [datetimeDiagram([{ date: '1714552200', values: { Weight: 1 } }]), /Measurement 1: "date" must be a valid/]
  ];
  for (const [text, message] of cases) {
    assert.throws(() => parseDiagramImport(text, now), message, text);
  }
});
