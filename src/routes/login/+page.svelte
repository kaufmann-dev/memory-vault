<script lang="ts">
  import { saveRememberedDEK } from '$lib/client/rememberedDevice';
  import { deriveKEK, encryptDEK, generateDEK, makeDEKNonExtractable } from '$lib/crypto';
  import { lockVault, sessionDEK } from '$lib/stores/cryptoKey';
  import { goto } from '$app/navigation';
  import type { PageProps } from './$types';
  import { Shield } from '@lucide/svelte';
  import * as Card from '$lib/components/ui/card/index.js';
  import { Button } from '$lib/components/ui/button/index.js';
  import { Input } from '$lib/components/ui/input/index.js';
  import { Label } from '$lib/components/ui/label/index.js';
  import { Checkbox } from '$lib/components/ui/checkbox/index.js';
  import { Spinner } from '$lib/components/ui/spinner/index.js';

  let { data }: PageProps = $props();

  let vaultPassphrase = $state('');
  let confirmVaultPassphrase = $state('');
  let rememberThisDevice = $state(false);
  let loading = $state(false);
  let message = $state('');

  function randomBase64(bytes: number) {
    const values = crypto.getRandomValues(new Uint8Array(bytes));
    return btoa(String.fromCharCode(...values));
  }

  async function postJson<T>(url: string, body: unknown): Promise<T> {
    const response = await fetch(url, {
      method: 'POST',
      headers: { 'content-type': 'application/json' },
      body: JSON.stringify(body)
    });

    if (!response.ok) {
      throw new Error(await response.text());
    }

    return response.json() as Promise<T>;
  }

  async function setup() {
    message = '';
    if (vaultPassphrase.length < 12) {
      message = 'Use at least 12 characters for the vault passphrase.';
      return;
    }
    if (vaultPassphrase !== confirmVaultPassphrase) {
      message = 'Vault passphrases do not match.';
      return;
    }

    loading = true;
    try {
      const kekSalt = randomBase64(16);
      const kek = await deriveKEK(vaultPassphrase, kekSalt);
      const exportableDEK = await generateDEK();
      const { encryptedDEK, dekIV } = await encryptDEK(kek, exportableDEK);
      const dek = await makeDEKNonExtractable(exportableDEK);

      const result = await postJson<{ ok: true; email: string }>('/api/auth/setup', {
        kekSalt,
        encryptedDEK,
        dekIV
      });

      sessionDEK.set(dek);
      if (rememberThisDevice) {
        await saveRememberedDEK(result.email, dek).catch(() => undefined);
      }
      await goto('/');
    } catch (error) {
      lockVault();
      message = error instanceof Error ? error.message : 'Setup failed.';
    } finally {
      loading = false;
    }
  }

</script>

<svelte:head>
  <title>Login | Memory Vault</title>
</svelte:head>

<main class="bg-background grid min-h-screen place-items-center px-4 py-10">
  <Card.Root class="w-full max-w-md">
    <Card.Header class="text-center">
      <img src="/memory-vault.svg" alt="" class="mx-auto mb-2 size-12" />
      <p class="text-xs font-semibold tracking-widest uppercase">
        {data.needsVaultSetup ? 'First vault setup' : 'Private vault'}
      </p>
      <Card.Title class="text-2xl">
        {data.needsVaultSetup ? 'Create your vault' : 'Sign in to Memory Vault'}
      </Card.Title>
      <Card.Description>
        {#if data.needsVaultSetup}
          Signed in with your identity provider{data.setupEmail ? ` as ${data.setupEmail}` : ''}. Choose a separate vault
          passphrase that never leaves this browser.
        {:else}
          Continue with your organization’s single sign-on. Your vault passphrase is requested after sign-in.
        {/if}
      </Card.Description>
    </Card.Header>

    <Card.Content>
      {#if data.needsVaultSetup}
        <form
          class="grid gap-4"
          onsubmit={(event) => {
            event.preventDefault();
            setup();
          }}
        >
          <div class="grid gap-2">
            <Label for="vault-passphrase">Vault passphrase</Label>
            <Input
              id="vault-passphrase"
              type="text"
              bind:value={vaultPassphrase}
              class="masked-text-input"
              autocomplete="off"
              autocapitalize="none"
              spellcheck="false"
              required
            />
          </div>

          <div class="grid gap-2">
            <Label for="confirm-vault-passphrase">Confirm vault passphrase</Label>
            <Input
              id="confirm-vault-passphrase"
              type="text"
              bind:value={confirmVaultPassphrase}
              class="masked-text-input"
              autocomplete="off"
              autocapitalize="none"
              spellcheck="false"
              required
            />
          </div>

          <Label for="remember-device" class="bg-muted/40 flex items-start gap-3 rounded-lg border p-3.5 font-normal">
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
              <Spinner class="size-4" /> Creating vault…
            {:else}
              <Shield class="size-4" /> Create vault
            {/if}
          </Button>
        </form>
      {:else}
        <div class="grid gap-4">
          {#if data.oidcError}
            <p class="text-destructive text-sm font-medium">Single sign-on failed. Please try again.</p>
          {/if}
          <Button href="/auth/login" class="w-full">
            <Shield class="size-4" /> Continue with single sign-on
          </Button>
        </div>

        <nav aria-label="Legal" class="text-muted-foreground mt-5 flex justify-center gap-4 text-xs">
          <a
            href="https://legal.kaufmann.dev/imprint?site=vault.kaufmann.dev"
            class="underline-offset-4 hover:underline focus-visible:underline"
          >
            Imprint
          </a>
          <a
            href="https://legal.kaufmann.dev/privacy?site=vault.kaufmann.dev"
            class="underline-offset-4 hover:underline focus-visible:underline"
          >
            Privacy
          </a>
        </nav>
      {/if}
    </Card.Content>
  </Card.Root>
</main>
