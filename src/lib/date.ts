const pad = (value: number) => String(value).padStart(2, '0');

/** Calendar date (YYYY-MM-DD) in the device's time zone. */
export function localDate(date = new Date()) {
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

/** Wall-clock date and time (YYYY-MM-DDTHH:mm) in the device's time zone, as used by datetime-local inputs. */
export function localDateTime(date = new Date()) {
  return `${localDate(date)}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}
