<script lang="ts">
  import type { Snippet } from 'svelte';

  let {
    title,
    eyebrow = '',
    description = '',
    children
  }: {
    title: string;
    eyebrow?: string;
    description?: string;
    children?: Snippet;
  } = $props();
</script>

<header class="mb-8">
  {#if eyebrow}
    <p class="mb-2 text-xs font-semibold uppercase tracking-widest" style="color: var(--accent)">{eyebrow}</p>
  {/if}
  <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
    <div>
      <h1 class="text-2xl font-bold tracking-tight sm:text-3xl" style="color: var(--foreground)">{title}</h1>
      {#if description}
        <p class="mt-2 max-w-2xl text-sm leading-relaxed" style="color: var(--muted)">{description}</p>
      {/if}
    </div>
    {#if children}
      {@render children()}
    {/if}
  </div>
</header>
