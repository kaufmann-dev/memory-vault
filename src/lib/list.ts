import type { ListColumn } from '#lib/types.js';

export function newColumn(): ListColumn {
  return { id: crypto.randomUUID(), name: '' };
}

export function columnLabel(column: ListColumn) {
  return column.name || 'Entry';
}

/** Aligns entry values with `columns`: drops removed columns, fills new ones with '', and trims. */
export function entryValues(columns: ListColumn[], values: Record<string, string>) {
  return Object.fromEntries(columns.map((column) => [column.id, (values[column.id] ?? '').trim()]));
}
