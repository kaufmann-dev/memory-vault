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

export type RecordType =
  | 'diary'
  | 'note'
  | 'note_group'
  | 'list'
  | 'diagram'
  | 'day_counter';

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
  text: string;
  groupIds: string[];
  createdAt: string;
  updatedAt: string;
};

export type NoteGroupPayload = {
  name: string;
  description: string;
  color: string;
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
  values: Record<string, number | null>;
};

export type DiagramPayload = {
  title: string;
  description: string;
  fields: DiagramField[];
  measurements: DiagramMeasurement[];
};
