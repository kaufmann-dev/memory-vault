// Temporary: delete together with migrateListPayload once all lists are migrated.
import assert from 'node:assert/strict';
import test from 'node:test';
import { migrateListPayload } from '../src/lib/list.ts';

const base = {
  title: 'Groceries',
  description: '',
  columns: [{ id: 'c', name: '' }],
  entries: [{ id: 'e', values: { c: 'Milk' }, done: true }],
  homePinned: true
};

test('migrateListPayload maps checklist: true to kind checklist', () => {
  assert.deepEqual(migrateListPayload({ ...base, checklist: true }), { ...base, kind: 'checklist' });
});

test('migrateListPayload maps checklist: false to kind plain', () => {
  assert.deepEqual(migrateListPayload({ ...base, checklist: false }), { ...base, kind: 'plain' });
});

test('migrateListPayload returns null for current payloads', () => {
  assert.equal(migrateListPayload({ ...base, kind: 'ordered' }), null);
});
