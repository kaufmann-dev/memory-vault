import assert from 'node:assert/strict';
import test from 'node:test';
import {
  applyTextEdit,
  markdownToPlainText,
  toggleInline,
  toggleLineFormat,
  wordCount
} from '../src/lib/markdown.ts';

test('strips markdown syntax to plain text', () => {
  const text = markdownToPlainText('## Lake day\nWe swam **far**.\n\n- one\n- _two_\n\n> quoted [link](https://x.test)');
  assert.equal(text.replace(/\s+/g, ' '), 'Lake day We swam far. one two quoted link');
});

test('keeps words on separate lines apart', () => {
  assert.equal(markdownToPlainText('first\nsecond').replace(/\s+/g, ' '), 'first second');
});

test('counts words without markdown syntax', () => {
  assert.equal(wordCount('## Title\n\n- **bold** item\n- 3.5 km'), 5);
  assert.equal(wordCount('   '), 0);
});

test('wraps and unwraps inline markers', () => {
  const wrapped = toggleInline('a word b', 2, 6, '**');
  const value = applyTextEdit('a word b', wrapped);
  assert.equal(value, 'a **word** b');
  assert.deepEqual([wrapped.selectionStart, wrapped.selectionEnd], [4, 8]);

  const unwrapped = toggleInline(value, 4, 8, '**');
  assert.equal(applyTextEdit(value, unwrapped), 'a word b');
  assert.deepEqual([unwrapped.selectionStart, unwrapped.selectionEnd], [2, 6]);
});

test('numbers selected lines and skips blank lines', () => {
  const value = 'intro\none\n\n- two\nend';
  const edit = toggleLineFormat(value, 6, 16, 'numbered');
  assert.equal(applyTextEdit(value, edit), 'intro\n1. one\n\n2. two\nend');
});

test('toggles a line prefix off when every line has it', () => {
  const value = '> a\n> b';
  assert.equal(applyTextEdit(value, toggleLineFormat(value, 0, value.length, 'quote')), 'a\nb');
});

test('replaces another heading level on the current line', () => {
  const value = '### Title';
  assert.equal(applyTextEdit(value, toggleLineFormat(value, 5, 5, 'heading2')), '## Title');
});
