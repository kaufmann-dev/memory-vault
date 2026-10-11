import type { DiaryPayload } from '#lib/types.js';

export const DIARY_LANGUAGES = ['English', 'German', 'Other'] as const;

export type DiaryImportEntry = Omit<DiaryPayload, 'wordCount'>;

const allowedKeys = new Set(['date', 'title', 'body', 'language', 'tags']);
const datePattern = /^\d{4}-\d{2}-\d{2}$/;

function isValidDate(value: string) {
  if (!datePattern.test(value)) return false;
  const date = new Date(`${value}T00:00:00Z`);
  return !Number.isNaN(date.getTime()) && date.toISOString().slice(0, 10) === value;
}

function parseEntry(value: unknown, entryNumber: number): DiaryImportEntry {
  const fail = (message: string): never => {
    throw new Error(`Entry ${entryNumber}: ${message}`);
  };

  if (typeof value !== 'object' || value === null || Array.isArray(value)) fail('must be an object.');
  const item = value as Record<string, unknown>;

  const unknownKey = Object.keys(item).find((key) => !allowedKeys.has(key));
  if (unknownKey) fail(`"${unknownKey}" is not an allowed key.`);

  if (typeof item.date !== 'string' || !isValidDate(item.date)) fail('"date" must be a valid YYYY-MM-DD date.');
  if (typeof item.body !== 'string' || !item.body.trim()) fail('"body" must be a non-empty string.');
  if (item.title !== undefined && typeof item.title !== 'string') fail('"title" must be a string.');
  if (item.language !== undefined && !DIARY_LANGUAGES.includes(item.language as DiaryPayload['language'])) {
    fail(`"language" must be one of ${DIARY_LANGUAGES.join(', ')}.`);
  }
  if (item.tags !== undefined && (!Array.isArray(item.tags) || item.tags.some((tag) => typeof tag !== 'string'))) {
    fail('"tags" must be an array of strings.');
  }

  return {
    occurredAt: item.date as string,
    title: ((item.title as string | undefined) ?? '').trim(),
    body: (item.body as string).trim(),
    language: (item.language as DiaryPayload['language'] | undefined) ?? 'English',
    tags: ((item.tags as string[] | undefined) ?? []).map((tag) => tag.trim()).filter(Boolean)
  };
}

export function parseDiaryImport(text: string): DiaryImportEntry[] {
  let data: unknown;
  try {
    data = JSON.parse(text);
  } catch (error) {
    throw new Error(`Invalid JSON: ${error instanceof Error ? error.message : 'could not parse the text.'}`);
  }

  if (!Array.isArray(data) || data.length === 0) throw new Error('Paste one JSON array with at least one entry.');
  return data.map((value, index) => parseEntry(value, index + 1));
}
