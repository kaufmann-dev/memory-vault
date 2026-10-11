import DOMPurify from 'dompurify';
import { Marked, type Token, type Tokens } from 'marked';

// Single line breaks render as <br> so text written in a plain textarea keeps its shape.
const markdown = new Marked({ gfm: true, breaks: true, async: false });

let linkHookAdded = false;

export function renderMarkdown(source: string) {
  if (!DOMPurify.isSupported) throw new Error('Markdown can only be rendered in the browser.');
  if (!linkHookAdded) {
    // Following a link in the same tab would drop the in-memory vault key.
    DOMPurify.addHook('afterSanitizeAttributes', (node) => {
      if (node.tagName !== 'A') return;
      node.setAttribute('target', '_blank');
      node.setAttribute('rel', 'noopener noreferrer');
    });
    linkHookAdded = true;
  }
  return DOMPurify.sanitize(markdown.parse(source) as string);
}

function plainText(tokens: Token[], separator: string): string {
  return tokens
    .map((token) => {
      if (token.type === 'html') return '';
      if (token.type === 'br') return '\n';
      if (token.type === 'list') return plainText((token as Tokens.List).items, '\n');
      if (token.type === 'table') {
        const table = token as Tokens.Table;
        return [...table.header, ...table.rows.flat()].map((cell) => plainText(cell.tokens, '')).join(' ');
      }
      if ('tokens' in token && token.tokens) {
        return plainText(token.tokens, token.type === 'blockquote' || token.type === 'list_item' ? '\n' : '');
      }
      return 'text' in token ? token.text : '';
    })
    .join(separator);
}

export function markdownToPlainText(source: string) {
  return plainText(markdown.lexer(source), '\n');
}

export function wordCount(source: string) {
  const text = markdownToPlainText(source).trim();
  return text ? text.split(/\s+/).length : 0;
}

// Replace value[from..to] with insert, then select selectionStart..selectionEnd.
export type TextEdit = {
  from: number;
  to: number;
  insert: string;
  selectionStart: number;
  selectionEnd: number;
};

export function applyTextEdit(value: string, edit: TextEdit) {
  return value.slice(0, edit.from) + edit.insert + value.slice(edit.to);
}

export function toggleInline(value: string, start: number, end: number, marker: string): TextEdit {
  const selected = value.slice(start, end);
  const size = marker.length;
  const wrapped = value.slice(start - size, start) === marker && value.slice(end, end + size) === marker;

  if (wrapped) {
    return { from: start - size, to: end + size, insert: selected, selectionStart: start - size, selectionEnd: end - size };
  }
  return { from: start, to: end, insert: `${marker}${selected}${marker}`, selectionStart: start + size, selectionEnd: end + size };
}

export type LineFormat = 'heading2' | 'heading3' | 'bullet' | 'numbered' | 'quote';

const BLOCK_PREFIX = /^(#{1,6} |[-*+] |\d+\. |> ?)/;

const lineFormats: Record<LineFormat, { pattern: RegExp; prefix: (index: number) => string }> = {
  heading2: { pattern: /^## /, prefix: () => '## ' },
  heading3: { pattern: /^### /, prefix: () => '### ' },
  bullet: { pattern: /^[-*+] /, prefix: () => '- ' },
  numbered: { pattern: /^\d+\. /, prefix: (index) => `${index + 1}. ` },
  quote: { pattern: /^> ?/, prefix: () => '> ' }
};

export function toggleLineFormat(value: string, start: number, end: number, format: LineFormat): TextEdit {
  const { pattern, prefix } = lineFormats[format];
  const from = value.lastIndexOf('\n', start - 1) + 1;
  const lineEnd = value.indexOf('\n', end);
  const to = lineEnd === -1 ? value.length : lineEnd;
  const lines = value.slice(from, to).split('\n');

  // Blank lines inside a multi-line selection stay untouched.
  const hasContent = lines.some((line) => line.trim());
  const isTarget = (line: string) => !hasContent || Boolean(line.trim());
  const remove = lines.filter(isTarget).every((line) => pattern.test(line));

  let index = 0;
  const insert = lines
    .map((line) => {
      if (!isTarget(line)) return line;
      if (remove) return line.replace(pattern, '');
      return prefix(index++) + line.replace(BLOCK_PREFIX, '');
    })
    .join('\n');

  const collapsed = start === end;
  return {
    from,
    to,
    insert,
    selectionStart: collapsed ? from + insert.length : from,
    selectionEnd: from + insert.length
  };
}
