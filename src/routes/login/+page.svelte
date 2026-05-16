<script lang="ts">
  import { deriveKEK, encryptDEK, generateDEK } from '$lib/crypto';
  import { lockVault, sessionDEK, unlockVault } from '$lib/stores/cryptoKey';
  import { goto } from '$app/navigation';
  import type { PageProps } from './$types';

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
    loading = true;
    try {
      await unlockVault(password, data.kekSalt, data.encryptedDEK, data.dekIV);
      await postJson('/api/auth/login', { email: data.adminEmail, password });
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

<main class="grid min-h-screen place-items-center px-4 py-10">
  <section class="w-full max-w-md rounded-2xl p-8 shadow-xl" style="background: var(--surface)">
    <div class="mb-6">
      <p class="mb-2 text-sm font-medium" style="color: var(--accent)">{data.hasAdmin ? 'Private vault' : 'First setup'}</p>
      <h1 class="text-2xl font-bold tracking-tight">{data.hasAdmin ? 'Unlock Memory Vault' : 'Create the admin vault'}</h1>
      <p class="mt-2 text-sm leading-6" style="color: var(--muted)">
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
          <input class="focus-ring mt-1 w-full rounded-lg border px-3 py-2" style="border-color: var(--border)" bind:value={name} required />
        </label>
      {/if}

      {#if data.hasAdmin}
        <label class="block text-sm font-medium">
          Email
          <input
            class="focus-ring mt-1 w-full rounded-lg border px-3 py-2"
            style="border-color: var(--border)"
            type="email"
            value={data.adminEmail}
            readonly
            required
          />
        </label>
      {:else}
        <label class="block text-sm font-medium">
          Email
          <input
            class="focus-ring mt-1 w-full rounded-lg border px-3 py-2"
            style="border-color: var(--border)"
            type="email"
            bind:value={email}
            required
          />
        </label>
      {/if}

      <label class="block text-sm font-medium">
        Password
        <input
          class="focus-ring mt-1 w-full rounded-lg border px-3 py-2"
          style="border-color: var(--border)"
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
            class="focus-ring mt-1 w-full rounded-lg border px-3 py-2"
            style="border-color: var(--border)"
            type="password"
            bind:value={confirmPassword}
            autocomplete="new-password"
            required
          />
        </label>
      {/if}

      {#if message}
        <p class="rounded-lg border px-3 py-2 text-sm" style="border-color: var(--danger); color: var(--danger)">{message}</p>
      {/if}

      <button
        class="focus-ring w-full rounded-lg px-4 py-2 text-sm font-medium text-white"
        style="background: var(--accent)"
        type="submit"
        disabled={loading}
      >
        {loading ? 'Working...' : data.hasAdmin ? 'Unlock' : 'Create vault'}
      </button>
    </form>
  </section>
</main>
