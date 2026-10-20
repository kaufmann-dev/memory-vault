import type { DayCounterPayload } from '#lib/types.js';

export function elapsedDays(date: string, now = new Date()) {
  const start = new Date(`${date}T00:00:00`);
  return Math.max(0, Math.floor((now.getTime() - start.getTime()) / 86_400_000));
}

export function progressPercent(payload: DayCounterPayload, now = new Date()) {
  if (payload.maxDays === null) return null;
  return Math.min(100, (elapsedDays(payload.initiated, now) / payload.maxDays) * 100);
}
