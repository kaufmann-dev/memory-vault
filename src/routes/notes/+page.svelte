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
    updateEncryptedRecord
  } from '$lib/client/records';
  import { sessionDEK } from '$lib/stores/cryptoKey';
  import type { EncryptedRecord, NoteGroupPayload, NotePayload } from '$lib/types';
  import { get } from 'svelte/store';
  import { onMount } from 'svelte';
  import { Pencil, Pin, Plus, Search, Trash2, X } from '@lucide/svelte';

  type NoteItem = {
    record: EncryptedRecord;
    payload: NotePayload;
  };

  type GroupItem = {
    record: EncryptedRecord;
    payload: NoteGroupPayload;
  };

  type SortOrder = 'newest' | 'oldest';

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
  let locked = $state(false);
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
      locked = true;
      loading = false;
      return;
    }
    await loadNotes();
  });
</script>

<PageHeader title="Notes" description="Short encrypted notes for quick capture and cleanup.">
  <button class="focus-ring vault-btn-primary" onclick={openCreateNote} type="button">
    <Plus size={16} />
    New
  </button>
</PageHeader>

{#if locked}
  <VaultNotice />
{:else}
  <div class="notes-layout">
    <aside class="notes-groups" aria-label="Note groups">
      <div class="notes-groups__header">
        <h2>Groups</h2>
        <button class="focus-ring vault-btn-ghost" type="button" onclick={openCreateGroup} aria-label="New group">
          <Plus size={15} />
        </button>
      </div>

      <button class="focus-ring notes-group" class:notes-group--active={activeGroupId === 'all'} type="button" onclick={() => (activeGroupId = 'all')}>
        <span class="notes-group__dot" style="background: var(--foreground)"></span>
        <span>All notes</span>
        <strong>{notes.length}</strong>
      </button>
      <button class="focus-ring notes-group" class:notes-group--active={activeGroupId === 'ungrouped'} type="button" onclick={() => (activeGroupId = 'ungrouped')}>
        <span class="notes-group__dot" style="background: var(--border-strong)"></span>
        <span>Ungrouped</span>
        <strong>{ungroupedCount}</strong>
      </button>

      <div class="notes-groups__list">
        {#each groups as group (group.record.id)}
          <div class="notes-group-row">
            <button
              class="focus-ring notes-group"
              class:notes-group--active={activeGroupId === group.record.id}
              type="button"
              onclick={() => (activeGroupId = group.record.id)}
            >
              <span class="notes-group__dot" style={`background: ${group.payload.color}`}></span>
              <span>{group.payload.name}</span>
              <strong>{groupCount(group.record.id)}</strong>
            </button>
            <button class="focus-ring vault-btn-ghost" type="button" onclick={() => openEditGroup(group)} aria-label="Edit group">
              <Pencil size={14} />
            </button>
            <button class="focus-ring vault-btn-ghost" type="button" onclick={() => removeGroup(group)} aria-label="Delete group">
              <Trash2 size={14} />
            </button>
          </div>
        {/each}
      </div>
    </aside>

    <section class="notes-main">
      <div class="notes-tools" aria-label="Note filters">
        <label class="notes-search">
          <span class="sr-only">Search notes</span>
          <Search size={16} />
          <input bind:value={query} placeholder="Search notes and groups" />
        </label>

        <select class="focus-ring vault-input notes-sort" bind:value={sortOrder} aria-label="Sort notes">
          <option value="newest">Newest updated</option>
          <option value="oldest">Oldest updated</option>
        </select>

        {#if hasFilters}
          <button class="focus-ring vault-btn-ghost" type="button" onclick={clearFilters}>
            <X size={15} />
            Clear
          </button>
        {/if}
      </div>

      {#if loading}
        <p class="text-sm" style="color: var(--muted)">Decrypting notes...</p>
      {:else if notes.length === 0}
        <EmptyState title="No notes yet" description="Create a short encrypted note when something needs a temporary place." />
      {:else if filteredNotes.length === 0}
        <EmptyState title="No matching notes" description="Clear filters or search for a different note." />
      {:else}
        <div class="notes-list">
          {#each filteredNotes as item (item.record.id)}
            <article class="note-card" class:note-card--pinned={item.payload.pinned}>
              <button
                class="focus-ring note-card__pin"
                class:note-card__pin--active={item.payload.pinned}
                type="button"
                onclick={() => togglePinned(item)}
                aria-label={item.payload.pinned ? 'Unpin note' : 'Pin note'}
                aria-pressed={item.payload.pinned}
                title={item.payload.pinned ? 'Unpin note' : 'Pin note'}
              >
                <Pin size={15} />
              </button>
              <button
                class="focus-ring note-card__open"
                type="button"
                onclick={() => openNote(item)}
                aria-label={item.payload.title ? `Open note: ${item.payload.title}` : 'Open note'}
              >
                <span class="note-card__content">
                  {#if item.payload.title}
                    <span class="note-card__title">{item.payload.title}</span>
                  {/if}
                  <span class="note-card__body">{item.payload.text}</span>
                </span>
                <span class="note-card__meta">
                  <time datetime={item.payload.updatedAt}>{formatDate(item.payload.updatedAt)}</time>
                  <span class="note-card__groups">
                    {#each item.payload.groupIds as groupId (groupId)}
                      {@const group = groupById.get(groupId)}
                      {#if group}
                        <span class="note-chip" style={`--chip-color: ${group.payload.color}`}>{group.payload.name}</span>
                      {/if}
                    {/each}
                  </span>
                </span>
              </button>
            </article>
          {/each}
        </div>
      {/if}
    </section>
  </div>

  <EntryModal
    open={noteFormOpen}
    title={editingNoteId ? 'Note details' : 'New note'}
    description={editingNoteId ? 'View, update, pin, or delete this encrypted note.' : 'Notes are encrypted before they leave this browser.'}
    onClose={closeNoteForm}
  >
    <form
      class="space-y-4"
      onsubmit={(event) => {
        event.preventDefault();
        saveNote();
      }}
    >
      <label class="block text-sm font-medium">
        Title <span style="color: var(--muted); font-weight: 500">(optional)</span>
        <input class="focus-ring vault-input mt-1.5" bind:value={noteForm.title} maxlength="120" />
      </label>

      <label class="block text-sm font-medium">
        Note
        <textarea class="focus-ring vault-input mt-1.5 min-h-36" bind:value={noteForm.text} maxlength="20000" required></textarea>
      </label>

      <label class="note-pin-toggle">
        <input type="checkbox" bind:checked={noteForm.pinned} />
        <span><Pin size={15} /> Pinned</span>
      </label>

      <div>
        <p class="text-sm font-medium" style="color: var(--foreground)">Groups</p>
        {#if groups.length === 0}
          <p class="mt-1 text-sm" style="color: var(--muted)">No groups yet. Create groups from the Notes page.</p>
        {:else}
          <div class="note-form-groups">
            {#each groups as group (group.record.id)}
              <label class="note-form-group">
                <input
                  type="checkbox"
                  checked={noteForm.groupIds.includes(group.record.id)}
                  onchange={() => toggleGroup(group.record.id)}
                />
                <span style={`--chip-color: ${group.payload.color}`}>{group.payload.name}</span>
              </label>
            {/each}
          </div>
        {/if}
      </div>

      <div class="note-form-footer">
        <span class="text-sm" style="color: var(--muted)">{noteForm.text.length}/20000</span>
        <div class="note-form-actions">
          {#if editingNoteId}
            <button class="focus-ring vault-btn-danger" type="button" onclick={removeCurrentNote} disabled={saving}>
              <Trash2 size={15} />
              Delete
            </button>
          {/if}
          <button class="focus-ring vault-btn-primary" type="submit" disabled={saving}>
            {saving ? 'Saving...' : editingNoteId ? 'Save changes' : 'Create'}
          </button>
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
      class="space-y-4"
      onsubmit={(event) => {
        event.preventDefault();
        saveGroup();
      }}
    >
      <label class="block text-sm font-medium">
        Name
        <input class="focus-ring vault-input mt-1.5" bind:value={groupForm.name} required />
      </label>
      <label class="block text-sm font-medium">
        Description
        <textarea class="focus-ring vault-input mt-1.5" bind:value={groupForm.description}></textarea>
      </label>
      <label class="block text-sm font-medium">
        Color
        <input class="focus-ring mt-1.5 h-10 w-16 rounded-lg border-0 p-1" type="color" bind:value={groupForm.color} />
      </label>
      <div class="flex justify-end">
        <button class="focus-ring vault-btn-primary" type="submit" disabled={saving}>
          {saving ? 'Saving...' : editingGroupId ? 'Save' : 'Create'}
        </button>
      </div>
    </form>
  </EntryModal>
{/if}

<style>
  .notes-layout {
    display: grid;
    grid-template-columns: minmax(14rem, 18rem) minmax(0, 1fr);
    gap: 1.5rem;
    align-items: start;
  }

  .notes-groups {
    position: sticky;
    top: 1rem;
    display: grid;
    gap: 0.4rem;
  }

  .notes-groups__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 0.35rem;
  }

  .notes-groups__header h2 {
    color: var(--foreground);
    font-size: 0.8125rem;
    font-weight: 700;
    line-height: 1.25rem;
  }

  .notes-groups__list {
    display: grid;
    gap: 0.4rem;
  }

  .notes-group-row {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 2.25rem 2.25rem;
    gap: 0.25rem;
  }

  .notes-group {
    display: grid;
    grid-template-columns: 0.625rem minmax(0, 1fr) auto;
    gap: 0.5rem;
    align-items: center;
    width: 100%;
    min-height: 2.25rem;
    border: 1px solid transparent;
    border-radius: 8px;
    padding: 0.5rem 0.625rem;
    color: var(--foreground);
    background: transparent;
    text-align: left;
    font-size: 0.875rem;
    line-height: 1.2;
  }

  .notes-group:hover,
  .notes-group--active {
    border-color: var(--border);
    background: var(--accent-light);
  }

  .notes-group span:not(.notes-group__dot) {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .notes-group strong {
    color: var(--muted);
    font-size: 0.75rem;
    font-weight: 600;
  }

  .notes-group__dot {
    width: 0.625rem;
    height: 0.625rem;
    border-radius: 999px;
  }

  .notes-main {
    min-width: 0;
  }

  .notes-tools {
    position: sticky;
    top: 0;
    z-index: 5;
    display: grid;
    grid-template-columns: minmax(12rem, 1fr) 11rem auto;
    gap: 0.75rem;
    align-items: center;
    padding: 0.75rem 0;
    background: var(--background);
    border-top: 1px solid var(--border);
    border-bottom: 1px solid var(--border);
  }

  .notes-search {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    width: 100%;
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 0.5rem 0.75rem;
    color: var(--muted);
    background: var(--background);
  }

  .notes-search:focus-within {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px var(--ring);
  }

  .notes-search input {
    width: 100%;
    min-width: 0;
    border: 0;
    outline: 0;
    background: transparent;
    color: var(--foreground);
    font-size: 0.875rem;
    line-height: 1.25rem;
  }

  .notes-sort {
    min-height: 2.375rem;
  }

  .notes-list {
    display: grid;
    gap: 0.65rem;
    align-items: start;
    margin-top: 1rem;
  }

  .note-card {
    position: relative;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: var(--background);
  }

  .note-card--pinned {
    border-color: color-mix(in srgb, var(--accent) 50%, var(--border));
    background: var(--accent-light);
  }

  .note-card__open {
    display: grid;
    gap: 0.85rem;
    width: 100%;
    border: 0;
    border-radius: 8px;
    padding: 1rem;
    background: transparent;
    color: inherit;
    text-align: left;
  }

  .note-card__open:hover {
    background: color-mix(in srgb, var(--accent-light) 72%, transparent);
  }

  .note-card__content {
    display: grid;
    gap: 0.45rem;
    min-width: 0;
  }

  .note-card__title {
    display: block;
    max-width: calc(100% - 2rem);
    overflow: hidden;
    color: var(--foreground);
    font-size: 0.9375rem;
    font-weight: 700;
    line-height: 1.3;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .note-card__body {
    display: -webkit-box;
    max-width: 100%;
    overflow: hidden;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 5;
    line-clamp: 5;
    color: var(--foreground);
    font-size: 0.9375rem;
    line-height: 1.55;
    overflow-wrap: anywhere;
    white-space: pre-wrap;
  }

  .note-card__meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    align-items: center;
    color: var(--muted);
    font-size: 0.75rem;
    line-height: 1rem;
  }

  .note-card__groups {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
  }

  .note-card__pin {
    position: absolute;
    top: 0.65rem;
    right: 0.65rem;
    z-index: 2;
    display: grid;
    width: 2rem;
    height: 2rem;
    place-items: center;
    border: 1px solid var(--border);
    border-radius: 999px;
    color: var(--muted);
    background: var(--background);
    opacity: 0.88;
    transition:
      border-color 0.15s ease,
      color 0.15s ease,
      opacity 0.15s ease;
  }

  .note-card__pin:hover,
  .note-card__pin--active {
    border-color: var(--accent);
    color: var(--accent);
    opacity: 1;
  }

  .note-card__pin--active :global(svg) {
    fill: currentColor;
  }

  .note-chip,
  .note-form-group span {
    border: 1px solid color-mix(in srgb, var(--chip-color) 45%, transparent);
    border-radius: 999px;
    padding: 0.125rem 0.45rem;
    color: var(--foreground);
    background: color-mix(in srgb, var(--chip-color) 11%, transparent);
    font-size: 0.75rem;
    font-weight: 600;
    line-height: 1rem;
  }

  .note-form-groups {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-top: 0.75rem;
  }

  .note-form-group {
    display: inline-flex;
    gap: 0.35rem;
    align-items: center;
  }

  .note-pin-toggle {
    display: inline-flex;
    gap: 0.45rem;
    align-items: center;
    color: var(--foreground);
    font-size: 0.875rem;
    font-weight: 600;
  }

  .note-pin-toggle span {
    display: inline-flex;
    gap: 0.35rem;
    align-items: center;
  }

  .note-form-footer {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    align-items: center;
    justify-content: space-between;
  }

  .note-form-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    justify-content: flex-end;
  }

  @media (min-width: 1100px) {
    .notes-list {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }
  }

  @media (max-width: 760px) {
    .notes-layout {
      grid-template-columns: 1fr;
    }

    .notes-groups,
    .notes-tools {
      position: static;
    }

    .notes-tools {
      grid-template-columns: 1fr;
    }

    .note-form-footer,
    .note-form-actions {
      align-items: stretch;
      flex-direction: column;
    }

    .note-form-actions :global(button) {
      justify-content: center;
    }
  }
</style>
