<script lang="ts">
  import EmptyState from '$lib/components/EmptyState.svelte';
  import PageHeader from '$lib/components/PageHeader.svelte';
  import VaultNotice from '$lib/components/VaultNotice.svelte';
  import {
    createEncryptedRecord,
    decryptRecords,
    deleteEncryptedRecord,
    fetchEncryptedRecords,
    updateEncryptedRecord
  } from '$lib/client/records';
  import { sessionDEK } from '$lib/stores/cryptoKey';
  import type { DayCounterPayload, EncryptedRecord } from '$lib/types';
  import { goto } from '$app/navigation';
  import { get } from 'svelte/store';
  import { onMount } from 'svelte';
  import { RotateCcw, Trash2 } from '@lucide/svelte';

  type CounterItem = {
    record: EncryptedRecord;
    payload: DayCounterPayload;
  };

  const today = () => new Date().toISOString().slice(0, 10);
  const emptyCounter = (): DayCounterPayload => ({ name: '', initiated: today(), maxDays: null });

  let dek: CryptoKey | null = null;
  let locked = $state(false);
  let loading = $state(true);
  let counters: CounterItem[] = $state([]);
  let form = $state(emptyCounter());

  function elapsedDays(date: string) {
    const start = new Date(`${date}T00:00:00`);
    const now = new Date();
    return Math.max(0, Math.floor((now.getTime() - start.getTime()) / 86_400_000));
  }

  async function loadCounters() {
    if (!dek) return;
    loading = true;
    const records = await fetchEncryptedRecords('day_counter');
    counters = (await decryptRecords<DayCounterPayload>(records, dek)).sort(
      (a, b) => elapsedDays(b.payload.initiated) - elapsedDays(a.payload.initiated)
    );
    loading = false;
  }

  async function saveCounter() {
    if (!dek || !form.name.trim()) return;
    await createEncryptedRecord('day_counter', { ...form, name: form.name.trim() }, dek);
    form = emptyCounter();
    await loadCounters();
  }

  async function resetCounter(item: CounterItem) {
    if (!dek) return;
    await updateEncryptedRecord(item.record.id, 'day_counter', { ...item.payload, initiated: today() }, dek);
    await loadCounters();
  }

  async function removeCounter(item: CounterItem) {
    if (!confirm('Delete this counter?')) return;
    await deleteEncryptedRecord(item.record.id);
    await loadCounters();
  }

  onMount(async () => {
    dek = get(sessionDEK);
    if (!dek) {
      locked = true;
      loading = false;
      await goto('/login');
      return;
    }
    await loadCounters();
  });
</script>

<PageHeader title="Milestones" description="Encrypted trackers for elapsed days since an event." />

{#if locked}
  <VaultNotice />
{:else}
  <div class="grid gap-6 lg:grid-cols-[320px_minmax(0,1fr)]">
    <form
      class="vault-card p-5 h-fit"
      onsubmit={(event) => {
        event.preventDefault();
        saveCounter();
      }}
    >
      <h2 class="mb-4 text-sm font-semibold" style="color: var(--foreground)">New milestone</h2>
      <label class="block text-sm font-medium">
        Name
        <input class="focus-ring vault-input mt-1.5" bind:value={form.name} required />
      </label>
      <label class="mt-3 block text-sm font-medium">
        Initiated
        <input class="focus-ring vault-input mt-1.5" type="date" bind:value={form.initiated} required />
      </label>
      <label class="mt-3 block text-sm font-medium">
        Max days
        <input
          class="focus-ring vault-input mt-1.5"
          type="number"
          min="0"
          bind:value={form.maxDays}
          placeholder="optional"
        />
      </label>
      <button class="focus-ring vault-btn-primary mt-4" type="submit">Create</button>
    </form>

    <section>
      {#if loading}
        <p class="text-sm" style="color: var(--muted)">Decrypting milestones...</p>
      {:else if counters.length === 0}
        <EmptyState title="No milestones yet" description="Create a milestone to track elapsed time." />
      {:else}
        <div class="grid gap-4 sm:grid-cols-2">
          {#each counters as item (item.record.id)}
            <article class="vault-card p-5">
              <div class="flex items-start justify-between gap-3">
                <div>
                  <h2 class="text-sm font-semibold" style="color: var(--foreground)">{item.payload.name}</h2>
                  <p class="mt-1 text-xs" style="color: var(--muted)">Since {item.payload.initiated}</p>
                </div>
                <div class="flex gap-2">
                  <button class="focus-ring vault-btn-ghost" type="button" onclick={() => resetCounter(item)}>
                    <RotateCcw size={15} />
                  </button>
                  <button class="focus-ring vault-btn-danger" type="button" onclick={() => removeCounter(item)}>
                    <Trash2 size={15} />
                  </button>
                </div>
              </div>
              <p class="mt-6 text-4xl font-bold tracking-tight" style="color: var(--foreground)">{elapsedDays(item.payload.initiated)}</p>
              <p class="mt-1 text-sm font-medium" style="color: var(--muted)">days elapsed</p>
              {#if item.payload.maxDays !== null}
                <div class="mt-4 h-1.5 overflow-hidden" style="background: var(--border)">
                  <div
                    class="h-full"
                    style={`background: var(--foreground); width: ${Math.min(100, (elapsedDays(item.payload.initiated) / item.payload.maxDays) * 100)}%`}
                  ></div>
                </div>
              {/if}
            </article>
          {/each}
        </div>
      {/if}
    </section>
  </div>
{/if}
