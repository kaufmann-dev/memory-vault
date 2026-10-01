<script lang="ts">
  import '../app.css';
  import {
    USER_ACTIVITY_HEADER,
    USER_ACTIVITY_HEADER_VALUE,
    USER_ACTIVITY_PATH,
    shouldSendActivitySignal
  } from '#lib/auth-policy.js';
  import AppShell from '#lib/components/AppShell.svelte';
  import VaultUnlockGate from '#lib/components/VaultUnlockGate.svelte';
  import { Toaster } from '#lib/components/ui/sonner/index.js';
  import type { LayoutProps } from './$types';

  let { data, children }: LayoutProps = $props();
  let lastActivitySignalAt = -Infinity;

  function signalUserActivity(event: Event) {
    if (!data.user) return;

    const now = Date.now();
    if (!shouldSendActivitySignal(event.isTrusted, now, lastActivitySignalAt)) return;
    lastActivitySignalAt = now;

    void fetch(USER_ACTIVITY_PATH, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { [USER_ACTIVITY_HEADER]: USER_ACTIVITY_HEADER_VALUE },
      keepalive: true
    }).catch(() => {});
  }
</script>

<svelte:window
  onpointerdown={signalUserActivity}
  onkeydown={signalUserActivity}
  onclick={signalUserActivity}
/>

<svelte:head>
  <title>Memory Vault</title>
  <meta name="description" content="A private encrypted personal vault." />
</svelte:head>

<Toaster />

{#if data.user}
  <AppShell user={data.user}>
    <VaultUnlockGate user={data.user}>
      {@render children()}
    </VaultUnlockGate>
  </AppShell>
{:else}
  {@render children()}
{/if}
