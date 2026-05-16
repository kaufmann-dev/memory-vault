<script lang="ts">
  import type { Snippet } from 'svelte';

  let {
    title,
    description,
    children
  }: {
    title: string;
    description: string;
    children?: Snippet;
  } = $props();
</script>

<div class="rounded-xl border p-8 text-center" style="border-color: var(--border); background: var(--surface)">
  <h2 class="text-base font-semibold">{title}</h2>
  <p class="mx-auto mt-2 max-w-md text-sm leading-6" style="color: var(--muted)">{description}</p>
  <div class="mt-5">
    {#if children}
      {@render children()}
    {/if}
  </div>
</div>
