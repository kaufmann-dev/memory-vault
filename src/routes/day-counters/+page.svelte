<script lang="ts">
  import EmptyState from '$lib/components/EmptyState.svelte';
  import LoadingState from '$lib/components/LoadingState.svelte';
  import EntryModal from '$lib/components/EntryModal.svelte';
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
  import { get } from 'svelte/store';
  import { onMount } from 'svelte';
  import { Pencil, Plus, RotateCcw, Trash2 } from '@lucide/svelte';
  import * as Card from '$lib/components/ui/card/index.js';
  import { Button } from '$lib/components/ui/button/index.js';
  import { Input } from '$lib/components/ui/input/index.js';
  import { Label } from '$lib/components/ui/label/index.js';
  import { Progress } from '$lib/components/ui/progress/index.js';

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
  let editingCounterId: string | null = $state(null);
  let formOpen = $state(false);

  function elapsedDays(date: string) {
    const start = new Date(`${date}T00:00:00`);
    const now = new Date();
    return Math.max(0, Math.floor((now.getTime() - start.getTime()) / 86_400_000));
  }

  function sortCounters(items: CounterItem[]) {
    return [...items].sort((a, b) => elapsedDays(b.payload.initiated) - elapsedDays(a.payload.initiated));
  }

  async function loadCounters() {
    if (!dek) return;
    loading = true;
    const records = await fetchEncryptedRecords('day_counter');
    counters = sortCounters(await decryptRecords<DayCounterPayload>(records, dek));
    loading = false;
  }

  function openCreateCounter() {
    editingCounterId = null;
    form = emptyCounter();
    formOpen = true;
  }

  function openEditCounter(item: CounterItem) {
    editingCounterId = item.record.id;
    form = { ...item.payload };
    formOpen = true;
  }

  function closeCounterForm() {
    editingCounterId = null;
    form = emptyCounter();
    formOpen = false;
  }

  async function saveCounter() {
    if (!dek || !form.name.trim()) return;
    const payload = { ...form, name: form.name.trim() };

    if (editingCounterId) {
      const id = editingCounterId;
      counters = sortCounters(counters.map((c) => (c.record.id === id ? { ...c, payload } : c)));
      await updateEncryptedRecord(id, 'day_counter', payload, dek);
    } else {
      const record = await createEncryptedRecord('day_counter', payload, dek);
      counters = sortCounters([...counters, { record, payload }]);
    }

    closeCounterForm();
  }

  async function resetCounter(item: CounterItem) {
    if (!dek) return;
    const payload = { ...item.payload, initiated: today() };
    counters = sortCounters(counters.map((c) => (c.record.id === item.record.id ? { ...c, payload } : c)));
    await updateEncryptedRecord(item.record.id, 'day_counter', payload, dek);
  }

  async function removeCounter(item: CounterItem) {
    if (!confirm('Delete this counter?')) return;
    counters = counters.filter((c) => c.record.id !== item.record.id);
    await deleteEncryptedRecord(item.record.id);
  }

  onMount(async () => {
    dek = get(sessionDEK);
    if (!dek) {
      locked = true;
      loading = false;
      return;
    }
    await loadCounters();
  });
</script>

<PageHeader title="Milestones" description="Encrypted trackers for elapsed days since an event.">
  <Button onclick={openCreateCounter}>
    <Plus class="size-4" />
    New
  </Button>
</PageHeader>

{#if locked}
  <VaultNotice />
{:else}
  <section>
    {#if loading}
      <LoadingState message="Decrypting milestones…" />
    {:else if counters.length === 0}
      <EmptyState title="No milestones yet" description="Create a milestone to track elapsed time." />
    {:else}
      <div class="grid gap-4 sm:grid-cols-2">
        {#each counters as item (item.record.id)}
          <Card.Root>
            <Card.Header>
              <Card.Title>{item.payload.name}</Card.Title>
              <Card.Description>Since {item.payload.initiated}</Card.Description>
              <Card.Action class="flex gap-1">
                <Button variant="ghost" size="icon" onclick={() => openEditCounter(item)} aria-label="Edit milestone">
                  <Pencil class="size-4" />
                </Button>
                <Button variant="ghost" size="icon" onclick={() => resetCounter(item)} aria-label="Reset milestone">
                  <RotateCcw class="size-4" />
                </Button>
                <Button
                  variant="ghost"
                  size="icon"
                  class="text-destructive hover:text-destructive"
                  onclick={() => removeCounter(item)}
                  aria-label="Delete milestone"
                >
                  <Trash2 class="size-4" />
                </Button>
              </Card.Action>
            </Card.Header>
            <Card.Content>
              <p class="text-4xl font-bold tracking-tight">{elapsedDays(item.payload.initiated)}</p>
              <p class="text-muted-foreground mt-1 text-sm font-medium">days elapsed</p>
              {#if item.payload.maxDays !== null}
                <Progress
                  value={Math.min(100, (elapsedDays(item.payload.initiated) / item.payload.maxDays) * 100)}
                  class="mt-4"
                />
              {/if}
            </Card.Content>
          </Card.Root>
        {/each}
      </div>
    {/if}
  </section>

  <EntryModal
    open={formOpen}
    title={editingCounterId ? 'Edit milestone' : 'New milestone'}
    description={editingCounterId ? 'Update this encrypted milestone.' : 'Choose the date you want to measure from. Everything stays encrypted.'}
    onClose={closeCounterForm}
  >
    <form
      class="grid gap-4"
      onsubmit={(event) => {
        event.preventDefault();
        saveCounter();
      }}
    >
      <div class="grid gap-2">
        <Label for="counter-name">Name</Label>
        <Input id="counter-name" bind:value={form.name} required />
      </div>
      <div class="grid gap-2">
        <Label for="counter-initiated">Initiated</Label>
        <Input id="counter-initiated" type="date" bind:value={form.initiated} required />
      </div>
      <div class="grid gap-2">
        <Label for="counter-max">Max days</Label>
        <Input id="counter-max" type="number" min="0" bind:value={form.maxDays} placeholder="optional" />
      </div>
      <div class="flex justify-end">
        <Button type="submit">{editingCounterId ? 'Save' : 'Create'}</Button>
      </div>
    </form>
  </EntryModal>
{/if}
