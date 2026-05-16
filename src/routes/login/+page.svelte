<script lang="ts">
  import { saveRememberedDEK } from '$lib/client/rememberedDevice';
  import { deriveKEK, encryptDEK, generateDEK, makeDEKNonExtractable } from '$lib/crypto';
  import { lockVault, sessionDEK } from '$lib/stores/cryptoKey';
  import { goto } from '$app/navigation';
  import type { PageProps } from './$types';
  import { Shield } from '@lucide/svelte';

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

<main class="grid min-h-screen place-items-center px-4 py-10" style="background: var(--background)">
  <section class="w-full max-w-md vault-card p-8">
    <div class="mb-8 text-center">
      <img src="/memory-vault.svg" alt="" class="mx-auto mb-4 h-12 w-12" />
      <p class="text-xs font-semibold uppercase tracking-widest" style="color: var(--foreground)">{data.hasAdmin ? 'Private vault' : 'First setup'}</p>
      <h1 class="mt-2 text-2xl font-bold tracking-tight" style="color: var(--foreground)">{data.hasAdmin ? 'Sign in to Memory Vault' : 'Create the admin vault'}</h1>
      <p class="mt-2 text-sm leading-relaxed" style="color: var(--muted)">
        {data.hasAdmin
          ? 'Sign in to your account. The vault passphrase is requested only after the account session is active.'
          : 'This creates the admin account and a separate client-side vault passphrase.'}
      </p>
    </div>

    <form
      class="space-y-4"
      onsubmit={(event) => {
        event.preventDefault();
        data.hasAdmin ? login() : setup();
      }}
    >
      {#if !data.hasAdmin}
        <label class="block text-sm font-medium">
          Name
          <input class="focus-ring vault-input mt-1.5" bind:value={name} required />
        </label>
      {/if}

      <label class="block text-sm font-medium">
        Email
        <input class="focus-ring vault-input mt-1.5" type="email" bind:value={email} autocomplete="username" required />
      </label>

      <label class="block text-sm font-medium">
        Account password
        <input
          class="focus-ring vault-input mt-1.5"
          type="password"
          bind:value={accountPassword}
          autocomplete={data.hasAdmin ? 'current-password' : 'new-password'}
          required
        />
      </label>

      {#if !data.hasAdmin}
        <label class="block text-sm font-medium">
          Confirm account password
          <input
            class="focus-ring vault-input mt-1.5"
            type="password"
            bind:value={confirmAccountPassword}
            autocomplete="new-password"
            required
          />
        </label>

        <div class="rounded-lg border p-4" style="border-color: var(--border); background: var(--surface)">
          <p class="text-sm font-semibold" style="color: var(--foreground)">Vault passphrase</p>
          <p class="mt-1 text-xs leading-relaxed" style="color: var(--muted)">
            This passphrase never gets sent to the server. It only decrypts the vault key in this browser.
          </p>

          <label class="mt-4 block text-sm font-medium">
            Vault passphrase
            <input
              class="focus-ring vault-input mt-1.5"
              type="password"
              bind:value={vaultPassphrase}
              autocomplete="new-password"
              required
            />
          </label>

          <label class="mt-4 block text-sm font-medium">
            Confirm vault passphrase
            <input
              class="focus-ring vault-input mt-1.5"
              type="password"
              bind:value={confirmVaultPassphrase}
              autocomplete="new-password"
              required
            />
          </label>

          <label class="mt-4 flex items-start gap-3 text-sm">
            <input class="mt-1" type="checkbox" bind:checked={rememberThisDevice} />
            <span>
              <span class="block font-semibold" style="color: var(--foreground)">Remember this device</span>
              <span class="block text-xs leading-relaxed" style="color: var(--muted)">
                Unlock automatically after reloads on this browser.
              </span>
            </span>
          </label>
        </div>
      {/if}

      {#if message}
        <p class="rounded-lg px-3 py-2 text-sm font-medium" style="background: rgba(220,38,38,0.06); color: var(--danger)">{message}</p>
      {/if}

      <button class="focus-ring vault-btn-primary w-full mt-2" type="submit" disabled={loading}>
        <Shield size={16} />
        {loading ? 'Working...' : data.hasAdmin ? 'Sign in' : 'Create vault'}
      </button>
    </form>
  </section>
</main>
