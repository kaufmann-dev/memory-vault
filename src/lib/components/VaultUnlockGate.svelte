<script lang="ts">
  import { loadRememberedDEK, saveRememberedDEK } from '$lib/client/rememberedDevice';
  import { sessionDEK, unlockVault } from '$lib/stores/cryptoKey';
  import type { SafeUser } from '$lib/types';
  import type { Snippet } from 'svelte';
  import { onMount } from 'svelte';
  import { KeyRound, Shield } from '@lucide/svelte';
  import * as Card from '$lib/components/ui/card/index.js';
  import { Button } from '$lib/components/ui/button/index.js';
  import { Input } from '$lib/components/ui/input/index.js';
  import { Label } from '$lib/components/ui/label/index.js';
  import { Checkbox } from '$lib/components/ui/checkbox/index.js';
  import { Spinner } from '$lib/components/ui/spinner/index.js';

  let { user, children }: { user: SafeUser; children: Snippet } = $props();

  let vaultPassphrase = $state('');
  let rememberThisDevice = $state(false);
  let loading = $state(false);
  let restoring = $state(true);
  let message = $state('');

  async function unlock() {
    message = '';
    if (!vaultPassphrase) {
      message = 'Enter the vault passphrase.';
      return;
    }

    loading = true;
    try {
      const dek = await unlockVault(vaultPassphrase, user.kekSalt, user.encryptedDEK, user.dekIV);
      if (rememberThisDevice) {
        await saveRememberedDEK(user.email, dek).catch(() => {
          message = 'Vault unlocked, but this browser could not remember it.';
        });
      }
      vaultPassphrase = '';
    } catch {
      message = 'Vault passphrase could not unlock this vault.';
    } finally {
      loading = false;
    }
  }

  onMount(() => {
    (async () => {
      try {
        const rememberedDEK = await loadRememberedDEK(user.email);
        if (rememberedDEK) {
          sessionDEK.set(rememberedDEK);
        }
      } catch {
        message = 'Remembered device unlock is unavailable. Enter the vault passphrase.';
      } finally {
        restoring = false;
      }
    })();
  });
</script>

{#if $sessionDEK}
  {@render children()}
{:else}
  <div class="grid min-h-[calc(100vh-7rem)] place-items-center py-8">
    <Card.Root class="w-full max-w-md">
      <Card.Header>
        <div class="bg-muted text-foreground mb-2 flex size-11 items-center justify-center rounded-lg">
          <KeyRound class="size-5" />
        </div>
        <Card.Description>Signed in as {user.email}</Card.Description>
        <Card.Title class="text-2xl">Unlock vault</Card.Title>
        <Card.Description>
          Your account session is active. Enter the separate vault passphrase to decrypt this browser tab.
        </Card.Description>
      </Card.Header>
      <Card.Content>
        {#if restoring}
          <p class="text-muted-foreground flex items-center gap-2 text-sm">
            <Spinner class="size-4" /> Checking this device…
          </p>
        {:else}
          <form
            class="grid gap-4"
            onsubmit={(event) => {
              event.preventDefault();
              unlock();
            }}
          >
            <div class="grid gap-2">
              <Label for="vault-passphrase">Vault passphrase</Label>
              <Input
                id="vault-passphrase"
                type="password"
                bind:value={vaultPassphrase}
                autocomplete="current-password"
                required
              />
            </div>

            <Label
              for="remember-device"
              class="bg-muted/40 flex items-start gap-3 rounded-lg border p-3.5 font-normal"
            >
              <Checkbox id="remember-device" bind:checked={rememberThisDevice} class="mt-0.5" />
              <span class="grid gap-0.5">
                <span class="font-medium">Remember this device</span>
                <span class="text-muted-foreground text-xs">Unlock automatically after reloads on this browser.</span>
              </span>
            </Label>

            {#if message}
              <p class="text-destructive text-sm font-medium">{message}</p>
            {/if}

            <Button type="submit" disabled={loading} class="w-full">
              {#if loading}
                <Spinner class="size-4" /> Unlocking…
              {:else}
                <Shield class="size-4" /> Unlock vault
              {/if}
            </Button>
          </form>
        {/if}
      </Card.Content>
    </Card.Root>
  </div>
{/if}
