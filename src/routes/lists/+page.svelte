<script lang="ts">
  import EmptyState from '$lib/components/EmptyState.svelte';
  import PageHeader from '$lib/components/PageHeader.svelte';
  import VaultNotice from '$lib/components/VaultNotice.svelte';
  import {
    createEncryptedRecord,
    decryptRecords,
    deleteEncryptedRecord,
    fetchEncryptedRecords,
    randomId,
    updateEncryptedRecord
  } from '$lib/client/records';
  import { sessionDEK } from '$lib/stores/cryptoKey';
  import type { EncryptedRecord, ListPayload } from '$lib/types';
  import { goto } from '$app/navigation';
  import { get } from 'svelte/store';
  import { onMount } from 'svelte';
  import { Plus, Trash2 } from '@lucide/svelte';

  type ListItem = {
    record: EncryptedRecord;
    payload: ListPayload;
  };

  const emptyList = (): ListPayload => ({
    title: '',
    description: '',
    checklist: true,
    tasks: []
  });

  let dek: CryptoKey | null = null;
  let locked = false;
  let loading = true;
  let lists: ListItem[] = [];
  let selectedId: string | null = null;
  let form = emptyList();
  let newTask = '';

  $: selected = lists.find((list) => list.record.id === selectedId) ?? lists[0] ?? null;

  async function loadLists() {
    if (!dek) return;
    loading = true;
    const records = await fetchEncryptedRecords('list');
    lists = await decryptRecords<ListPayload>(records, dek);
    if (!selectedId && lists[0]) selectedId = lists[0].record.id;
    loading = false;
  }

  async function saveList() {
    if (!dek || !form.title.trim()) return;
    await createEncryptedRecord(
      'list',
      {
        ...form,
        title: form.title.trim(),
        description: form.description.trim()
      },
      dek
    );
    form = emptyList();
    await loadLists();
  }

  async function updateList(item: ListItem, payload: ListPayload) {
    if (!dek) return;
    await updateEncryptedRecord(item.record.id, 'list', payload, dek);
    await loadLists();
  }

  async function removeList(item: ListItem) {
    if (!confirm('Delete this list?')) return;
    await deleteEncryptedRecord(item.record.id);
    selectedId = null;
    await loadLists();
  }

  async function addTask(item: ListItem) {
    if (!newTask.trim()) return;
    await updateList(item, {
      ...item.payload,
      tasks: [...item.payload.tasks, { id: randomId(), text: newTask.trim(), done: false }]
    });
    newTask = '';
  }

  async function toggleTask(item: ListItem, taskId: string) {
    await updateList(item, {
      ...item.payload,
      tasks: item.payload.tasks.map((task) => (task.id === taskId ? { ...task, done: !task.done } : task))
    });
  }

  async function removeTask(item: ListItem, taskId: string) {
    await updateList(item, {
      ...item.payload,
      tasks: item.payload.tasks.filter((task) => task.id !== taskId)
    });
  }

  onMount(async () => {
    dek = get(sessionDEK);
    if (!dek) {
      locked = true;
      loading = false;
      await goto('/login');
      return;
    }
    await loadLists();
  });
</script>

<PageHeader title="Lists" description="Encrypted checklists and plain lists, stored as self-contained records." />

{#if locked}
  <VaultNotice />
{:else}
  <div class="grid gap-6 lg:grid-cols-[300px_minmax(0,1fr)]">
    <aside class="space-y-4">
      <form class="vault-card p-4" on:submit|preventDefault={saveList}>
        <h2 class="mb-4 text-base font-semibold" style="color: var(--foreground)">Create list</h2>
        <label class="block text-sm font-medium">
          Title
          <input class="focus-ring vault-input mt-1.5" bind:value={form.title} required />
        </label>
        <label class="mt-3 block text-sm font-medium">
          Description
          <textarea class="focus-ring vault-input mt-1.5" bind:value={form.description}></textarea>
        </label>
        <label class="mt-3 flex items-center gap-2 text-sm font-medium">
          <input type="checkbox" bind:checked={form.checklist} class="rounded border-slate-300 text-blue-600 focus:ring-blue-500" />
          Checklist
        </label>
        <button class="focus-ring vault-btn-primary mt-4" type="submit">
          <Plus size={16} />
          Create
        </button>
      </form>

      {#if lists.length}
        <nav class="space-y-2">
          {#each lists as item}
            <button
              class="focus-ring w-full rounded-xl px-3 py-2.5 text-left text-sm font-medium transition-all"
              class:vault-card={selected?.record.id === item.record.id}
              style={selected?.record.id === item.record.id
                ? 'color: var(--accent); background: var(--accent-light); border: 1px solid rgba(37,99,235,0.15)'
                : 'color: var(--foreground); background: transparent; border: 1px solid transparent'}
              type="button"
              on:click={() => (selectedId = item.record.id)}
            >
              {item.payload.title}
            </button>
          {/each}
        </nav>
      {/if}
    </aside>

    <section>
      {#if loading}
        <p class="text-sm" style="color: var(--muted)">Decrypting lists...</p>
      {:else if !selected}
        <EmptyState title="No lists yet" description="Create a list to start tracking tasks." />
      {:else}
        <div class="vault-card p-5">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <h2 class="text-xl font-semibold" style="color: var(--foreground)">{selected.payload.title}</h2>
              {#if selected.payload.description}
                <p class="mt-2 text-sm leading-relaxed" style="color: var(--muted)">{selected.payload.description}</p>
              {/if}
            </div>
            <button class="focus-ring vault-btn-danger" type="button" on:click={() => removeList(selected)}>
              <Trash2 size={16} />
            </button>
          </div>

          <form class="mt-6 flex gap-2" on:submit|preventDefault={() => addTask(selected)}>
            <input class="focus-ring vault-input min-w-0 flex-1" bind:value={newTask} placeholder="New task" />
            <button class="focus-ring vault-btn-primary shrink-0" type="submit">Add</button>
          </form>

          <div class="mt-5 divide-y" style="border-color: var(--border)">
            {#each selected.payload.tasks as task}
              <div class="flex items-center gap-3 py-3 group">
                {#if selected.payload.checklist}
                  <input type="checkbox" checked={task.done} on:change={() => toggleTask(selected, task.id)} class="rounded border-slate-300 text-blue-600 focus:ring-blue-500" />
                {/if}
                <span class:line-through={task.done} class="flex-1 text-sm" style="color: var(--foreground)">{task.text}</span>
                <button class="focus-ring vault-btn-ghost opacity-0 group-hover:opacity-100 transition-opacity" type="button" on:click={() => removeTask(selected, task.id)}>
                  <Trash2 size={15} />
                </button>
              </div>
            {/each}
          </div>
        </div>
      {/if}
    </section>
  </div>
{/if}
