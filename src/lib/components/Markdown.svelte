<script lang="ts">
  import { renderMarkdown } from '#lib/markdown.js';
  import { cn } from '#lib/utils.js';

  let { source, class: className }: { source: string; class?: string } = $props();

  let html = $derived(renderMarkdown(source));
</script>

<!-- renderMarkdown sanitizes the generated HTML with DOMPurify. -->
<div class={cn('markdown', className)}>{@html html}</div>
