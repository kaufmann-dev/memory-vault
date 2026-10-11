import assert from 'node:assert/strict';
import test from 'node:test';
import { parseDiaryImport } from '../src/lib/diary-import.ts';

test('parses entries and applies defaults', () => {
  const entries = parseDiaryImport(
    JSON.stringify([
      { date: '2024-05-01', title: ' Walk ', body: ' Lake day. ', language: 'German', tags: [' a ', '', 'b'] },
      { date: '2024-05-03', body: 'Only body' }
    ])
  );
  assert.deepEqual(entries, [
    { occurredAt: '2024-05-01', title: 'Walk', body: 'Lake day.', language: 'German', tags: ['a', 'b'] },
    { occurredAt: '2024-05-03', title: '', body: 'Only body', language: 'English', tags: [] }
  ]);
});

test('preserves multi-line bodies', () => {
  const [entry] = parseDiaryImport('[{"date":"2024-05-01","body":"First.\\n\\nSecond."}]');
  assert.equal(entry.body, 'First.\n\nSecond.');
});

test('rejects invalid JSON', () => {
  assert.throws(() => parseDiaryImport('[{'), /^Error: Invalid JSON:/);
});

test('rejects non-array and empty array input', () => {
  assert.throws(() => parseDiaryImport('{"date":"2024-05-01","body":"x"}'), /JSON array/);
  assert.throws(() => parseDiaryImport('[]'), /JSON array/);
});

test('rejects invalid entries with the entry number', () => {
  const ok = { date: '2024-05-01', body: 'x' };
  const cases: Array<[unknown, RegExp]> = [
    [{ date: '2024-05-01' }, /"body"/],
    [{ date: '2024-05-01', body: '   ' }, /"body"/],
    [{ date: '01.05.2024', body: 'x' }, /"date"/],
    [{ date: '2024-02-30', body: 'x' }, /"date"/],
    [{ ...ok, language: 'French' }, /"language"/],
    [{ ...ok, tags: ['a', 1] }, /"tags"/],
    [{ ...ok, tags: 'a' }, /"tags"/],
    [{ ...ok, title: 5 }, /"title"/],
    [{ ...ok, mood: 'happy' }, /"mood" is not an allowed key/],
    ['text', /must be an object/]
  ];
  for (const [entry, pattern] of cases) {
    assert.throws(() => parseDiaryImport(JSON.stringify([ok, entry])), (error: Error) => {
      assert.match(error.message, /^Entry 2: /);
      assert.match(error.message, pattern);
      return true;
    });
  }
});
