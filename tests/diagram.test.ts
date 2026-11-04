import assert from 'node:assert/strict';
import test from 'node:test';
import { chartSeries, peakScale } from '../src/lib/diagram.ts';
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
