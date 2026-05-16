import { decrypt, encrypt } from '$lib/crypto';
import type { EncryptedRecord, RecordType } from '$lib/types';

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

export async function fetchEncryptedRecords(type: RecordType) {
  const response = await fetch(`/api/records?type=${encodeURIComponent(type)}`);
  return parseResponse<RecordResponse>(response).then((body) => body.records);
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

export async function createEncryptedRecord<T>(type: RecordType, payload: T, dek: CryptoKey) {
  const encrypted = await encrypt(dek, JSON.stringify(payload));
  const response = await fetch('/api/records', {
    method: 'POST',
    headers: { 'content-type': 'application/json' },
    body: JSON.stringify({ type, ciphertext: encrypted.ciphertext, iv: encrypted.iv })
  });

  return parseResponse<{ record: EncryptedRecord }>(response).then((body) => body.record);
}

export async function updateEncryptedRecord<T>(
  id: string,
  type: RecordType,
  payload: T,
  dek: CryptoKey
) {
  const encrypted = await encrypt(dek, JSON.stringify(payload));
  const response = await fetch('/api/records', {
    method: 'PATCH',
    headers: { 'content-type': 'application/json' },
    body: JSON.stringify({ id, type, ciphertext: encrypted.ciphertext, iv: encrypted.iv })
  });

  return parseResponse<{ record: EncryptedRecord }>(response).then((body) => body.record);
}

export async function deleteEncryptedRecord(id: string) {
  const response = await fetch('/api/records', {
    method: 'DELETE',
    headers: { 'content-type': 'application/json' },
    body: JSON.stringify({ id })
  });

  await parseResponse<{ ok: true }>(response);
}

export function wordCount(value: string) {
  const trimmed = value.trim();
  if (!trimmed) return 0;
  return trimmed.split(/[\s.?]+/).filter(Boolean).length;
}

export function randomId() {
  return crypto.randomUUID();
}
