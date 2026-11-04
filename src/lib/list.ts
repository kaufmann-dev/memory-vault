import type { ListColumn, ListEntry, ListKind } from '#lib/types.js';

export const listKindLabels: Record<ListKind, string> = {
  plain: 'List',
  checklist: 'Checklist',
  ordered: 'Ordered list'
};

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

/** Swaps the entry with its neighbour in `offset` direction; returns `entries` unchanged when it cannot move. */
export function moveEntry(entries: ListEntry[], entryId: string, offset: -1 | 1) {
  const from = entries.findIndex((entry) => entry.id === entryId);
  const to = from + offset;
  if (from === -1 || to < 0 || to >= entries.length) return entries;
  const next = [...entries];
  [next[from], next[to]] = [next[to], next[from]];
  return next;
}
