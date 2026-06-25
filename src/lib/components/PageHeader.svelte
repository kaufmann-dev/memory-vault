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
    <p class="text-muted-foreground mb-2 text-xs font-semibold tracking-widest uppercase">{eyebrow}</p>
  {/if}
  <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
    <div>
      <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">{title}</h1>
      {#if description}
        <p class="text-muted-foreground mt-2 max-w-2xl text-sm leading-relaxed">{description}</p>
      {/if}
    </div>
    {#if children}
      {@render children()}
    {/if}
  </div>
</header>
