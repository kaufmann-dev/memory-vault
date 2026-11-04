import assert from 'node:assert/strict';
import test from 'node:test';
import { entryValues } from '../src/lib/list.ts';

const columns = [
  { id: 'name', name: 'Name' },
  { id: 'dosage', name: 'Dosage' }
];

test('entryValues trims values and fills missing columns', () => {
  assert.deepEqual(entryValues(columns, { name: '  Ibuprofen ' }), { name: 'Ibuprofen', dosage: '' });
});

test('entryValues drops values of removed columns', () => {
  assert.deepEqual(entryValues(columns, { name: 'Ibuprofen', dosage: '400 mg', usage: 'as needed' }), {
    name: 'Ibuprofen',
    dosage: '400 mg'
  });
});
