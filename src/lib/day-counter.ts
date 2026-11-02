import type { DayCounterPayload } from '#lib/types.js';

/** Whole calendar days since `date` (YYYY-MM-DD) in the device's time zone; daylight saving changes do not shift the count. */
export function elapsedDays(date: string, now = new Date()) {
  const [year, month, day] = date.split('-').map(Number);
  const start = Date.UTC(year, month - 1, day);
  const today = Date.UTC(now.getFullYear(), now.getMonth(), now.getDate());
  return Math.max(0, (today - start) / 86_400_000);
}

export function progressPercent(payload: DayCounterPayload, now = new Date()) {
  if (payload.maxDays === null) return null;
  return Math.min(100, (elapsedDays(payload.initiated, now) / payload.maxDays) * 100);
}
