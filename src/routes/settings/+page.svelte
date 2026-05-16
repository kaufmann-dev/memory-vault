<script lang="ts">
  import PageHeader from '$lib/components/PageHeader.svelte';
  import { decryptDEK, deriveKEK, encryptDEK } from '$lib/crypto';
  import { sessionDEK } from '$lib/stores/cryptoKey';
  import { invalidateAll } from '$app/navigation';
  import { get } from 'svelte/store';
  import { onMount } from 'svelte';
  import type { PageProps } from './$types';
  import { KeyRound, Save, Shield } from '@lucide/svelte';

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
  });
</script>

<PageHeader title="Settings" description="Account security and vault controls." />

<div class="settings-grid">
  <section class="vault-card p-6">
    <div class="settings-card__header">
      <div class="settings-card__icon">
        <Shield size={18} />
      </div>
      <div>
        <h2>Account password</h2>
        <p>This password signs in to the server and creates the session cookie.</p>
      </div>
    </div>

    <form
      class="mt-6 space-y-4"
      onsubmit={(event) => {
        event.preventDefault();
        changeAccountPassword();
      }}
    >
      <label class="block text-sm font-medium">
        Current account password
        <input class="focus-ring vault-input mt-1.5" type="password" bind:value={currentAccountPassword} required />
      </label>
      <label class="block text-sm font-medium">
        New account password
        <input class="focus-ring vault-input mt-1.5" type="password" bind:value={newAccountPassword} required />
      </label>
      <label class="block text-sm font-medium">
        Confirm new account password
        <input class="focus-ring vault-input mt-1.5" type="password" bind:value={confirmAccountPassword} required />
      </label>
      {#if accountMessage}
        <p class="text-sm font-medium" style="color: {accountSuccess ? 'var(--success)' : 'var(--danger)'}">{accountMessage}</p>
      {/if}
      <button class="focus-ring vault-btn-primary" type="submit" disabled={savingAccount}>
        <Save size={16} />
        {savingAccount ? 'Saving...' : 'Save account password'}
      </button>
    </form>
  </section>

  <section class="vault-card p-6">
    <div class="settings-card__header">
      <div class="settings-card__icon">
        <KeyRound size={18} />
      </div>
      <div>
        <h2>Vault passphrase</h2>
        <p>This passphrase never gets sent to the server. It re-encrypts only the vault key.</p>
      </div>
    </div>

    <form
      class="mt-6 space-y-4"
      onsubmit={(event) => {
        event.preventDefault();
        changeVaultPassphrase();
      }}
    >
      <label class="block text-sm font-medium">
        Current vault passphrase
        <input class="focus-ring vault-input mt-1.5" type="password" bind:value={currentVaultPassphrase} required />
      </label>
      <label class="block text-sm font-medium">
        New vault passphrase
        <input class="focus-ring vault-input mt-1.5" type="password" bind:value={newVaultPassphrase} required />
      </label>
      <label class="block text-sm font-medium">
        Confirm new vault passphrase
        <input class="focus-ring vault-input mt-1.5" type="password" bind:value={confirmVaultPassphrase} required />
      </label>
      {#if vaultMessage}
        <p class="text-sm font-medium" style="color: {vaultSuccess ? 'var(--success)' : 'var(--danger)'}">{vaultMessage}</p>
      {/if}
      <button class="focus-ring vault-btn-primary" type="submit" disabled={savingVault}>
        <Save size={16} />
        {savingVault ? 'Saving...' : 'Save vault passphrase'}
      </button>
    </form>
  </section>
</div>

<style>
  .settings-grid {
    display: grid;
    gap: 1rem;
    max-width: 64rem;
  }

  .settings-card__header {
    display: flex;
    gap: 0.875rem;
    align-items: flex-start;
  }

  .settings-card__icon {
    display: grid;
    flex: 0 0 auto;
    width: 2.5rem;
    height: 2.5rem;
    place-items: center;
    border: 1px solid var(--border);
    border-radius: 8px;
    color: var(--foreground);
    background: var(--surface);
  }

  h2 {
    color: var(--foreground);
    font-size: 1rem;
    font-weight: 700;
    line-height: 1.4;
  }

  p {
    margin-top: 0.25rem;
    color: var(--muted);
    font-size: 0.875rem;
    line-height: 1.5;
  }

  @media (min-width: 900px) {
    .settings-grid {
      grid-template-columns: repeat(2, minmax(0, 1fr));
      align-items: start;
    }
  }
</style>
