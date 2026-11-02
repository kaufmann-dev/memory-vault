import assert from 'node:assert/strict';
import test from 'node:test';
import { localDate, localDateTime } from '../src/lib/date.ts';

process.env.TZ = 'Europe/Vienna';

test('uses the local calendar day after local midnight, not the UTC one', () => {
  const justAfterMidnight = new Date('2026-11-02T00:30:00');
  assert.equal(justAfterMidnight.toISOString().slice(0, 10), '2026-11-01');
  assert.equal(localDate(justAfterMidnight), '2026-11-02');
  assert.equal(localDateTime(justAfterMidnight), '2026-11-02T00:30');
});

test('keeps the local wall-clock time in summer time', () => {
  assert.equal(localDateTime(new Date('2026-07-15T09:05:00')), '2026-07-15T09:05');
});
