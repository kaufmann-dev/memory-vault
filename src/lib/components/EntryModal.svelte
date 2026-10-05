<script lang="ts">
  import type { Snippet } from 'svelte';
  import * as Dialog from '#lib/components/ui/dialog/index.js';

  let {
    open,
    title,
    description = '',
    onClose,
    children
  }: {
    open: boolean;
    title: string;
    description?: string;
    onClose: () => void;
    children: Snippet;
  } = $props();
</script>

<Dialog.Root {open} onOpenChange={(value) => { if (!value) onClose(); }}>
  <Dialog.Content class="max-h-[min(90vh,760px)] gap-0 overflow-y-auto sm:max-w-2xl">
    <Dialog.Header class="mb-5 wrap-anywhere">
      <Dialog.Title>{title}</Dialog.Title>
      {#if description}
        <Dialog.Description>{description}</Dialog.Description>
      {/if}
    </Dialog.Header>
    {@render children()}
  </Dialog.Content>
</Dialog.Root>
