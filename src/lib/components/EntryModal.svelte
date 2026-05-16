<script lang="ts">
  import type { Snippet } from 'svelte';
  import { fade, fly } from 'svelte/transition';
  import { cubicOut } from 'svelte/easing';
  import { X } from '@lucide/svelte';

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

  $effect(() => {
    if (typeof document !== 'undefined') {
      document.body.style.overflow = open ? 'hidden' : '';
    }
  });
</script>

<svelte:window
  onkeydown={(event) => {
    if (open && event.key === 'Escape') onClose();
  }}
/>

{#if open}
  <div class="fixed inset-0 z-50 grid place-items-center px-4 py-6" role="dialog" aria-modal="true" transition:fade={{ duration: 140 }}>
    <button
      class="absolute inset-0 bg-black/50"
      type="button"
      aria-label="Close form"
      onclick={onClose}
    ></button>

    <section
      class="relative flex max-h-[min(760px,calc(100vh-3rem))] w-full max-w-2xl flex-col overflow-hidden vault-card"
      style="box-shadow: var(--shadow-lg)"
      transition:fly={{ y: 18, duration: 180, easing: cubicOut }}
    >
      <div class="flex items-start justify-between gap-4 border-b p-5" style="border-color: var(--border)">
        <div>
          <h2 class="text-lg font-semibold" style="color: var(--foreground)">{title}</h2>
          {#if description}
            <p class="mt-1 text-sm leading-relaxed" style="color: var(--muted)">{description}</p>
          {/if}
        </div>
        <button class="focus-ring vault-btn-ghost shrink-0" type="button" onclick={onClose} aria-label="Close form">
          <X size={16} />
        </button>
      </div>

      <div class="overflow-y-auto p-5">
        {@render children()}
      </div>
    </section>
  </div>
{/if}
