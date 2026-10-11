import { get, writable } from 'svelte/store';
import { decryptRecords, fetchEncryptedRecords, updateEncryptedRecords } from '#lib/client/records.js';
import { markdownToPlainText } from '#lib/markdown.js';
import type { DiaryPayload, EncryptedRecord } from '#lib/types.js';

export type DiaryItem = {
  record: EncryptedRecord;
  payload: DiaryPayload;
};

export const diaryEntries = writable<DiaryItem[]>([]);

let loaded = false;

function sortEntries(items: DiaryItem[]) {
  return [...items].sort((a, b) => b.payload.occurredAt.localeCompare(a.payload.occurredAt));
}

export async function loadDiary(dek: CryptoKey, force = false) {
  if (loaded && !force) return;
  const records = await fetchEncryptedRecords('diary');
  diaryEntries.set(sortEntries(await decryptRecords<DiaryPayload>(records, dek)));
  loaded = true;
}

export function upsertDiaryEntry(item: DiaryItem) {
  diaryEntries.update((items) => {
    const exists = items.some((entry) => entry.record.id === item.record.id);
    const next = exists
      ? items.map((entry) => (entry.record.id === item.record.id ? item : entry))
      : [...items, item];
    return sortEntries(next);
  });
}

export function addDiaryEntries(newItems: DiaryItem[]) {
  diaryEntries.update((items) => sortEntries([...items, ...newItems]));
}

// Replaces tag with next in place, or drops it when next is null. Renaming onto an existing tag merges both.
export function replaceTag(tags: string[], tag: string, next: string | null) {
  return [...new Set(tags.flatMap((value) => (value !== tag ? [value] : next ? [next] : [])))];
}

export async function replaceDiaryTag(tag: string, next: string | null, dek: CryptoKey) {
  const updates = get(diaryEntries)
    .filter((item) => item.payload.tags.includes(tag))
    .map((item) => ({
      id: item.record.id,
      payload: { ...item.payload, tags: replaceTag(item.payload.tags, tag, next) }
    }));
  if (updates.length === 0) return;

  const records = await updateEncryptedRecords('diary', updates, dek);
  const updated = new Map(records.map((record, index) => [record.id, { record, payload: updates[index].payload }]));
  diaryEntries.update((items) => items.map((item) => updated.get(item.record.id) ?? item));
}

export function removeDiaryEntry(id: string) {
  diaryEntries.update((items) => items.filter((entry) => entry.record.id !== id));
}

export function resetDiary() {
  diaryEntries.set([]);
  loaded = false;
}

export function formatDate(value: string) {
  return new Intl.DateTimeFormat(undefined, {
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  }).format(new Date(`${value}T00:00:00`));
}

export function formatShortDate(value: string) {
  return new Intl.DateTimeFormat(undefined, {
    month: 'short',
    day: 'numeric'
  }).format(new Date(`${value}T00:00:00`));
}

export function formatMonth(value: string) {
  return new Intl.DateTimeFormat(undefined, {
    year: 'numeric',
    month: 'long'
  }).format(new Date(`${value}-01T00:00:00`));
}

export function excerpt(markdown: string) {
  const compact = markdownToPlainText(markdown).replace(/\s+/g, ' ').trim();
  if (compact.length <= 150) return compact;
  return `${compact.slice(0, 150).trim()}...`;
}
