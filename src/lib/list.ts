import type { ListColumn, ListPayload } from '#lib/types.js';

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

type LegacyListPayload = Omit<ListPayload, 'columns' | 'entries'> & {
  tasks: Array<{ id: string; text: string; done: boolean }>;
};

/** Temporary: stored list payloads may still use the pre-column shape. */
export type StoredListPayload = ListPayload | LegacyListPayload;

/** Temporary: converts a pre-column list to a single unnamed column; returns null when already current. */
export function migrateListPayload(payload: StoredListPayload): ListPayload | null {
  if (!('tasks' in payload)) return null;
  const { tasks, ...rest } = payload;
  const column = newColumn();
  return {
    ...rest,
    columns: [column],
    entries: tasks.map(({ id, text, done }) => ({ id, done, values: { [column.id]: text } }))
  };
}
