import assert from 'node:assert/strict';
import test from 'node:test';
import { entryValues, moveEntry } from '../src/lib/list.ts';

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

const entries = ['a', 'b', 'c'].map((id) => ({ id, values: {}, done: false }));
const ids = (list: typeof entries) => list.map((entry) => entry.id);

test('moveEntry swaps an entry with its neighbour', () => {
  assert.deepEqual(ids(moveEntry(entries, 'b', -1)), ['b', 'a', 'c']);
  assert.deepEqual(ids(moveEntry(entries, 'b', 1)), ['a', 'c', 'b']);
});

test('moveEntry leaves entries unchanged at the edges or for an unknown id', () => {
  assert.equal(moveEntry(entries, 'a', -1), entries);
  assert.equal(moveEntry(entries, 'c', 1), entries);
  assert.equal(moveEntry(entries, 'missing', 1), entries);
});
