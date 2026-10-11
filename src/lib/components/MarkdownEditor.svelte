<script lang="ts">
  import Markdown from '#lib/components/Markdown.svelte';
  import { applyTextEdit, toggleInline, toggleLineFormat, type LineFormat, type TextEdit } from '#lib/markdown.js';
  import { tick, type Component } from 'svelte';
  import { Bold, Eye, Heading2, Heading3, Italic, List, ListOrdered, PenLine, TextQuote } from '@lucide/svelte';
  import { Button } from '#lib/components/ui/button/index.js';
  import { Textarea } from '#lib/components/ui/textarea/index.js';
  import { cn } from '#lib/utils.js';

  let {
    value = $bindable(''),
    id,
    required = false,
    class: className
  }: {
    value: string;
    id?: string;
    required?: boolean;
    class?: string;
  } = $props();

  type Tool = {
    label: string;
    icon: Component;
    edit: (value: string, start: number, end: number) => TextEdit;
  };

  const line = (format: LineFormat) => (text: string, start: number, end: number) =>
    toggleLineFormat(text, start, end, format);

  const tools: Tool[] = [
    { label: 'Heading', icon: Heading2, edit: line('heading2') },
    { label: 'Subheading', icon: Heading3, edit: line('heading3') },
    { label: 'Bold (Ctrl+B)', icon: Bold, edit: (text, start, end) => toggleInline(text, start, end, '**') },
    { label: 'Italic (Ctrl+I)', icon: Italic, edit: (text, start, end) => toggleInline(text, start, end, '_') },
    { label: 'Bullet list', icon: List, edit: line('bullet') },
    { label: 'Numbered list', icon: ListOrdered, edit: line('numbered') },
    { label: 'Quote', icon: TextQuote, edit: line('quote') }
  ];

  let textarea: HTMLTextAreaElement | null = $state(null);
  let preview = $state(false);

  async function apply(edit: Tool['edit']) {
    if (!textarea) return;
    const change = edit(value, textarea.selectionStart, textarea.selectionEnd);
    textarea.focus();
    textarea.setSelectionRange(change.from, change.to);
    // execCommand keeps the change in the browser's undo history; fall back to a direct update.
    const inserted = change.insert
      ? document.execCommand('insertText', false, change.insert)
      : document.execCommand('delete');
    if (!inserted) {
      value = applyTextEdit(value, change);
      await tick();
    }
    textarea.setSelectionRange(change.selectionStart, change.selectionEnd);
  }

  function handleKeydown(event: KeyboardEvent) {
    if (!(event.ctrlKey || event.metaKey) || event.altKey || event.shiftKey) return;
    const key = event.key.toLowerCase();
    if (key !== 'b' && key !== 'i') return;
    event.preventDefault();
    apply((text, start, end) => toggleInline(text, start, end, key === 'b' ? '**' : '_'));
  }
</script>

<div class="grid gap-2">
  <!-- Sticky so the tools stay reachable while the dialog scrolls through a long entry; the
       negative offsets cancel the dialog's p-6 so the bar pins flush to its top edge. -->
  <div class="bg-popover sticky -top-6 z-10 -mx-6 flex flex-wrap items-center justify-between gap-2 px-6 py-2">
    <div class="bg-muted inline-flex gap-1 rounded-lg p-1">
      <Button variant={preview ? 'ghost' : 'default'} size="sm" onclick={() => (preview = false)}>
        <PenLine class="size-4" /> Write
      </Button>
      <Button variant={preview ? 'default' : 'ghost'} size="sm" onclick={() => (preview = true)}>
        <Eye class="size-4" /> Preview
      </Button>
    </div>
    <div class="flex flex-wrap items-center gap-0.5" role="toolbar" aria-label="Formatting">
      {#each tools as tool (tool.label)}
        <Button
          variant="ghost"
          size="icon-sm"
          disabled={preview}
          aria-label={tool.label}
          title={tool.label}
          onclick={() => apply(tool.edit)}
        >
          <tool.icon class="size-4" />
        </Button>
      {/each}
    </div>
  </div>

  {#if preview}
    <div class={cn('min-h-64 rounded-2xl border px-3 py-2', className)}>
      {#if value.trim()}
        <Markdown source={value} />
      {:else}
        <p class="text-muted-foreground text-sm">Nothing to preview.</p>
      {/if}
    </div>
  {:else}
    <Textarea {id} bind:ref={textarea} bind:value {required} onkeydown={handleKeydown} class={cn('min-h-64 leading-relaxed', className)} />
  {/if}
</div>
