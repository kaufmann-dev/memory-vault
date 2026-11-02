import assert from 'node:assert/strict';
import test from 'node:test';
import { elapsedDays, progressPercent } from '../src/lib/day-counter.ts';

process.env.TZ = 'Europe/Vienna';

const now = new Date('2026-10-20T12:00:00');

test('counts whole days since the start date and never goes negative', () => {
  assert.equal(elapsedDays('2026-10-10', now), 10);
  assert.equal(elapsedDays('2026-10-20', now), 0);
  assert.equal(elapsedDays('2026-11-01', now), 0);
});

test('reports progress only when a maximum is set and caps it at 100', () => {
  assert.equal(progressPercent({ name: 'A', initiated: '2026-10-10', maxDays: null }, now), null);
  assert.equal(progressPercent({ name: 'A', initiated: '2026-10-10', maxDays: 20 }, now), 50);
  assert.equal(progressPercent({ name: 'A', initiated: '2026-10-10', maxDays: 5 }, now), 100);
});

test('counts calendar days across daylight saving changes', () => {
  assert.equal(elapsedDays('2026-03-01', new Date('2026-04-01T00:30:00')), 31);
  assert.equal(elapsedDays('2026-10-01', new Date('2026-11-01T23:30:00')), 31);
});
