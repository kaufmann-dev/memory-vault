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
  | 'list'
  | 'diagram'
  | 'metric_weight'
  | 'metric_blood'
  | 'metric_hormone'
  | 'day_counter'
  | 'family_tree';

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

export type WeightPayload = {
  date: string;
  weight: number;
};

export type BloodPayload = {
  date: string;
  sys: number;
  dia: number;
  pul: number;
};

export type HormonePayload = {
  date: string;
  lh: number | null;
  fsh: number | null;
  e2: number | null;
  prog: number | null;
  prl: number | null;
  t: number | null;
  bat: number | null;
  shbg: number | null;
  tsh: number | null;
};

export type FamilyPerson = {
  id: string;
  parentId: string | null;
  name: string;
  relation: string;
  birth: string;
  death: string;
  notes: string;
};

export type FamilyTreePayload = {
  people: FamilyPerson[];
};
