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
    updateEncryptedRecord
  } from '$lib/client/records';
  import { sessionDEK } from '$lib/stores/cryptoKey';
  import type { EncryptedRecord, NoteGroupPayload, NotePayload } from '$lib/types';
  import { get } from 'svelte/store';
  import { onMount } from 'svelte';
  import { Pencil, Pin, Plus, Search, Settings2, Trash2, X } from '@lucide/svelte';
  import * as Card from '$lib/components/ui/card/index.js';
  import * as Select from '$lib/components/ui/select/index.js';
  import { Button } from '$lib/components/ui/button/index.js';
  import { Input } from '$lib/components/ui/input/index.js';
  import { Textarea } from '$lib/components/ui/textarea/index.js';
  import { Label } from '$lib/components/ui/label/index.js';
  import { Checkbox } from '$lib/components/ui/checkbox/index.js';

  type NoteItem = {
    record: EncryptedRecord;
    payload: NotePayload;
  };

  type GroupItem = {
    record: EncryptedRecord;
    payload: NoteGroupPayload;
  };

  type SortOrder = 'newest' | 'oldest';

  const noteTextMaxLength = 50000;
  const groupColors = ['#2563eb', '#0f766e', '#7c3aed', '#be123c', '#64748b', '#a16207'];
  const nowIso = () => new Date().toISOString();
  const emptyNote = (): NotePayload => ({
    title: '',
    text: '',
    groupIds: [],
    pinned: false,
    createdAt: nowIso(),
    updatedAt: nowIso()
  });
  const emptyGroup = (): NoteGroupPayload => ({ name: '', description: '', color: groupColors[0] });

  let dek: CryptoKey | null = null;
  let loading = $state(true);
  let saving = $state(false);
  let notes: NoteItem[] = $state([]);
  let groups: GroupItem[] = $state([]);
  let noteForm = $state(emptyNote());
  let groupForm = $state(emptyGroup());
  let editingNoteId: string | null = $state(null);
  let editingGroupId: string | null = $state(null);
  let noteFormOpen = $state(false);
  let groupFormOpen = $state(false);
  let manageGroupsOpen = $state(false);
  let query = $state('');
  let activeGroupId = $state('all');
  let sortOrder: SortOrder = $state('newest');

  let groupById = $derived.by(() => new Map(groups.map((group) => [group.record.id, group])));

  let filteredNotes = $derived.by(() => {
    const normalizedQuery = query.trim().toLowerCase();

    return notes
      .filter((item) => {
        if (activeGroupId === 'ungrouped' && item.payload.groupIds.length > 0) return false;
        if (activeGroupId !== 'all' && activeGroupId !== 'ungrouped' && !item.payload.groupIds.includes(activeGroupId)) return false;
        if (!normalizedQuery) return true;

        const groupText = item.payload.groupIds
          .map((groupId) => groupById.get(groupId)?.payload.name ?? '')
          .join(' ');
        return `${item.payload.title ?? ''} ${item.payload.text} ${groupText}`.toLowerCase().includes(normalizedQuery);
      })
      .sort((a, b) => {
        if (a.payload.pinned !== b.payload.pinned) return a.payload.pinned ? -1 : 1;
        const direction = sortOrder === 'newest' ? -1 : 1;
        return a.payload.updatedAt.localeCompare(b.payload.updatedAt) * direction;
      });
  });

  let ungroupedCount = $derived(notes.filter((note) => note.payload.groupIds.length === 0).length);
  let hasFilters = $derived(Boolean(query.trim() || activeGroupId !== 'all' || sortOrder !== 'newest'));

  let navItems = $derived([
    { id: 'all', label: 'All notes', tone: 'var(--foreground)', count: notes.length },
    { id: 'ungrouped', label: 'Ungrouped', tone: 'var(--border)', count: ungroupedCount },
    ...groups.map((group) => ({
      id: group.record.id,
      label: group.payload.name,
      tone: group.payload.color,
      count: groupCount(group.record.id)
    }))
  ]);

  function formatDate(value: string) {
    return new Intl.DateTimeFormat(undefined, {
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    }).format(new Date(value));
  }

  function groupCount(groupId: string) {
    return notes.filter((note) => note.payload.groupIds.includes(groupId)).length;
  }

  function normalizeNotePayload(payload: NotePayload): NotePayload {
    return {
      title: payload.title ?? '',
      text: payload.text,
      groupIds: payload.groupIds ?? [],
      pinned: payload.pinned ?? false,
      createdAt: payload.createdAt,
      updatedAt: payload.updatedAt
    };
  }

  function clearFilters() {
    query = '';
    activeGroupId = 'all';
    sortOrder = 'newest';
  }

  function openCreateNote() {
    editingNoteId = null;
    noteForm = emptyNote();
    noteFormOpen = true;
  }

  function openNote(item: NoteItem) {
    editingNoteId = item.record.id;
    noteForm = normalizeNotePayload({
      ...item.payload,
      groupIds: [...item.payload.groupIds]
    });
    noteFormOpen = true;
  }

  function closeNoteForm() {
    noteFormOpen = false;
    editingNoteId = null;
    noteForm = emptyNote();
  }

  function openCreateGroup() {
    editingGroupId = null;
    groupForm = {
      ...emptyGroup(),
      color: groupColors[groups.length % groupColors.length]
    };
    groupFormOpen = true;
  }

  function openEditGroup(item: GroupItem) {
    editingGroupId = item.record.id;
    groupForm = { ...item.payload };
    groupFormOpen = true;
  }

  function closeGroupForm() {
    groupFormOpen = false;
    editingGroupId = null;
    groupForm = emptyGroup();
  }

  function manageCreateGroup() {
    manageGroupsOpen = false;
    openCreateGroup();
  }

  function manageEditGroup(item: GroupItem) {
    manageGroupsOpen = false;
    openEditGroup(item);
  }

  function toggleGroup(groupId: string) {
    noteForm.groupIds = noteForm.groupIds.includes(groupId)
      ? noteForm.groupIds.filter((id) => id !== groupId)
      : [...noteForm.groupIds, groupId];
  }

  function sortGroups(items: GroupItem[]) {
    return [...items].sort((a, b) => a.payload.name.localeCompare(b.payload.name));
  }

  async function loadNotes() {
    if (!dek) return;
    loading = true;
    const [noteRecords, groupRecords] = await Promise.all([
      fetchEncryptedRecords('note'),
      fetchEncryptedRecords('note_group')
    ]);
    groups = sortGroups(await decryptRecords<NoteGroupPayload>(groupRecords, dek));
    notes = (await decryptRecords<NotePayload>(noteRecords, dek)).map((item) => ({
      ...item,
      payload: normalizeNotePayload(item.payload)
    }));
    loading = false;
  }

  async function saveNote() {
    if (!dek || saving || !noteForm.text.trim()) return;
    saving = true;
    try {
      const timestamp = nowIso();
      const payload: NotePayload = {
        title: noteForm.title?.trim() ?? '',
        text: noteForm.text.trim(),
        groupIds: noteForm.groupIds.filter((groupId) => groupById.has(groupId)),
        pinned: noteForm.pinned ?? false,
        createdAt: editingNoteId ? noteForm.createdAt : timestamp,
        updatedAt: timestamp
      };

      if (editingNoteId) {
        const id = editingNoteId;
        notes = notes.map((note) => (note.record.id === id ? { ...note, payload } : note));
        await updateEncryptedRecord(id, 'note', payload, dek);
      } else {
        const record = await createEncryptedRecord('note', payload, dek);
        notes = [...notes, { record, payload }];
      }

      closeNoteForm();
    } finally {
      saving = false;
    }
  }

  async function removeCurrentNote() {
    if (!editingNoteId || !confirm('Delete this note?')) return;
    const id = editingNoteId;
    notes = notes.filter((note) => note.record.id !== id);
    closeNoteForm();
    await deleteEncryptedRecord(id);
  }

  async function togglePinned(item: NoteItem) {
    if (!dek || saving) return;
    saving = true;
    try {
      const payload = {
        ...normalizeNotePayload(item.payload),
        pinned: !item.payload.pinned,
        updatedAt: nowIso()
      };
      notes = notes.map((note) => (note.record.id === item.record.id ? { ...note, payload } : note));
      await updateEncryptedRecord(item.record.id, 'note', payload, dek);
    } finally {
      saving = false;
    }
  }

  async function saveGroup() {
    if (!dek || saving || !groupForm.name.trim()) return;
    saving = true;
    try {
      const payload: NoteGroupPayload = {
        name: groupForm.name.trim(),
        description: groupForm.description.trim(),
        color: groupForm.color || groupColors[0]
      };

      if (editingGroupId) {
        const id = editingGroupId;
        groups = sortGroups(groups.map((group) => (group.record.id === id ? { ...group, payload } : group)));
        await updateEncryptedRecord(id, 'note_group', payload, dek);
      } else {
        const record = await createEncryptedRecord('note_group', payload, dek);
        groups = sortGroups([...groups, { record, payload }]);
      }

      closeGroupForm();
    } finally {
      saving = false;
    }
  }

  async function removeGroup(item: GroupItem) {
    const activeDek = dek;
    if (!activeDek || !confirm('Delete this group? Notes in it will become ungrouped unless they have other groups.')) return;
    const groupId = item.record.id;
    const updates = notes
      .filter((note) => note.payload.groupIds.includes(groupId))
      .map((note) => ({
        note,
        payload: {
          ...note.payload,
          groupIds: note.payload.groupIds.filter((id) => id !== groupId),
          updatedAt: nowIso()
        }
      }));
    const updatedById = new Map(updates.map((update) => [update.note.record.id, update.payload]));
    notes = notes.map((note) =>
      updatedById.has(note.record.id) ? { ...note, payload: updatedById.get(note.record.id)! } : note
    );
    groups = groups.filter((group) => group.record.id !== groupId);
    if (activeGroupId === groupId) activeGroupId = 'all';
    await Promise.all([
      ...updates.map((update) => updateEncryptedRecord(update.note.record.id, 'note', update.payload, activeDek)),
      deleteEncryptedRecord(groupId)
    ]);
  }

  onMount(async () => {
    dek = get(sessionDEK);
    if (!dek) {
      loading = false;
      return;
    }
    await loadNotes();
  });
</script>

<PageHeader title="Notes" description="Short encrypted notes for quick capture and cleanup.">
  <Button onclick={openCreateNote}>
    <Plus class="size-4" />
    New
  </Button>
</PageHeader>

{#snippet groupRow(item: (typeof navItems)[number])}
  <span class="size-2.5 shrink-0 rounded-full" style="background: {item.tone}"></span>
  <span class="min-w-0 flex-1 truncate text-left">{item.label}</span>
  <span class="text-muted-foreground ml-auto shrink-0 text-xs font-semibold">{item.count}</span>
{/snippet}

{#snippet manageAction()}
  <Button variant="outline" size="sm" class="w-full md:w-auto" onclick={() => (manageGroupsOpen = true)}>
    <Settings2 class="size-4" /> Manage groups
  </Button>
{/snippet}

<div class="grid gap-6 md:grid-cols-[16rem_minmax(0,1fr)]">
  <aside class="min-w-0" aria-label="Note groups">
    <CollectionNav
      items={navItems}
      selected={activeGroupId}
      onSelect={(id) => (activeGroupId = id)}
      row={groupRow}
      title="Groups"
      searchPlaceholder="Search groups"
      emptyText="No groups found."
      ariaLabel="Select group"
      action={manageAction}
    />
  </aside>

  <section class="min-w-0">
    <div class="bg-background grid grid-cols-2 gap-3 border-y py-3 sm:flex sm:flex-wrap sm:items-center">
      <div class="relative min-w-0 sm:flex-1">
        <Search class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
        <Input bind:value={query} placeholder="Search notes and groups" class="pl-9" aria-label="Search notes" />
      </div>
      <Select.Root type="single" value={sortOrder} onValueChange={(value) => (sortOrder = value as SortOrder)}>
        <Select.Trigger class="w-full sm:w-44">{sortOrder === 'newest' ? 'Newest updated' : 'Oldest updated'}</Select.Trigger>
        <Select.Content>
          <Select.Item value="newest" label="Newest updated">Newest updated</Select.Item>
          <Select.Item value="oldest" label="Oldest updated">Oldest updated</Select.Item>
        </Select.Content>
      </Select.Root>
      {#if hasFilters}
        <Button variant="ghost" size="sm" class="col-span-2 justify-self-start sm:col-span-1" onclick={clearFilters}>
          <X class="size-4" /> Clear
        </Button>
      {/if}
    </div>

    <div class="mt-4">
      {#if loading}
        <LoadingState message="Decrypting notes…" />
      {:else if notes.length === 0}
        <EmptyState title="No notes yet" description="Create a short encrypted note when something needs a temporary place." />
      {:else if filteredNotes.length === 0}
        <EmptyState title="No matching notes" description="Clear filters or search for a different note." />
      {:else}
        <div class="grid gap-3 xl:grid-cols-2">
          {#each filteredNotes as item (item.record.id)}
            <Card.Root class="relative gap-0 py-0 {item.payload.pinned ? 'border-primary/40 bg-muted/40' : ''}">
              <button
                type="button"
                onclick={() => togglePinned(item)}
                aria-label={item.payload.pinned ? 'Unpin note' : 'Pin note'}
                aria-pressed={item.payload.pinned}
                class="bg-background hover:border-primary hover:text-primary absolute top-2.5 right-2.5 z-[2] grid size-8 place-items-center rounded-full border transition-colors {item.payload.pinned ? 'border-primary text-primary' : 'text-muted-foreground'}"
              >
                <Pin class="size-3.5 {item.payload.pinned ? 'fill-current' : ''}" />
              </button>
              <button
                type="button"
                onclick={() => openNote(item)}
                aria-label={item.payload.title ? `Open note: ${item.payload.title}` : 'Open note'}
                class="hover:bg-muted/40 grid w-full gap-3 rounded-xl p-4 text-left transition-colors"
              >
                <span class="grid min-w-0 gap-1.5">
                  {#if item.payload.title}
                    <span class="truncate pr-8 font-semibold">{item.payload.title}</span>
                  {/if}
                  <span class="line-clamp-5 text-sm leading-relaxed break-words whitespace-pre-wrap">{item.payload.text}</span>
                </span>
                <span class="text-muted-foreground flex flex-wrap items-center gap-2 text-xs">
                  <time datetime={item.payload.updatedAt}>{formatDate(item.payload.updatedAt)}</time>
                  {#each item.payload.groupIds as groupId (groupId)}
                    {@const group = groupById.get(groupId)}
                    {#if group}
                      <span
                        class="rounded-full border px-2 py-0.5 text-[0.6875rem] font-semibold"
                        style="border-color: color-mix(in srgb, {group.payload.color} 45%, transparent); background: color-mix(in srgb, {group.payload.color} 12%, transparent); color: var(--foreground)"
                      >
                        {group.payload.name}
                      </span>
                    {/if}
                  {/each}
                </span>
              </button>
            </Card.Root>
          {/each}
        </div>
      {/if}
    </div>
  </section>
</div>

<EntryModal
  open={noteFormOpen}
  title={editingNoteId ? 'Note details' : 'New note'}
  description={editingNoteId ? 'View, update, pin, or delete this encrypted note.' : 'Notes are encrypted before they leave this browser.'}
  onClose={closeNoteForm}
>
  <form
    class="grid gap-4"
    onsubmit={(event) => {
      event.preventDefault();
      saveNote();
    }}
  >
    <div class="grid gap-2">
      <Label for="note-title">Title</Label>
      <Input id="note-title" bind:value={noteForm.title} maxlength={120} />
    </div>

    <div class="grid gap-2">
      <Label for="note-text">Note</Label>
      <Textarea id="note-text" bind:value={noteForm.text} maxlength={noteTextMaxLength} required class="min-h-36" />
    </div>

    <Label for="note-pinned" class="flex items-center gap-2 font-normal">
      <Checkbox id="note-pinned" bind:checked={noteForm.pinned} />
      <Pin class="size-4" /> Pinned
    </Label>

    <div class="grid gap-2">
      <p class="text-sm font-medium">Groups</p>
      {#if groups.length === 0}
        <p class="text-muted-foreground text-sm">No groups yet. Create groups from the Notes page.</p>
      {:else}
        <div class="flex flex-wrap gap-3">
          {#each groups as group (group.record.id)}
            <Label class="flex items-center gap-2 font-normal">
              <Checkbox
                checked={noteForm.groupIds.includes(group.record.id)}
                onCheckedChange={() => toggleGroup(group.record.id)}
              />
              <span
                class="rounded-full border px-2 py-0.5 text-xs font-semibold"
                style="border-color: color-mix(in srgb, {group.payload.color} 45%, transparent); background: color-mix(in srgb, {group.payload.color} 12%, transparent)"
              >
                {group.payload.name}
              </span>
            </Label>
          {/each}
        </div>
      {/if}
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3">
      <span class="text-muted-foreground text-sm">{noteForm.text.length}/{noteTextMaxLength}</span>
      <div class="flex gap-2">
        {#if editingNoteId}
          <Button
            variant="outline"
            type="button"
            class="text-destructive hover:text-destructive"
            onclick={removeCurrentNote}
            disabled={saving}
          >
            <Trash2 class="size-4" /> Delete
          </Button>
        {/if}
        <Button type="submit" disabled={saving}>
          {saving ? 'Saving…' : editingNoteId ? 'Save changes' : 'Create'}
        </Button>
      </div>
    </div>
  </form>
</EntryModal>

<EntryModal
  open={groupFormOpen}
  title={editingGroupId ? 'Edit group' : 'New group'}
  description="Groups are encrypted labels that help you triage quick notes."
  onClose={closeGroupForm}
>
  <form
    class="grid gap-4"
    onsubmit={(event) => {
      event.preventDefault();
      saveGroup();
    }}
  >
    <div class="grid gap-2">
      <Label for="group-name">Name</Label>
      <Input id="group-name" bind:value={groupForm.name} required />
    </div>
    <div class="grid gap-2">
      <Label for="group-description">Description</Label>
      <Textarea id="group-description" bind:value={groupForm.description} />
    </div>
    <div class="grid gap-2">
      <Label for="group-color">Color</Label>
      <input
        id="group-color"
        class="border-input h-10 w-16 cursor-pointer rounded-md border bg-transparent p-1"
        type="color"
        bind:value={groupForm.color}
      />
    </div>
    <div class="flex justify-end">
      <Button type="submit" disabled={saving}>
        {saving ? 'Saving…' : editingGroupId ? 'Save' : 'Create'}
      </Button>
    </div>
  </form>
</EntryModal>

<EntryModal
  open={manageGroupsOpen}
  title="Manage groups"
  description="Create, rename, recolor, or delete the encrypted labels used to triage notes."
  onClose={() => (manageGroupsOpen = false)}
>
  <div class="grid gap-4">
    <Button onclick={manageCreateGroup}>
      <Plus class="size-4" /> New group
    </Button>

    {#if groups.length === 0}
      <p class="text-muted-foreground text-sm">No groups yet. Create one to start triaging notes.</p>
    {:else}
      <ul class="grid gap-1.5">
        {#each groups as group (group.record.id)}
          <li class="flex items-center gap-3 rounded-lg border px-3 py-2">
            <span class="size-3 shrink-0 rounded-full" style="background: {group.payload.color}"></span>
            <div class="min-w-0 flex-1">
              <p class="truncate text-sm font-medium">{group.payload.name}</p>
              {#if group.payload.description}
                <p class="text-muted-foreground truncate text-xs">{group.payload.description}</p>
              {/if}
            </div>
            <span class="text-muted-foreground shrink-0 text-xs font-semibold">{groupCount(group.record.id)}</span>
            <Button variant="ghost" size="icon" class="size-8" onclick={() => manageEditGroup(group)} aria-label="Edit group">
              <Pencil class="size-4" />
            </Button>
            <Button
              variant="ghost"
              size="icon"
              class="text-muted-foreground hover:text-destructive size-8"
              onclick={() => removeGroup(group)}
              aria-label="Delete group"
            >
              <Trash2 class="size-4" />
            </Button>
          </li>
        {/each}
      </ul>
    {/if}
  </div>
</EntryModal>
