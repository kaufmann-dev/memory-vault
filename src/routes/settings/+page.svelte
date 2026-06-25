<script lang="ts">
  import PageHeader from '$lib/components/PageHeader.svelte';
  import {
    forgetRememberedDEK,
    hasRememberedDEK,
    saveRememberedDEK
  } from '$lib/client/rememberedDevice';
  import { decryptDEK, deriveKEK, encryptDEK } from '$lib/crypto';
  import { sessionDEK } from '$lib/stores/cryptoKey';
  import { invalidateAll } from '$app/navigation';
  import { get } from 'svelte/store';
  import { onMount } from 'svelte';
  import type { PageProps } from './$types';
  import { KeyRound, Save, Shield } from '@lucide/svelte';
  import { Button } from '$lib/components/ui/button/index.js';
  import { Input } from '$lib/components/ui/input/index.js';
  import { Label } from '$lib/components/ui/label/index.js';

  let { data }: PageProps = $props();

  type KeyMaterial = NonNullable<PageProps['data']['keyMaterial']>;

  let updatedKeyMaterial = $state<KeyMaterial | null>(null);
  let keyMaterial = $derived(updatedKeyMaterial ?? data.keyMaterial);
  let dek: CryptoKey | null = null;

  let currentAccountPassword = $state('');
  let newAccountPassword = $state('');
  let confirmAccountPassword = $state('');
  let accountMessage = $state('');
  let accountSuccess = $state(false);
  let savingAccount = $state(false);

  let currentVaultPassphrase = $state('');
  let newVaultPassphrase = $state('');
  let confirmVaultPassphrase = $state('');
  let vaultMessage = $state('');
  let vaultSuccess = $state(false);
  let savingVault = $state(false);
  let rememberedDevice = $state(false);
  let savingRememberedDevice = $state(false);
  let deviceMessage = $state('');
  let deviceSuccess = $state(false);

  function randomBase64(bytes: number) {
    const values = crypto.getRandomValues(new Uint8Array(bytes));
    return btoa(String.fromCharCode(...values));
  }

  async function changeAccountPassword() {
    accountMessage = '';
    accountSuccess = false;

    if (newAccountPassword.length < 12) {
      accountMessage = 'Use at least 12 characters.';
      return;
    }
    if (newAccountPassword !== confirmAccountPassword) {
      accountMessage = 'Account passwords do not match.';
      return;
    }

    savingAccount = true;
    try {
      const response = await fetch('/api/auth/password', {
        method: 'POST',
        headers: { 'content-type': 'application/json' },
        body: JSON.stringify({
          currentPassword: currentAccountPassword,
          newPassword: newAccountPassword
        })
      });

      if (!response.ok) throw new Error(await response.text());
      currentAccountPassword = '';
      newAccountPassword = '';
      confirmAccountPassword = '';
      accountSuccess = true;
      accountMessage = 'Account password changed.';
    } catch {
      accountMessage = 'Account password change failed.';
    } finally {
      savingAccount = false;
    }
  }

  async function changeVaultPassphrase() {
    vaultMessage = '';
    vaultSuccess = false;

    if (!dek || !keyMaterial) {
      vaultMessage = 'Unlock the vault before changing its passphrase.';
      return;
    }
    if (newVaultPassphrase.length < 12) {
      vaultMessage = 'Use at least 12 characters.';
      return;
    }
    if (newVaultPassphrase !== confirmVaultPassphrase) {
      vaultMessage = 'Vault passphrases do not match.';
      return;
    }

    savingVault = true;
    try {
      const oldKEK = await deriveKEK(currentVaultPassphrase, keyMaterial.kekSalt);
      await decryptDEK(oldKEK, keyMaterial.encryptedDEK, keyMaterial.dekIV);

      const newKekSalt = randomBase64(16);
      const newKEK = await deriveKEK(newVaultPassphrase, newKekSalt);
      const { encryptedDEK: newEncryptedDEK, dekIV: newDekIV } = await encryptDEK(newKEK, dek);

      const response = await fetch('/api/auth/vault-key', {
        method: 'POST',
        headers: { 'content-type': 'application/json' },
        body: JSON.stringify({
          newKekSalt,
          newEncryptedDEK,
          newDekIV
        })
      });

      if (!response.ok) throw new Error(await response.text());
      updatedKeyMaterial = {
        kekSalt: newKekSalt,
        encryptedDEK: newEncryptedDEK,
        dekIV: newDekIV
      };
      currentVaultPassphrase = '';
      newVaultPassphrase = '';
      confirmVaultPassphrase = '';
      vaultSuccess = true;
      vaultMessage = 'Vault passphrase changed.';
      await invalidateAll();
    } catch {
      vaultMessage = 'Vault passphrase change failed.';
    } finally {
      savingVault = false;
    }
  }

  onMount(() => {
    dek = get(sessionDEK);
    (async () => {
      if (!data.user) return;
      rememberedDevice = await hasRememberedDEK(data.user.email).catch(() => false);
    })();
  });

  async function rememberDevice() {
    deviceMessage = '';
    deviceSuccess = false;

    const activeDEK = dek ?? get(sessionDEK);
    if (!data.user || !activeDEK) {
      deviceMessage = 'Unlock the vault before remembering this device.';
      return;
    }

    savingRememberedDevice = true;
    try {
      await saveRememberedDEK(data.user.email, activeDEK);
      dek = activeDEK;
      rememberedDevice = true;
      deviceSuccess = true;
      deviceMessage = 'This device will unlock after reloads.';
    } catch {
      deviceMessage = 'This browser could not remember the vault key.';
    } finally {
      savingRememberedDevice = false;
    }
  }

  async function forgetDevice() {
    deviceMessage = '';
    deviceSuccess = false;
    if (!data.user) return;

    savingRememberedDevice = true;
    try {
      await forgetRememberedDEK(data.user.email);
      rememberedDevice = false;
      deviceSuccess = true;
      deviceMessage = 'This device has been forgotten.';
    } catch {
      deviceMessage = 'This browser could not forget the vault key.';
    } finally {
      savingRememberedDevice = false;
    }
  }
</script>

<PageHeader title="Settings" description="Account security and vault controls." />

<div class="max-w-3xl">
  <section class="border-border grid gap-6 border-b py-8 first:pt-0 last:border-b-0 last:pb-0">
    <div class="grid gap-3">
      <div class="bg-muted text-foreground flex size-10 items-center justify-center rounded-lg">
        <Shield class="size-4.5" />
      </div>
      <div class="grid gap-2">
        <h2 class="text-xl font-semibold tracking-tight">Account password</h2>
        <p class="text-muted-foreground max-w-2xl text-sm leading-6">
          This password signs in to the server and creates the session cookie.
        </p>
      </div>
    </div>

    <form
      class="grid max-w-xl gap-4"
      onsubmit={(event) => {
        event.preventDefault();
        changeAccountPassword();
      }}
    >
      <div class="grid gap-2">
        <Label for="current-account-password">Current account password</Label>
        <Input id="current-account-password" type="password" bind:value={currentAccountPassword} required />
      </div>
      <div class="grid gap-2">
        <Label for="new-account-password">New account password</Label>
        <Input id="new-account-password" type="password" bind:value={newAccountPassword} required />
      </div>
      <div class="grid gap-2">
        <Label for="confirm-account-password">Confirm new account password</Label>
        <Input id="confirm-account-password" type="password" bind:value={confirmAccountPassword} required />
      </div>
      {#if accountMessage}
        <p class="text-sm font-medium {accountSuccess ? 'text-green-600 dark:text-green-500' : 'text-destructive'}">
          {accountMessage}
        </p>
      {/if}
      <Button type="submit" disabled={savingAccount} class="justify-self-start">
        <Save class="size-4" />
        {savingAccount ? 'Saving…' : 'Save account password'}
      </Button>
    </form>
  </section>

  <section class="border-border grid gap-6 border-b py-8">
    <div class="grid gap-3">
      <div class="bg-muted text-foreground flex size-10 items-center justify-center rounded-lg">
        <KeyRound class="size-4.5" />
      </div>
      <div class="grid gap-2">
        <h2 class="text-xl font-semibold tracking-tight">Vault passphrase</h2>
        <p class="text-muted-foreground max-w-2xl text-sm leading-6">
          This passphrase never gets sent to the server. It re-encrypts only the vault key.
        </p>
      </div>
    </div>

    <form
      class="grid max-w-xl gap-4"
      onsubmit={(event) => {
        event.preventDefault();
        changeVaultPassphrase();
      }}
    >
      <div class="grid gap-2">
        <Label for="current-vault-passphrase">Current vault passphrase</Label>
        <Input id="current-vault-passphrase" type="password" bind:value={currentVaultPassphrase} required />
      </div>
      <div class="grid gap-2">
        <Label for="new-vault-passphrase">New vault passphrase</Label>
        <Input id="new-vault-passphrase" type="password" bind:value={newVaultPassphrase} required />
      </div>
      <div class="grid gap-2">
        <Label for="confirm-vault-passphrase">Confirm new vault passphrase</Label>
        <Input id="confirm-vault-passphrase" type="password" bind:value={confirmVaultPassphrase} required />
      </div>
      {#if vaultMessage}
        <p class="text-sm font-medium {vaultSuccess ? 'text-green-600 dark:text-green-500' : 'text-destructive'}">
          {vaultMessage}
        </p>
      {/if}
      <Button type="submit" disabled={savingVault} class="justify-self-start">
        <Save class="size-4" />
        {savingVault ? 'Saving…' : 'Save vault passphrase'}
      </Button>
    </form>
  </section>

  <section class="grid gap-6 py-8">
    <div class="grid gap-3">
      <div class="bg-muted text-foreground flex size-10 items-center justify-center rounded-lg">
        <Shield class="size-4.5" />
      </div>
      <div class="grid gap-2">
        <h2 class="text-xl font-semibold tracking-tight">Remembered device</h2>
        <p class="text-muted-foreground max-w-2xl text-sm leading-6">
          Store a browser-local vault key so reloads unlock automatically on this device.
        </p>
      </div>
    </div>

    <div class="grid max-w-xl gap-4">
      <p class="text-sm font-medium">Status: {rememberedDevice ? 'Enabled' : 'Disabled'}</p>
      {#if deviceMessage}
        <p class="text-sm font-medium {deviceSuccess ? 'text-green-600 dark:text-green-500' : 'text-destructive'}">
          {deviceMessage}
        </p>
      {/if}
      {#if rememberedDevice}
        <Button variant="outline" disabled={savingRememberedDevice} onclick={forgetDevice} class="justify-self-start">
          {savingRememberedDevice ? 'Working…' : 'Forget this device'}
        </Button>
      {:else}
        <Button disabled={savingRememberedDevice} onclick={rememberDevice} class="justify-self-start">
          <Save class="size-4" />
          {savingRememberedDevice ? 'Working…' : 'Remember this device'}
        </Button>
      {/if}
    </div>
  </section>
</div>
