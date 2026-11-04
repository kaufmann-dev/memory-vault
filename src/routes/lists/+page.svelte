<script lang="ts">
  import EmptyState from '#lib/components/EmptyState.svelte';
  import LoadingState from '#lib/components/LoadingState.svelte';
  import EntryModal from '#lib/components/EntryModal.svelte';
  import PageHeader from '#lib/components/PageHeader.svelte';
  import CollectionNav from '#lib/components/CollectionNav.svelte';
  import HomePinButton from '#lib/components/HomePinButton.svelte';
  import {
    createEncryptedRecord,
    decryptRecords,
    deleteEncryptedRecord,
    fetchEncryptedRecords,
    randomId,
    updateEncryptedRecord
  } from '#lib/client/records.js';
  import { columnLabel, entryValues, listKindLabels, moveEntry, newColumn } from '#lib/list.js';
  import { sessionDEK } from '#lib/stores/cryptoKey.js';
  import { LIST_KINDS, type EncryptedRecord, type ListEntry, type ListKind, type ListPayload } from '#lib/types.js';
  import { page } from '$app/state';
  import { get } from 'svelte/store';
  import { onMount } from 'svelte';
  import { ArrowDown, ArrowUp, Pencil, Plus, Trash2 } from '@lucide/svelte';
  import * as Card from '#lib/components/ui/card/index.js';
  import * as Table from '#lib/components/ui/table/index.js';
  import { Button } from '#lib/components/ui/button/index.js';
  import { Input } from '#lib/components/ui/input/index.js';
  import { Textarea } from '#lib/components/ui/textarea/index.js';
  import { Label } from '#lib/components/ui/label/index.js';
  import { Checkbox } from '#lib/components/ui/checkbox/index.js';
  import * as Select from '#lib/components/ui/select/index.js';
  import { Separator } from '#lib/components/ui/separator/index.js';

  type ListItem = {
    record: EncryptedRecord;
    payload: ListPayload;
  };

  const emptyList = (): ListPayload => ({
    title: '',
    description: '',
    kind: 'plain',
    columns: [newColumn()],
    entries: []
  });

  let dek: CryptoKey | null = null;
  let loading = $state(true);
  let lists: ListItem[] = $state([]);
  let selectedId: string | null = $state(null);
  let form = $state(emptyList());
  let editingListId: string | null = $state(null);
  let entryForm: Record<string, string> = $state({});
  let editingEntryId: string | null = $state(null);
  let listFormOpen = $state(false);
  let entryFormOpen = $state(false);

  let selected = $derived(lists.find((list) => list.record.id === selectedId) ?? lists[0] ?? null);
  let openEntries = $derived(
    selected?.payload.kind === 'checklist'
      ? selected.payload.entries.filter((entry) => !entry.done)
      : (selected?.payload.entries ?? [])
  );
  let completedEntries = $derived(
    selected?.payload.kind === 'checklist' ? selected.payload.entries.filter((entry) => entry.done) : []
  );

  let navItems = $derived(
    lists.map((item) => ({
      id: item.record.id,
      label: item.payload.title || 'Untitled list',
      entryCount: item.payload.entries.length,
      kind: item.payload.kind
    }))
  );

  async function loadLists() {
    if (!dek) return;
    loading = true;
    const records = await fetchEncryptedRecords('list');
    lists = await decryptRecords<ListPayload>(records, dek);
    if (!selectedId) {
      const linkedId = page.url.searchParams.get('id');
      selectedId = (lists.find((list) => list.record.id === linkedId) ?? lists[0])?.record.id ?? null;
    }
    loading = false;
  }

  function openCreateList() {
    editingListId = null;
    form = emptyList();
    listFormOpen = true;
  }

  function openEditList(item: ListItem) {
    editingListId = item.record.id;
    form = $state.snapshot(item.payload);
    listFormOpen = true;
  }

  function closeListForm() {
    listFormOpen = false;
    editingListId = null;
    form = emptyList();
  }

  function addColumn() {
    form.columns = [...form.columns, newColumn()];
  }

  function removeColumn(columnId: string) {
    if (form.columns.length === 1) return;
    form.columns = form.columns.filter((column) => column.id !== columnId);
  }

  async function saveList() {
    if (!dek || !form.title.trim()) return;
    const columns = form.columns.map((column) => ({ ...column, name: column.name.trim() }));
    const payload: ListPayload = {
      ...form,
      title: form.title.trim(),
      description: form.description.trim(),
      columns,
      entries: form.entries.map((entry) => ({ ...entry, values: entryValues(columns, entry.values) }))
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

  function openAddEntry(item: ListItem) {
    editingEntryId = null;
    entryForm = entryValues(item.payload.columns, {});
    entryFormOpen = true;
  }

  function openEditEntry(item: ListItem, entry: ListEntry) {
    editingEntryId = entry.id;
    entryForm = entryValues(item.payload.columns, entry.values);
    entryFormOpen = true;
  }

  function closeEntryForm() {
    entryFormOpen = false;
    editingEntryId = null;
    entryForm = {};
  }

  async function saveEntry(item: ListItem) {
    const values = entryValues(item.payload.columns, entryForm);
    if (!values[item.payload.columns[0].id]) return;
    const id = editingEntryId;
    await updateList(item, {
      ...item.payload,
      entries: id
        ? item.payload.entries.map((entry) => (entry.id === id ? { ...entry, values } : entry))
        : [...item.payload.entries, { id: randomId(), values, done: false }]
    });
    closeEntryForm();
  }

  async function toggleEntry(item: ListItem, entryId: string) {
    await updateList(item, {
      ...item.payload,
      entries: item.payload.entries.map((entry) => (entry.id === entryId ? { ...entry, done: !entry.done } : entry))
    });
  }

  async function moveListEntry(item: ListItem, entryId: string, offset: -1 | 1) {
    await updateList(item, { ...item.payload, entries: moveEntry(item.payload.entries, entryId, offset) });
  }

  async function toggleHomePinned(item: ListItem) {
    await updateList(item, { ...item.payload, homePinned: !item.payload.homePinned });
  }

  async function removeEntry(item: ListItem, entryId: string) {
    await updateList(item, {
      ...item.payload,
      entries: item.payload.entries.filter((entry) => entry.id !== entryId)
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

<PageHeader title="Lists" description="Encrypted checklists, plain lists, and ordered lists, stored as self-contained records.">
  <Button onclick={openCreateList}>
    <Plus class="size-4" />
    New
  </Button>
</PageHeader>

{#snippet listRow(item: (typeof navItems)[number])}
  <span class="min-w-0 flex-1 truncate text-left">{item.label}</span>
  <span class="text-muted-foreground ml-auto shrink-0 text-xs whitespace-nowrap">
    {item.entryCount} · {listKindLabels[item.kind]}
  </span>
{/snippet}

{#snippet entryCheckbox(item: ListItem, entry: ListEntry)}
  <Checkbox checked={entry.done} onCheckedChange={() => toggleEntry(item, entry.id)} />
{/snippet}

{#snippet entryActions(item: ListItem, entry: ListEntry, index: number)}
  <div
    class="flex shrink-0 justify-end gap-1 opacity-100 transition-opacity [@media(hover:hover)]:opacity-0 [@media(hover:hover)]:group-focus-within:opacity-100 [@media(hover:hover)]:group-hover:opacity-100"
  >
    {#if item.payload.kind === 'ordered'}
      <Button
        variant="ghost"
        size="icon"
        class="text-muted-foreground size-8"
        onclick={() => moveListEntry(item, entry.id, -1)}
        aria-label="Move up"
        disabled={index === 0}
      >
        <ArrowUp class="size-4" />
      </Button>
      <Button
        variant="ghost"
        size="icon"
        class="text-muted-foreground size-8"
        onclick={() => moveListEntry(item, entry.id, 1)}
        aria-label="Move down"
        disabled={index === item.payload.entries.length - 1}
      >
        <ArrowDown class="size-4" />
      </Button>
    {/if}
    <Button
      variant="ghost"
      size="icon"
      class="text-muted-foreground size-8"
      onclick={() => openEditEntry(item, entry)}
      aria-label="Edit entry"
    >
      <Pencil class="size-4" />
    </Button>
    <Button
      variant="ghost"
      size="icon"
      class="text-muted-foreground hover:text-destructive size-8"
      onclick={() => removeEntry(item, entry.id)}
      aria-label="Delete entry"
    >
      <Trash2 class="size-4" />
    </Button>
  </div>
{/snippet}

{#snippet entryRow(item: ListItem, entry: ListEntry, index: number)}
  <li class="group flex items-center gap-3 py-3">
    {#if item.payload.kind === 'checklist'}
      {@render entryCheckbox(item, entry)}
    {:else if item.payload.kind === 'ordered'}
      <span class="text-muted-foreground w-6 shrink-0 text-right text-sm tabular-nums">{index + 1}.</span>
    {/if}
    <span class={['min-w-0 flex-1 text-sm wrap-anywhere', entry.done && 'text-muted-foreground line-through']}>
      {entry.values[item.payload.columns[0].id]}
    </span>
    {@render entryActions(item, entry, index)}
  </li>
{/snippet}

{#snippet entryTableRow(item: ListItem, entry: ListEntry, index: number)}
  <Table.Row class="group">
    {#if item.payload.kind === 'checklist'}
      <Table.Cell class="w-8">{@render entryCheckbox(item, entry)}</Table.Cell>
    {:else if item.payload.kind === 'ordered'}
      <Table.Cell class="text-muted-foreground w-8 tabular-nums">{index + 1}</Table.Cell>
    {/if}
    {#each item.payload.columns as column (column.id)}
      <Table.Cell
        class={['min-w-24 whitespace-normal wrap-break-word', entry.done && 'text-muted-foreground line-through']}
      >
        {entry.values[column.id]}
      </Table.Cell>
    {/each}
    <Table.Cell class="w-px">{@render entryActions(item, entry, index)}</Table.Cell>
  </Table.Row>
{/snippet}

<div class="grid gap-6 md:grid-cols-[16rem_minmax(0,1fr)]">
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

  <section class="min-w-0">
    {#if loading}
      <LoadingState message="Decrypting lists…" />
    {:else if !selected}
      <EmptyState title="No lists yet" description="Create a list to start collecting entries." />
    {:else}
      <Card.Root>
        <Card.Header>
          <Card.Title class="text-lg wrap-anywhere">{selected.payload.title}</Card.Title>
          {#if selected.payload.description}
            <Card.Description class="wrap-anywhere">{selected.payload.description}</Card.Description>
          {/if}
          <Card.Action class="flex gap-1">
            <Button variant="ghost" size="icon" onclick={() => openAddEntry(selected)} aria-label="Add entry">
              <Plus class="size-4" />
            </Button>
            <HomePinButton pinned={selected.payload.homePinned ?? false} onclick={() => toggleHomePinned(selected)} />
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
          {#if selected.payload.columns.length > 1}
            <Table.Root>
              <Table.Header>
                <Table.Row>
                  {#if selected.payload.kind === 'checklist'}
                    <Table.Head class="w-8"><span class="sr-only">Done</span></Table.Head>
                  {:else if selected.payload.kind === 'ordered'}
                    <Table.Head class="w-8">#</Table.Head>
                  {/if}
                  {#each selected.payload.columns as column (column.id)}
                    <Table.Head class="whitespace-normal wrap-break-word">{columnLabel(column)}</Table.Head>
                  {/each}
                  <Table.Head class="w-px"><span class="sr-only">Actions</span></Table.Head>
                </Table.Row>
              </Table.Header>
              <Table.Body>
                {#each openEntries as entry, index (entry.id)}
                  {@render entryTableRow(selected, entry, index)}
                {/each}
                {#if completedEntries.length > 0}
                  <Table.Row class="hover:bg-transparent">
                    <Table.Cell
                      colspan={selected.payload.columns.length + 2}
                      class={[
                        'text-muted-foreground text-xs font-medium tracking-wide uppercase',
                        openEntries.length > 0 && 'pt-6'
                      ]}
                    >
                      Completed · {completedEntries.length}
                    </Table.Cell>
                  </Table.Row>
                  {#each completedEntries as entry, index (entry.id)}
                    {@render entryTableRow(selected, entry, index)}
                  {/each}
                {/if}
              </Table.Body>
            </Table.Root>
          {:else}
            <svelte:element this={selected.payload.kind === 'ordered' ? 'ol' : 'ul'} class="grid">
              {#each openEntries as entry, index (entry.id)}
                {#if index > 0}<Separator />{/if}
                {@render entryRow(selected, entry, index)}
              {/each}
            </svelte:element>
            {#if completedEntries.length > 0}
              <h3
                class={[
                  'text-muted-foreground pt-3 text-xs font-medium tracking-wide uppercase',
                  openEntries.length > 0 && 'mt-4'
                ]}
              >
                Completed · {completedEntries.length}
              </h3>
              <ul class="grid">
                {#each completedEntries as entry, index (entry.id)}
                  {#if index > 0}<Separator />{/if}
                  {@render entryRow(selected, entry, index)}
                {/each}
              </ul>
            {/if}
          {/if}
        </Card.Content>
      </Card.Root>
    {/if}
  </section>
</div>

<EntryModal
  open={listFormOpen}
  title={editingListId ? 'Edit list' : 'New list'}
  description={editingListId ? 'Update this private list.' : 'Create a private list. Entries can be added once the list exists.'}
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
    <div class="grid gap-2">
      <Label>Type</Label>
      <Select.Root type="single" value={form.kind} onValueChange={(value) => (form.kind = value as ListKind)}>
        <Select.Trigger class="w-full">{listKindLabels[form.kind]}</Select.Trigger>
        <Select.Content>
          {#each LIST_KINDS as kind (kind)}
            <Select.Item value={kind} label={listKindLabels[kind]}>{listKindLabels[kind]}</Select.Item>
          {/each}
        </Select.Content>
      </Select.Root>
    </div>

    <div class="grid gap-3">
      <div class="flex items-center justify-between gap-3">
        <div class="grid gap-1">
          <h3 class="text-sm font-semibold">Columns</h3>
          <p class="text-muted-foreground text-xs">Add columns to store several values per entry.</p>
        </div>
        <Button variant="ghost" size="icon" class="size-8" onclick={addColumn} aria-label="Add column">
          <Plus class="size-4" />
        </Button>
      </div>

      {#each form.columns as column (column.id)}
        <div class="grid grid-cols-[minmax(0,1fr)_2.5rem] items-center gap-2">
          <Input
            class="min-w-0"
            bind:value={column.name}
            placeholder={form.columns.length > 1 ? 'Name' : 'Name (optional)'}
            required={form.columns.length > 1}
            aria-label="Column name"
          />
          <Button
            variant="ghost"
            size="icon"
            class="text-muted-foreground hover:text-destructive size-8"
            onclick={() => removeColumn(column.id)}
            aria-label="Remove column"
            disabled={form.columns.length === 1}
          >
            <Trash2 class="size-4" />
          </Button>
        </div>
      {/each}
    </div>

    <div class="flex justify-end">
      <Button type="submit">{editingListId ? 'Save' : 'Create'}</Button>
    </div>
  </form>
</EntryModal>

{#if selected}
  <EntryModal
    open={entryFormOpen}
    title={editingEntryId ? 'Edit entry' : 'New entry'}
    description={editingEntryId
      ? `Update this entry in ${selected.payload.title}.`
      : `Add an entry to ${selected.payload.title}.`}
    onClose={closeEntryForm}
  >
    <form
      class="grid gap-4"
      onsubmit={(event) => {
        event.preventDefault();
        saveEntry(selected);
      }}
    >
      {#each selected.payload.columns as column, index (column.id)}
        <div class="grid gap-2">
          <Label for={`entry-${column.id}`} class="wrap-anywhere">{columnLabel(column)}</Label>
          <Input id={`entry-${column.id}`} bind:value={entryForm[column.id]} required={index === 0} />
        </div>
      {/each}
      <div class="flex justify-end">
        <Button type="submit">{editingEntryId ? 'Save' : 'Add'}</Button>
      </div>
    </form>
  </EntryModal>
{/if}
