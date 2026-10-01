import { RECORD_TYPES, type EncryptedRecord, type RecordType } from '#lib/types.js';

export const BACKUP_APP = 'memory-vault';
export const BACKUP_VERSION = 1;
export const BACKUP_EXTENSION = 'mvault';

const recordTypes = new Set<string>(RECORD_TYPES);
const uuidPattern = /^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;
const base64Pattern = /^[A-Za-z0-9+/]+={0,2}$/;

export type BackupKeyMaterial = {
  kekSalt: string;
  encryptedDEK: string;
  dekIV: string;
};

export type BackupEncryptedPayload = {
  ciphertext: string;
  iv: string;
};

export type VaultBackupFile = {
  app: typeof BACKUP_APP;
  version: typeof BACKUP_VERSION;
  keyMaterial: BackupKeyMaterial;
  payload: BackupEncryptedPayload;
};

export type BackupPayload = {
  exportedAt: string;
  user: {
    email: string;
    name: string;
  };
  records: EncryptedRecord[];
};

export type RestoreBackupRequest = {
  keyMaterial: BackupKeyMaterial;
  records: EncryptedRecord[];
};

function isObject(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function assertObject(value: unknown, label: string): Record<string, unknown> {
  if (!isObject(value)) throw new Error(`Invalid ${label}`);
  return value;
}

function assertString(value: unknown, label: string) {
  if (typeof value !== 'string' || !value.trim()) throw new Error(`Invalid ${label}`);
  return value;
}

function assertBase64(value: unknown, label: string) {
  const text = assertString(value, label);
  if (text.length % 4 !== 0 || !base64Pattern.test(text)) throw new Error(`Invalid ${label}`);
  return text;
}

function assertDate(value: unknown, label: string) {
  const text = assertString(value, label);
  if (Number.isNaN(Date.parse(text))) throw new Error(`Invalid ${label}`);
  return text;
}

function assertRecordType(value: unknown): RecordType {
  const type = assertString(value, 'record type');
  if (!recordTypes.has(type)) throw new Error('Invalid record type');
  return type as RecordType;
}

export function parseBackupKeyMaterial(value: unknown): BackupKeyMaterial {
  const input = assertObject(value, 'backup key material');
  return {
    kekSalt: assertBase64(input.kekSalt, 'kek salt'),
    encryptedDEK: assertBase64(input.encryptedDEK, 'encrypted DEK'),
    dekIV: assertBase64(input.dekIV, 'DEK IV')
  };
}

export function parseEncryptedPayload(value: unknown): BackupEncryptedPayload {
  const input = assertObject(value, 'encrypted backup payload');
  return {
    ciphertext: assertBase64(input.ciphertext, 'backup ciphertext'),
    iv: assertBase64(input.iv, 'backup IV')
  };
}

export function parseEncryptedRecord(value: unknown): EncryptedRecord {
  const input = assertObject(value, 'encrypted record');
  const id = assertString(input.id, 'record id');
  if (!uuidPattern.test(id)) throw new Error('Invalid record id');

  return {
    id,
    type: assertRecordType(input.type),
    ciphertext: assertBase64(input.ciphertext, 'record ciphertext'),
    iv: assertBase64(input.iv, 'record IV'),
    createdAt: assertDate(input.createdAt, 'record creation date'),
    updatedAt: assertDate(input.updatedAt, 'record update date')
  };
}

export function parseBackupFile(value: unknown): VaultBackupFile {
  const input = assertObject(value, 'backup file');
  if (input.app !== BACKUP_APP || input.version !== BACKUP_VERSION) {
    throw new Error('Unsupported backup file');
  }

  return {
    app: BACKUP_APP,
    version: BACKUP_VERSION,
    keyMaterial: parseBackupKeyMaterial(input.keyMaterial),
    payload: parseEncryptedPayload(input.payload)
  };
}

export function parseBackupPayload(value: unknown): BackupPayload {
  const input = assertObject(value, 'backup payload');
  const user = assertObject(input.user, 'backup user');
  const records = Array.isArray(input.records) ? input.records.map(parseEncryptedRecord) : null;
  if (!records) throw new Error('Invalid backup records');

  return {
    exportedAt: assertDate(input.exportedAt, 'backup export date'),
    user: {
      email: assertString(user.email, 'backup user email'),
      name: assertString(user.name, 'backup user name')
    },
    records
  };
}

export function parseRestoreBackupRequest(value: unknown): RestoreBackupRequest {
  const input = assertObject(value, 'restore request');
  const records = Array.isArray(input.records) ? input.records.map(parseEncryptedRecord) : null;
  if (!records) throw new Error('Invalid restore records');

  return {
    keyMaterial: parseBackupKeyMaterial(input.keyMaterial),
    records
  };
}
