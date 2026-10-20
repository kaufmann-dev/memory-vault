import { decrypt, encrypt } from '#lib/crypto.js';
import type { EncryptedRecord, RecordType } from '#lib/types.js';

type RecordResponse = {
  records: EncryptedRecord[];
};

async function parseResponse<T>(response: Response): Promise<T> {
  if (!response.ok) {
    const message = await response.text();
    throw new Error(message || 'Request failed');
  }

  return response.json() as Promise<T>;
}

export async function fetchEncryptedRecords(type?: RecordType) {
  const response = await fetch(type ? `/api/records?type=${encodeURIComponent(type)}` : '/api/records');
  return parseResponse<RecordResponse>(response).then((body) => body.records);
}

export function countRecords(records: EncryptedRecord[]): Record<RecordType, number> {
  const counts = {
    diary: 0,
    note: 0,
    note_group: 0,
    list: 0,
    diagram: 0,
    day_counter: 0,
    secret: 0
  } satisfies Record<RecordType, number>;
  for (const record of records) counts[record.type]++;
  return counts;
}

export async function decryptRecord<T>(record: EncryptedRecord, dek: CryptoKey) {
  return JSON.parse(await decrypt(dek, record.ciphertext, record.iv)) as T;
}

export async function decryptRecords<T>(records: EncryptedRecord[], dek: CryptoKey) {
  return Promise.all(
    records.map(async (record) => ({
      record,
      payload: await decryptRecord<T>(record, dek)
    }))
  );
}

export async function createEncryptedRecords<T>(type: RecordType, payloads: T[], dek: CryptoKey) {
  const records = await Promise.all(payloads.map((payload) => encrypt(dek, JSON.stringify(payload))));
  const response = await fetch('/api/records', {
    method: 'POST',
    headers: { 'content-type': 'application/json' },
    body: JSON.stringify({ type, records })
  });

  return parseResponse<RecordResponse>(response).then((body) => body.records);
}

export async function createEncryptedRecord<T>(type: RecordType, payload: T, dek: CryptoKey) {
  const [record] = await createEncryptedRecords(type, [payload], dek);
  return record;
}

export async function updateEncryptedRecords<T>(
  type: RecordType,
  updates: Array<{ id: string; payload: T }>,
  dek: CryptoKey
) {
  const records = await Promise.all(
    updates.map(async ({ id, payload }) => ({ id, ...(await encrypt(dek, JSON.stringify(payload))) }))
  );
  const response = await fetch('/api/records', {
    method: 'PATCH',
    headers: { 'content-type': 'application/json' },
    body: JSON.stringify({ type, records })
  });

  return parseResponse<RecordResponse>(response).then((body) => body.records);
}

export async function updateEncryptedRecord<T>(
  id: string,
  type: RecordType,
  payload: T,
  dek: CryptoKey
) {
  const [record] = await updateEncryptedRecords(type, [{ id, payload }], dek);
  return record;
}

export async function deleteEncryptedRecord(id: string) {
  const response = await fetch('/api/records', {
    method: 'DELETE',
    headers: { 'content-type': 'application/json' },
    body: JSON.stringify({ id })
  });

  await parseResponse<{ ok: true }>(response);
}

export function randomId() {
  return crypto.randomUUID();
}
