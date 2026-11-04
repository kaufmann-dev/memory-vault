import assert from 'node:assert/strict';
import test from 'node:test';
import { defaultXAxis, migrateDiagramPayload } from '../src/lib/diagram.ts';
import { migrateListPayload } from '../src/lib/list.ts';

test('migrateListPayload converts tasks to entries in one unnamed column', () => {
  const migrated = migrateListPayload({
    title: 'Groceries',
    description: '',
    checklist: true,
    homePinned: true,
    tasks: [
      { id: 'a', text: 'Milk', done: false },
      { id: 'b', text: 'Bread', done: true }
    ]
  });
  assert.ok(migrated);
  assert.equal('tasks' in migrated, false);
  assert.equal(migrated.homePinned, true);
  assert.equal(migrated.columns.length, 1);
  const [column] = migrated.columns;
  assert.equal(column.name, '');
  assert.deepEqual(migrated.entries, [
    { id: 'a', done: false, values: { [column.id]: 'Milk' } },
    { id: 'b', done: true, values: { [column.id]: 'Bread' } }
  ]);
});

test('migrateListPayload leaves current payloads alone', () => {
  assert.equal(
    migrateListPayload({ title: 'x', description: '', checklist: false, columns: [{ id: 'c', name: '' }], entries: [] }),
    null
  );
});

const diagram = {
  title: 'Weight',
  description: '',
  fields: [{ id: 'w', label: 'Weight', unit: 'kg', color: '#2563eb' }]
};

test('migrateDiagramPayload fills the x axis and measurement x values', () => {
  const migrated = migrateDiagramPayload({
    ...diagram,
    measurements: [{ id: 'm', date: '2026-01-01T08:00', values: { w: 80 } }]
  });
  assert.ok(migrated);
  assert.deepEqual(migrated.xAxis, defaultXAxis());
  assert.equal(migrated.measurements[0].x, null);
  assert.deepEqual(migrated.measurements[0].values, { w: 80 });
});

test('migrateDiagramPayload leaves current payloads alone', () => {
  assert.equal(
    migrateDiagramPayload({
      ...diagram,
      xAxis: { type: 'number', label: 'Minutes', unit: 'min' },
      measurements: [{ id: 'm', date: '2026-01-01T08:00', x: 5, values: { w: 80 } }]
    }),
    null
  );
});
