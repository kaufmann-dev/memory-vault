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

<div class="vault-card p-10 text-center">
  <h2 class="text-base font-semibold" style="color: var(--foreground)">{title}</h2>
  <p class="mx-auto mt-2 max-w-md text-sm leading-relaxed" style="color: var(--muted)">{description}</p>
  <div class="mt-5">
    {#if children}
      {@render children()}
    {/if}
  </div>
</div>
