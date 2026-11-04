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
};

export const LIST_KINDS = ['plain', 'checklist', 'ordered'] as const;

export type ListKind = (typeof LIST_KINDS)[number];

export type ListColumn = {
  id: string;
  /** May be empty only when it is the list's sole column. */
  name: string;
};

export type ListEntry = {
  id: string;
  /** Keyed by column id. */
  values: Record<string, string>;
  done: boolean;
};

export type ListPayload = {
  title: string;
  description: string;
  kind: ListKind;
  /** Always at least one, in display order. */
  columns: ListColumn[];
  entries: ListEntry[];
  homePinned?: boolean;
};

export type NotePayload = {
  title?: string;
  text: string;
  groupIds: string[];
  pinned?: boolean;
  homePinned?: boolean;
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
  homePinned?: boolean;
  createdAt: string;
  updatedAt: string;
};

export type DayCounterPayload = {
  name: string;
  initiated: string;
  maxDays: number | null;
  homePinned?: boolean;
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
  x: number | null;
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
  /** Plot each series as a percentage of its own largest absolute value. */
  relativeToPeak: boolean;
  homePinned?: boolean;
};
