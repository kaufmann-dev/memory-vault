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
  <div class="mx-auto mb-4 grid h-12 w-12 place-items-center rounded-2xl" style="background: var(--accent-light)">
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <rect width="20" height="20" x="2" y="2" rx="5" ry="5" />
      <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z" />
      <line x1="17.5" x2="17.51" y1="6.5" y2="6.5" />
    </svg>
  </div>
  <h2 class="text-lg font-semibold" style="color: var(--foreground)">{title}</h2>
  <p class="mx-auto mt-2 max-w-md text-sm leading-relaxed" style="color: var(--muted)">{description}</p>
  <div class="mt-6">
    {#if children}
      {@render children()}
    {/if}
  </div>
</div>
