import { writable } from 'svelte/store';
import { decryptRecords, fetchEncryptedRecords } from '#lib/client/records.js';
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

export function excerpt(value: string) {
  const compact = value.replace(/\s+/g, ' ').trim();
  if (compact.length <= 150) return compact;
  return `${compact.slice(0, 150).trim()}...`;
}
