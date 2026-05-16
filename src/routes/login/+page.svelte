<script lang="ts">
  import { deriveKEK, encryptDEK, generateDEK } from '$lib/crypto';
  import { lockVault, sessionDEK, unlockVault } from '$lib/stores/cryptoKey';
  import { goto } from '$app/navigation';
  import type { PageProps } from './$types';
  import { Shield } from '@lucide/svelte';

  let { data }: PageProps = $props();

  let email = $state('');
  let name = $state('');
  let password = $state('');
  let confirmPassword = $state('');
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
    if (password.length < 12) {
      message = 'Use at least 12 characters.';
      return;
    }
    if (password !== confirmPassword) {
      message = 'Passwords do not match.';
      return;
    }

    loading = true;
    try {
      const kekSalt = randomBase64(16);
      const kek = await deriveKEK(password, kekSalt);
      const dek = await generateDEK();
      const { encryptedDEK, dekIV } = await encryptDEK(kek, dek);

      await postJson('/api/auth/setup', {
        email,
        name,
        password,
        kekSalt,
        encryptedDEK,
        dekIV
      });

      sessionDEK.set(dek);
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
      await unlockVault(password, data.kekSalt, data.encryptedDEK, data.dekIV);
      await postJson('/api/auth/login', { email, password });
      await goto('/');
    } catch {
      lockVault();
      message = 'Password could not unlock this vault.';
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
      <div class="mx-auto mb-4 grid h-12 w-12 place-items-center text-2xl font-extrabold text-white" style="background: var(--accent)">M</div>
      <p class="text-xs font-semibold uppercase tracking-widest" style="color: var(--accent)">{data.hasAdmin ? 'Private vault' : 'First setup'}</p>
      <h1 class="mt-2 text-2xl font-bold tracking-tight" style="color: var(--foreground)">{data.hasAdmin ? 'Unlock Memory Vault' : 'Create the admin vault'}</h1>
      <p class="mt-2 text-sm leading-relaxed" style="color: var(--muted)">
        {data.hasAdmin
          ? 'Your password unlocks the in-memory encryption key for this browser tab.'
          : 'This creates the only admin account and the client-side data encryption key.'}
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
        Password
        <input
          class="focus-ring vault-input mt-1.5"
          type="password"
          bind:value={password}
          autocomplete={data.hasAdmin ? 'current-password' : 'new-password'}
          required
        />
      </label>

      {#if !data.hasAdmin}
        <label class="block text-sm font-medium">
          Confirm password
          <input
            class="focus-ring vault-input mt-1.5"
            type="password"
            bind:value={confirmPassword}
            autocomplete="new-password"
            required
          />
        </label>
      {/if}

      {#if message}
        <p class="rounded-lg px-3 py-2 text-sm font-medium" style="background: rgba(220,38,38,0.06); color: var(--danger)">{message}</p>
      {/if}

      <button class="focus-ring vault-btn-primary w-full mt-2" type="submit" disabled={loading}>
        <Shield size={16} />
        {loading ? 'Working...' : data.hasAdmin ? 'Unlock' : 'Create vault'}
      </button>
    </form>
  </section>
</main>
