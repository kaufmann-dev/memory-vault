<script lang="ts">
  import EmptyState from '$lib/components/EmptyState.svelte';
  import EntryModal from '$lib/components/EntryModal.svelte';
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
  import { get } from 'svelte/store';
  import { onMount } from 'svelte';
  import { Pencil, Plus, Trash2 } from '@lucide/svelte';

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
  let locked = $state(false);
  let loading = $state(true);
  let lists: ListItem[] = $state([]);
  let selectedId: string | null = $state(null);
  let form = $state(emptyList());
  let editingListId: string | null = $state(null);
  let newTask = $state('');
  let listFormOpen = $state(false);
  let taskFormOpen = $state(false);

  let selected = $derived(lists.find((list) => list.record.id === selectedId) ?? lists[0] ?? null);

  async function loadLists() {
    if (!dek) return;
    loading = true;
    const records = await fetchEncryptedRecords('list');
    lists = await decryptRecords<ListPayload>(records, dek);
    if (!selectedId && lists[0]) selectedId = lists[0].record.id;
    loading = false;
  }

  function openCreateList() {
    editingListId = null;
    form = emptyList();
    listFormOpen = true;
  }

  function openEditList(item: ListItem) {
    editingListId = item.record.id;
    form = {
      title: item.payload.title,
      description: item.payload.description,
      checklist: item.payload.checklist,
      tasks: [...item.payload.tasks]
    };
    listFormOpen = true;
  }

  function closeListForm() {
    listFormOpen = false;
    editingListId = null;
    form = emptyList();
  }

  async function saveList() {
    if (!dek || !form.title.trim()) return;
    const payload = {
      ...form,
      title: form.title.trim(),
      description: form.description.trim()
    };

    if (editingListId) {
      const id = editingListId;
      lists = lists.map((list) => (list.record.id === id ? { ...list, payload } : list));
      await updateEncryptedRecord(id, 'list', payload, dek);
    } else {
      const record = await createEncryptedRecord('list', payload, dek);
      lists = [...lists, { record, payload }];
      selectedId = record.id;
    }

    closeListForm();
  }

  async function updateList(item: ListItem, payload: ListPayload) {
    if (!dek) return;
    lists = lists.map((list) => (list.record.id === item.record.id ? { ...list, payload } : list));
    await updateEncryptedRecord(item.record.id, 'list', payload, dek);
  }

  async function removeList(item: ListItem) {
    if (!confirm('Delete this list?')) return;
    lists = lists.filter((list) => list.record.id !== item.record.id);
    selectedId = null;
    await deleteEncryptedRecord(item.record.id);
  }

  async function addTask(item: ListItem) {
    if (!newTask.trim()) return;
    await updateList(item, {
      ...item.payload,
      tasks: [...item.payload.tasks, { id: randomId(), text: newTask.trim(), done: false }]
    });
    newTask = '';
    taskFormOpen = false;
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
      return;
    }
    await loadLists();
  });
</script>

<PageHeader title="Lists" description="Encrypted checklists and plain lists, stored as self-contained records.">
  <button class="focus-ring vault-btn-primary" type="button" onclick={openCreateList}>
    <Plus size={16} />
    New
  </button>
</PageHeader>

{#if locked}
  <VaultNotice />
{:else}
  <div class="grid gap-6 lg:grid-cols-[300px_minmax(0,1fr)]">
    <aside class="space-y-4">
      {#if lists.length}
        <nav class="space-y-1">
          {#each lists as item (item.record.id)}
            <button
              class="focus-ring w-full rounded-lg px-3 py-2.5 text-left text-sm font-medium transition-colors"
              style={selected?.record.id === item.record.id
                ? 'color: var(--foreground); background: var(--accent-light); border: 1px solid var(--border)'
                : 'color: var(--foreground); background: transparent; border: 1px solid transparent'}
              type="button"
              onclick={() => (selectedId = item.record.id)}
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
          <div class="space-y-3">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
              <h2 class="min-w-0 break-words text-lg font-semibold" style="color: var(--foreground)">{selected.payload.title}</h2>
              <div class="flex shrink-0 gap-2">
                <button class="focus-ring vault-btn-primary" type="button" onclick={() => (taskFormOpen = true)}>+ Add</button>
                <button class="focus-ring vault-btn-secondary" type="button" onclick={() => openEditList(selected)}>
                  <Pencil size={16} />
                  Edit
                </button>
                <button class="focus-ring vault-btn-danger" type="button" onclick={() => removeList(selected)} aria-label="Delete list">
                  <Trash2 size={16} />
                </button>
              </div>
            </div>
            {#if selected.payload.description}
              <p class="text-sm leading-relaxed" style="color: var(--muted)">{selected.payload.description}</p>
            {/if}
          </div>

          <div class="mt-5 divide-y" style="border-color: var(--border)">
            {#each selected.payload.tasks as task (task.id)}
              <div class="flex items-center gap-3 py-3 group">
                {#if selected.payload.checklist}
                  <input type="checkbox" checked={task.done} onchange={() => toggleTask(selected, task.id)} class="rounded border-neutral-300 text-neutral-900 focus:ring-neutral-900" />
                {/if}
                <span class:line-through={task.done} class="flex-1 text-sm" style="color: var(--foreground)">{task.text}</span>
                <button class="focus-ring vault-btn-ghost opacity-100 transition-opacity [@media(hover:hover)]:opacity-0 [@media(hover:hover)]:group-hover:opacity-100" type="button" onclick={() => removeTask(selected, task.id)}>
                  <Trash2 size={15} />
                </button>
              </div>
            {/each}
          </div>
        </div>
      {/if}
    </section>
  </div>

  <EntryModal
    open={listFormOpen}
    title={editingListId ? 'Edit list' : 'New list'}
    description={editingListId ? 'Update this private list shell.' : 'Create a private list shell. Tasks can be added once the list exists.'}
    onClose={closeListForm}
  >
    <form
      class="space-y-4"
      onsubmit={(event) => {
        event.preventDefault();
        saveList();
      }}
    >
      <label class="block text-sm font-medium">
        Title
        <input class="focus-ring vault-input mt-1.5" bind:value={form.title} required />
      </label>
      <label class="block text-sm font-medium">
        Description
        <textarea class="focus-ring vault-input mt-1.5" bind:value={form.description}></textarea>
      </label>
      <label class="flex items-center gap-2 text-sm font-medium">
        <input type="checkbox" bind:checked={form.checklist} class="rounded border-neutral-300 text-neutral-900 focus:ring-neutral-900" />
        Checklist
      </label>
      <div class="flex justify-end">
        <button class="focus-ring vault-btn-primary" type="submit">{editingListId ? 'Save' : 'Create'}</button>
      </div>
    </form>
  </EntryModal>

  {#if selected}
    <EntryModal
      open={taskFormOpen}
      title="New task"
      description={`Add a task to ${selected.payload.title}.`}
      onClose={() => {
        taskFormOpen = false;
        newTask = '';
      }}
    >
      <form
        class="space-y-4"
        onsubmit={(event) => {
          event.preventDefault();
          addTask(selected);
        }}
      >
        <label class="block text-sm font-medium">
          Task
          <input class="focus-ring vault-input mt-1.5" bind:value={newTask} required />
        </label>
        <div class="flex justify-end">
          <button class="focus-ring vault-btn-primary" type="submit">Add</button>
        </div>
      </form>
    </EntryModal>
  {/if}
{/if}
