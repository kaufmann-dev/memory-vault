<script lang="ts">
  import EmptyState from '$lib/components/EmptyState.svelte';
  import LoadingState from '$lib/components/LoadingState.svelte';
  import EntryModal from '$lib/components/EntryModal.svelte';
  import PageHeader from '$lib/components/PageHeader.svelte';
  import CollectionNav from '$lib/components/CollectionNav.svelte';
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
  import * as Card from '$lib/components/ui/card/index.js';
  import { Button } from '$lib/components/ui/button/index.js';
  import { Input } from '$lib/components/ui/input/index.js';
  import { Textarea } from '$lib/components/ui/textarea/index.js';
  import { Label } from '$lib/components/ui/label/index.js';
  import { Checkbox } from '$lib/components/ui/checkbox/index.js';
  import { Separator } from '$lib/components/ui/separator/index.js';

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
  let loading = $state(true);
  let lists: ListItem[] = $state([]);
  let selectedId: string | null = $state(null);
  let form = $state(emptyList());
  let editingListId: string | null = $state(null);
  let newTask = $state('');
  let listFormOpen = $state(false);
  let taskFormOpen = $state(false);

  let selected = $derived(lists.find((list) => list.record.id === selectedId) ?? lists[0] ?? null);

  let navItems = $derived(
    lists.map((item) => ({
      id: item.record.id,
      label: item.payload.title || 'Untitled list',
      taskCount: item.payload.tasks.length,
      checklist: item.payload.checklist
    }))
  );

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
      loading = false;
      return;
    }
    await loadLists();
  });
</script>

<PageHeader title="Lists" description="Encrypted checklists and plain lists, stored as self-contained records.">
  <Button onclick={openCreateList}>
    <Plus class="size-4" />
    New
  </Button>
</PageHeader>

{#snippet listRow(item: (typeof navItems)[number])}
  <span class="min-w-0 flex-1 truncate text-left">{item.label}</span>
  <span class="text-muted-foreground ml-auto shrink-0 text-xs whitespace-nowrap">
    {item.taskCount} · {item.checklist ? 'Checklist' : 'List'}
  </span>
{/snippet}

<div class="grid gap-6 sm:grid-cols-[16rem_minmax(0,1fr)]">
  <aside class="min-w-0">
    <CollectionNav
      items={navItems}
      selected={selected?.record.id ?? null}
      onSelect={(id) => (selectedId = id)}
      row={listRow}
      searchPlaceholder="Search lists"
      emptyText="No lists found."
      ariaLabel="Select list"
    />
  </aside>

  <section>
    {#if loading}
      <LoadingState message="Decrypting lists…" />
    {:else if !selected}
      <EmptyState title="No lists yet" description="Create a list to start tracking tasks." />
    {:else}
      <Card.Root>
        <Card.Header>
          <Card.Title class="text-lg break-words">{selected.payload.title}</Card.Title>
          {#if selected.payload.description}
            <Card.Description>{selected.payload.description}</Card.Description>
          {/if}
          <Card.Action class="flex gap-1">
            <Button variant="ghost" size="icon" onclick={() => (taskFormOpen = true)} aria-label="Add task">
              <Plus class="size-4" />
            </Button>
            <Button variant="ghost" size="icon" onclick={() => openEditList(selected)} aria-label="Edit list">
              <Pencil class="size-4" />
            </Button>
            <Button
              variant="ghost"
              size="icon"
              class="text-destructive hover:text-destructive"
              onclick={() => removeList(selected)}
              aria-label="Delete list"
            >
              <Trash2 class="size-4" />
            </Button>
          </Card.Action>
        </Card.Header>
        <Card.Content>
          <ul class="grid">
            {#each selected.payload.tasks as task, index (task.id)}
              {#if index > 0}<Separator />{/if}
              <li class="group flex items-center gap-3 py-3">
                {#if selected.payload.checklist}
                  <Checkbox checked={task.done} onCheckedChange={() => toggleTask(selected, task.id)} />
                {/if}
                <span class="flex-1 text-sm" class:line-through={task.done} class:text-muted-foreground={task.done}>
                  {task.text}
                </span>
                <Button
                  variant="ghost"
                  size="icon"
                  class="text-muted-foreground hover:text-destructive size-8 opacity-100 transition-opacity [@media(hover:hover)]:opacity-0 [@media(hover:hover)]:group-hover:opacity-100"
                  onclick={() => removeTask(selected, task.id)}
                  aria-label="Delete task"
                >
                  <Trash2 class="size-4" />
                </Button>
              </li>
            {/each}
          </ul>
        </Card.Content>
      </Card.Root>
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
    class="grid gap-4"
    onsubmit={(event) => {
      event.preventDefault();
      saveList();
    }}
  >
    <div class="grid gap-2">
      <Label for="list-title">Title</Label>
      <Input id="list-title" bind:value={form.title} required />
    </div>
    <div class="grid gap-2">
      <Label for="list-description">Description</Label>
      <Textarea id="list-description" bind:value={form.description} />
    </div>
    <Label for="list-checklist" class="flex items-center gap-2 font-normal">
      <Checkbox id="list-checklist" bind:checked={form.checklist} />
      Checklist
    </Label>
    <div class="flex justify-end">
      <Button type="submit">{editingListId ? 'Save' : 'Create'}</Button>
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
      class="grid gap-4"
      onsubmit={(event) => {
        event.preventDefault();
        addTask(selected);
      }}
    >
      <div class="grid gap-2">
        <Label for="new-task">Task</Label>
        <Input id="new-task" bind:value={newTask} required />
      </div>
      <div class="flex justify-end">
        <Button type="submit">Add</Button>
      </div>
    </form>
  </EntryModal>
{/if}
