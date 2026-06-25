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

  let email = $state('');
  let name = $state('');
  let accountPassword = $state('');
  let confirmAccountPassword = $state('');
  let vaultPassphrase = $state('');
  let confirmVaultPassphrase = $state('');
  let rememberThisDevice = $state(false);
  let loading = $state(false);
  let message = $state('');

  function randomBase64(bytes: number) {
    const values = crypto.getRandomValues(new Uint8Array(bytes));
    return btoa(String.fromCharCode(...values));
  }

  async function postJson(url: string, body: unknown) {
    const response = await fetch(url, {
      method: 'POST',
      headers: { 'content-type': 'application/json' },
      body: JSON.stringify(body)
    });

    if (!response.ok) {
      throw new Error(await response.text());
    }
  }

  async function setup() {
    message = '';
    if (accountPassword.length < 12) {
      message = 'Use at least 12 characters for the account password.';
      return;
    }
    if (accountPassword !== confirmAccountPassword) {
      message = 'Account passwords do not match.';
      return;
    }
    if (vaultPassphrase.length < 12) {
      message = 'Use at least 12 characters for the vault passphrase.';
      return;
    }
    if (vaultPassphrase !== confirmVaultPassphrase) {
      message = 'Vault passphrases do not match.';
      return;
    }
    if (accountPassword === vaultPassphrase) {
      message = 'Use a different vault passphrase than your account password.';
      return;
    }

    loading = true;
    try {
      const kekSalt = randomBase64(16);
      const kek = await deriveKEK(vaultPassphrase, kekSalt);
      const exportableDEK = await generateDEK();
      const { encryptedDEK, dekIV } = await encryptDEK(kek, exportableDEK);
      const dek = await makeDEKNonExtractable(exportableDEK);

      await postJson('/api/auth/setup', {
        email,
        name,
        password: accountPassword,
        kekSalt,
        encryptedDEK,
        dekIV
      });

      const normalizedEmail = email.trim().toLowerCase();
      sessionDEK.set(dek);
      if (rememberThisDevice) {
        await saveRememberedDEK(normalizedEmail, dek).catch(() => undefined);
      }
      await goto('/');
    } catch (error) {
      lockVault();
      message = error instanceof Error ? error.message : 'Setup failed.';
    } finally {
      loading = false;
    }
  }

  async function login() {
    message = '';
    if (!email.trim()) {
      message = 'Enter the admin email address.';
      return;
    }

    loading = true;
    try {
      await postJson('/api/auth/login', { email, password: accountPassword });
      await goto('/');
    } catch {
      lockVault();
      message = 'Invalid account credentials.';
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
      <p class="text-xs font-semibold tracking-widest uppercase">{data.hasAdmin ? 'Private vault' : 'First setup'}</p>
      <Card.Title class="text-2xl">{data.hasAdmin ? 'Sign in to Memory Vault' : 'Create the admin vault'}</Card.Title>
      <Card.Description>
        {data.hasAdmin
          ? 'Sign in to your account. The vault passphrase is requested only after the account session is active.'
          : 'This creates the admin account and a separate client-side vault passphrase.'}
      </Card.Description>
    </Card.Header>

    <Card.Content>
      <form
        class="grid gap-4"
        onsubmit={(event) => {
          event.preventDefault();
          data.hasAdmin ? login() : setup();
        }}
      >
        {#if !data.hasAdmin}
          <div class="grid gap-2">
            <Label for="name">Name</Label>
            <Input id="name" bind:value={name} required />
          </div>
        {/if}

        <div class="grid gap-2">
          <Label for="email">Email</Label>
          <Input id="email" type="email" bind:value={email} autocomplete="username" required />
        </div>

        <div class="grid gap-2">
          <Label for="account-password">Account password</Label>
          <Input
            id="account-password"
            type="password"
            bind:value={accountPassword}
            autocomplete={data.hasAdmin ? 'current-password' : 'new-password'}
            required
          />
        </div>

        {#if !data.hasAdmin}
          <div class="grid gap-2">
            <Label for="confirm-account-password">Confirm account password</Label>
            <Input
              id="confirm-account-password"
              type="password"
              bind:value={confirmAccountPassword}
              autocomplete="new-password"
              required
            />
          </div>

          <div class="bg-muted/40 grid gap-4 rounded-lg border p-4">
            <div class="grid gap-1">
              <p class="text-sm font-semibold">Vault passphrase</p>
              <p class="text-muted-foreground text-xs leading-relaxed">
                This passphrase never gets sent to the server. It only decrypts the vault key in this browser.
              </p>
            </div>

            <div class="grid gap-2">
              <Label for="vault-passphrase">Vault passphrase</Label>
              <Input
                id="vault-passphrase"
                type="password"
                bind:value={vaultPassphrase}
                autocomplete="new-password"
                required
              />
            </div>

            <div class="grid gap-2">
              <Label for="confirm-vault-passphrase">Confirm vault passphrase</Label>
              <Input
                id="confirm-vault-passphrase"
                type="password"
                bind:value={confirmVaultPassphrase}
                autocomplete="new-password"
                required
              />
            </div>

            <Label for="remember-device" class="flex items-start gap-3 font-normal">
              <Checkbox id="remember-device" bind:checked={rememberThisDevice} class="mt-0.5" />
              <span class="grid gap-0.5">
                <span class="font-medium">Remember this device</span>
                <span class="text-muted-foreground text-xs">Unlock automatically after reloads on this browser.</span>
              </span>
            </Label>
          </div>
        {/if}

        {#if message}
          <p class="text-destructive text-sm font-medium">{message}</p>
        {/if}

        <Button type="submit" disabled={loading} class="w-full">
          {#if loading}
            <Spinner class="size-4" /> Working…
          {:else}
            <Shield class="size-4" />
            {data.hasAdmin ? 'Sign in' : 'Create vault'}
          {/if}
        </Button>
      </form>
    </Card.Content>
  </Card.Root>
</main>
