<script lang="ts">
  import { loadRememberedDEK, saveRememberedDEK } from '$lib/client/rememberedDevice';
  import { sessionDEK, unlockVault } from '$lib/stores/cryptoKey';
  import type { SafeUser } from '$lib/types';
  import type { Snippet } from 'svelte';
  import { onMount } from 'svelte';
  import { KeyRound, Shield } from '@lucide/svelte';

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
  <section class="vault-unlock">
    <div class="vault-unlock__card">
      <div class="vault-unlock__icon">
        <KeyRound size={22} />
      </div>
      <div>
        <p class="vault-unlock__eyebrow">Signed in as {user.email}</p>
        <h1>Unlock vault</h1>
        <p class="vault-unlock__copy">
          Your account session is active. Enter the separate vault passphrase to decrypt this browser tab.
        </p>
      </div>

      {#if restoring}
        <p class="vault-unlock__copy">Checking this device...</p>
      {:else}
        <form
          class="vault-unlock__form"
          onsubmit={(event) => {
            event.preventDefault();
            unlock();
          }}
        >
          <label class="block text-sm font-medium">
            Vault passphrase
            <input
              class="focus-ring vault-input mt-1.5"
              type="password"
              bind:value={vaultPassphrase}
              autocomplete="current-password"
              required
            />
          </label>

          <label class="vault-unlock__remember">
            <input type="checkbox" bind:checked={rememberThisDevice} />
            <span>
              <strong>Remember this device</strong>
              <small>Unlock automatically after reloads on this browser.</small>
            </span>
          </label>

          {#if message}
            <p class="vault-unlock__message">{message}</p>
          {/if}

          <button class="focus-ring vault-btn-primary w-full" type="submit" disabled={loading}>
            <Shield size={16} />
            {loading ? 'Unlocking...' : 'Unlock vault'}
          </button>
        </form>
      {/if}
    </div>
  </section>
{/if}

<style>
  .vault-unlock {
    display: grid;
    min-height: calc(100vh - 4rem);
    place-items: center;
    padding: 2rem 0;
  }

  .vault-unlock__card {
    width: min(100%, 28rem);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 1.5rem;
    background: var(--background);
  }

  .vault-unlock__icon {
    display: grid;
    width: 3rem;
    height: 3rem;
    place-items: center;
    border: 1px solid var(--border);
    border-radius: 8px;
    color: var(--foreground);
    background: var(--surface);
  }

  .vault-unlock__eyebrow {
    margin-top: 1rem;
    color: var(--muted);
    font-size: 0.75rem;
    font-weight: 600;
    line-height: 1rem;
    overflow-wrap: anywhere;
  }

  h1 {
    margin-top: 0.25rem;
    color: var(--foreground);
    font-size: 1.625rem;
    font-weight: 700;
    line-height: 1.15;
  }

  .vault-unlock__copy {
    margin-top: 0.5rem;
    color: var(--muted);
    font-size: 0.875rem;
    line-height: 1.55;
  }

  .vault-unlock__form {
    display: grid;
    gap: 1rem;
    margin-top: 1.5rem;
  }

  .vault-unlock__remember {
    display: flex;
    gap: 0.75rem;
    align-items: flex-start;
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 0.875rem;
    color: var(--foreground);
    background: var(--surface);
    font-size: 0.875rem;
    line-height: 1.4;
  }

  .vault-unlock__remember input {
    margin-top: 0.125rem;
  }

  .vault-unlock__remember span {
    display: grid;
    gap: 0.125rem;
  }

  .vault-unlock__remember small {
    color: var(--muted);
    font-size: 0.75rem;
    line-height: 1.25rem;
  }

  .vault-unlock__message {
    border-radius: 8px;
    padding: 0.625rem 0.75rem;
    color: var(--danger);
    background: rgba(220, 38, 38, 0.06);
    font-size: 0.875rem;
    font-weight: 500;
    line-height: 1.25rem;
  }
</style>
