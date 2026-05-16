<script lang="ts">
  import '../app.css';
  import AppShell from '$lib/components/AppShell.svelte';
  import type { LayoutProps } from './$types';

  let { data, children }: LayoutProps = $props();
</script>

<svelte:head>
  <title>The Second Directory</title>
  <meta name="description" content="A private encrypted personal directory." />
</svelte:head>

{#if data.user}
  <AppShell user={data.user}>
    {@render children()}
  </AppShell>
{:else}
  {@render children()}
{/if}
