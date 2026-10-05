export type SafeUser = {
  id: string;
  email: string;
  name: string;
  role: 'admin';
  kekSalt: string;
  encryptedDEK: string;
  dekIV: string;
};

export type EncryptedRecord = {
  id: string;
  type: RecordType;
  ciphertext: string;
  iv: string;
  createdAt: string;
  updatedAt: string;
};

export const RECORD_TYPES = [
  'diary',
  'note',
  'note_group',
  'list',
  'diagram',
  'day_counter',
  'secret'
] as const;

export type RecordType = (typeof RECORD_TYPES)[number];

export type SecretCategory =
  | 'password'
  | 'api_key'
  | 'encryption_key'
  | 'wifi'
  | 'pgp_key'
  | 'bank_account'
  | 'vpn'
  | 'remote_connection'
  | 'totp';

export type DiaryPayload = {
  title: string;
  body: string;
  occurredAt: string;
  language: 'English' | 'German' | 'Other';
  tags: string[];
  wordCount: number;
};

export type ListPayload = {
  title: string;
  description: string;
  checklist: boolean;
  tasks: Array<{
    id: string;
    text: string;
    done: boolean;
  }>;
};

export type NotePayload = {
  title?: string;
  text: string;
  groupIds: string[];
  pinned?: boolean;
  createdAt: string;
  updatedAt: string;
};

export type NoteGroupPayload = {
  name: string;
  description: string;
  color: string;
};

export type SecretPayload = {
  title: string;
  category: SecretCategory;
  username: string;
  secret: string;
  publicKey: string;
  fingerprint: string;
  passphrase: string;
  iban: string;
  accountHolder: string;
  bank: string;
  bic: string;
  vpnProtocol: string;
  vpnGateway: string;
  vpnNtDomain: string;
  remoteProtocol: string;
  remoteServer: string;
  remoteDomain: string;
  otpAlgorithm: string;
  otpDigits: number;
  otpPeriod: number;
  notes: string;
  createdAt: string;
  updatedAt: string;
};

export type DayCounterPayload = {
  name: string;
  initiated: string;
  maxDays: number | null;
};

export type DiagramField = {
  id: string;
  label: string;
  unit: string;
  color: string;
};

export type DiagramMeasurement = {
  id: string;
  date: string;
  x?: number | null;
  values: Record<string, number | null>;
};

export type DiagramPayload = {
  title: string;
  description: string;
  xAxis: {
    type: 'datetime' | 'number';
    label: string;
    unit: string;
  };
  fields: DiagramField[];
  measurements: DiagramMeasurement[];
};
