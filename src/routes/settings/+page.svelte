<script lang="ts">
  import PageHeader from '$lib/components/PageHeader.svelte';
  import VaultNotice from '$lib/components/VaultNotice.svelte';
  import { decryptDEK, deriveKEK, encryptDEK } from '$lib/crypto';
  import { sessionDEK } from '$lib/stores/cryptoKey';
  import { goto } from '$app/navigation';
  import { get } from 'svelte/store';
  import { onMount } from 'svelte';
  import type { PageProps } from './$types';
  import { Save } from '@lucide/svelte';

  let { data }: PageProps = $props();

  let dek = $state<CryptoKey | null>(null);
  let locked = $state(false);
  let currentPassword = $state('');
  let newPassword = $state('');
  let confirmPassword = $state('');
  let message = $state('');
  let saving = $state(false);

  function randomBase64(bytes: number) {
    const values = crypto.getRandomValues(new Uint8Array(bytes));
    return btoa(String.fromCharCode(...values));
  }

  async function changePassword() {
    message = '';
    if (!dek || !data.keyMaterial) return;
    if (newPassword.length < 12) {
      message = 'Use at least 12 characters.';
      return;
    }
    if (newPassword !== confirmPassword) {
      message = 'Passwords do not match.';
      return;
    }

    saving = true;
    try {
      const oldKEK = await deriveKEK(currentPassword, data.keyMaterial.kekSalt);
      await decryptDEK(oldKEK, data.keyMaterial.encryptedDEK, data.keyMaterial.dekIV);

      const newKekSalt = randomBase64(16);
      const newKEK = await deriveKEK(newPassword, newKekSalt);
      const { encryptedDEK: newEncryptedDEK, dekIV: newDekIV } = await encryptDEK(newKEK, dek);

      const response = await fetch('/api/auth/password', {
        method: 'POST',
        headers: { 'content-type': 'application/json' },
        body: JSON.stringify({
          currentPassword,
          newPassword,
          newKekSalt,
          newEncryptedDEK,
          newDekIV
        })
      });

      if (!response.ok) throw new Error(await response.text());
      currentPassword = '';
      newPassword = '';
      confirmPassword = '';
      message = 'Password changed.';
    } catch {
      message = 'Password change failed.';
    } finally {
      saving = false;
    }
  }

  onMount(async () => {
    dek = get(sessionDEK);
    if (!dek) {
      locked = true;
      await goto('/login');
    }
  });
</script>

<PageHeader title="Settings" description="Account security and vault controls." />

{#if locked}
  <VaultNotice />
{:else}
  <section class="max-w-xl vault-card p-6">
    <h2 class="text-base font-semibold" style="color: var(--foreground)">Change password</h2>
    <p class="mt-2 text-sm leading-relaxed" style="color: var(--muted)">
      This re-encrypts the data key in the browser. Existing encrypted records do not need to be rewritten.
    </p>
    <form
      class="mt-6 space-y-4"
      onsubmit={(event) => {
        event.preventDefault();
        changePassword();
      }}
    >
      <label class="block text-sm font-medium">
        Current password
        <input class="focus-ring vault-input mt-1.5" type="password" bind:value={currentPassword} required />
      </label>
      <label class="block text-sm font-medium">
        New password
        <input class="focus-ring vault-input mt-1.5" type="password" bind:value={newPassword} required />
      </label>
      <label class="block text-sm font-medium">
        Confirm new password
        <input class="focus-ring vault-input mt-1.5" type="password" bind:value={confirmPassword} required />
      </label>
      {#if message}
        <p class="text-sm font-medium" style="color: {message === 'Password changed.' ? 'var(--success)' : 'var(--danger)'}">{message}</p>
      {/if}
      <button class="focus-ring vault-btn-primary" type="submit" disabled={saving}>
        <Save size={16} />
        {saving ? 'Saving...' : 'Save password'}
      </button>
    </form>
  </section>
{/if}
