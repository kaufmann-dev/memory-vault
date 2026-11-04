import type { ListColumn, ListEntry, ListKind, ListPayload } from '#lib/types.js';

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

type LegacyListPayload = Omit<ListPayload, 'kind'> & { checklist: boolean };

/** Temporary: stored list payloads may still use the boolean `checklist` flag. */
export type StoredListPayload = ListPayload | LegacyListPayload;

/** Temporary: converts the `checklist` flag to `kind`; returns null when already current. */
export function migrateListPayload(payload: StoredListPayload): ListPayload | null {
  if (!('checklist' in payload)) return null;
  const { checklist, ...rest } = payload;
  return { ...rest, kind: checklist ? 'checklist' : 'plain' };
}
