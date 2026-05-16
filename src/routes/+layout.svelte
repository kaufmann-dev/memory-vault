<script lang="ts">
  import '../app.css';
  import AppShell from '$lib/components/AppShell.svelte';
  import VaultUnlockGate from '$lib/components/VaultUnlockGate.svelte';
  import type { LayoutProps } from './$types';

  let { data, children }: LayoutProps = $props();
</script>

<svelte:head>
  <title>Memory Vault</title>
  <meta name="description" content="A private encrypted personal vault." />
</svelte:head>

{#if data.user}
  <AppShell user={data.user}>
    <VaultUnlockGate user={data.user}>
      {@render children()}
    </VaultUnlockGate>
  </AppShell>
{:else}
  {@render children()}
{/if}
