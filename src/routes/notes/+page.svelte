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
  import { Pencil, Plus, Search, Trash2, X } from '@lucide/svelte';

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
  const emptyNote = (): NotePayload => ({ text: '', groupIds: [], createdAt: nowIso(), updatedAt: nowIso() });
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
        return `${item.payload.text} ${groupText}`.toLowerCase().includes(normalizedQuery);
      })
      .sort((a, b) => {
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

  function openEditNote(item: NoteItem) {
    editingNoteId = item.record.id;
    noteForm = {
      ...item.payload,
      groupIds: [...item.payload.groupIds]
    };
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

  async function loadNotes() {
    if (!dek) return;
    loading = true;
    const [noteRecords, groupRecords] = await Promise.all([
      fetchEncryptedRecords('note'),
      fetchEncryptedRecords('note_group')
    ]);
    groups = (await decryptRecords<NoteGroupPayload>(groupRecords, dek)).sort((a, b) =>
      a.payload.name.localeCompare(b.payload.name)
    );
    notes = await decryptRecords<NotePayload>(noteRecords, dek);
    loading = false;
  }

  async function saveNote() {
    if (!dek || saving || !noteForm.text.trim()) return;
    saving = true;
    try {
      const timestamp = nowIso();
      const payload: NotePayload = {
        text: noteForm.text.trim(),
        groupIds: noteForm.groupIds.filter((groupId) => groupById.has(groupId)),
        createdAt: editingNoteId ? noteForm.createdAt : timestamp,
        updatedAt: timestamp
      };

      if (editingNoteId) {
        await updateEncryptedRecord(editingNoteId, 'note', payload, dek);
      } else {
        await createEncryptedRecord('note', payload, dek);
      }

      closeNoteForm();
      await loadNotes();
    } finally {
      saving = false;
    }
  }

  async function removeNote(item: NoteItem) {
    if (!confirm('Delete this note?')) return;
    await deleteEncryptedRecord(item.record.id);
    await loadNotes();
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
        await updateEncryptedRecord(editingGroupId, 'note_group', payload, dek);
      } else {
        await createEncryptedRecord('note_group', payload, dek);
      }

      closeGroupForm();
      await loadNotes();
    } finally {
      saving = false;
    }
  }

  async function removeGroup(item: GroupItem) {
    if (!dek || !confirm('Delete this group? Notes in it will become ungrouped unless they have other groups.')) return;
    const changedNotes = notes.filter((note) => note.payload.groupIds.includes(item.record.id));
    await Promise.all(
      changedNotes.map((note) =>
        updateEncryptedRecord(
          note.record.id,
          'note',
          {
            ...note.payload,
            groupIds: note.payload.groupIds.filter((groupId) => groupId !== item.record.id),
            updatedAt: nowIso()
          },
          dek
        )
      )
    );
    await deleteEncryptedRecord(item.record.id);
    if (activeGroupId === item.record.id) activeGroupId = 'all';
    await loadNotes();
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
            <article class="note-card">
              <div class="note-card__body">{item.payload.text}</div>
              <div class="note-card__meta">
                <time datetime={item.payload.updatedAt}>{formatDate(item.payload.updatedAt)}</time>
                <div class="note-card__groups">
                  {#each item.payload.groupIds as groupId (groupId)}
                    {@const group = groupById.get(groupId)}
                    {#if group}
                      <span class="note-chip" style={`--chip-color: ${group.payload.color}`}>{group.payload.name}</span>
                    {/if}
                  {/each}
                </div>
              </div>
              <div class="note-card__actions">
                <button class="focus-ring vault-btn-secondary" type="button" onclick={() => openEditNote(item)}>
                  <Pencil size={15} />
                  Edit
                </button>
                <button class="focus-ring vault-btn-danger" type="button" onclick={() => removeNote(item)} aria-label="Delete note">
                  <Trash2 size={15} />
                </button>
              </div>
            </article>
          {/each}
        </div>
      {/if}
    </section>
  </div>

  <EntryModal
    open={noteFormOpen}
    title={editingNoteId ? 'Edit note' : 'New note'}
    description="Keep it short. Notes are encrypted before they leave this browser."
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
        Note
        <textarea class="focus-ring vault-input mt-1.5 min-h-36" bind:value={noteForm.text} maxlength="1200" required></textarea>
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

      <div class="flex items-center justify-between gap-3">
        <span class="text-sm" style="color: var(--muted)">{noteForm.text.length}/1200</span>
        <button class="focus-ring vault-btn-primary" type="submit" disabled={saving}>
          {saving ? 'Saving...' : 'Save'}
        </button>
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
    margin-top: 1rem;
  }

  .note-card {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 0.75rem 1rem;
    align-items: start;
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 1rem;
    background: var(--background);
  }

  .note-card__body {
    white-space: pre-wrap;
    color: var(--foreground);
    font-size: 0.9375rem;
    line-height: 1.55;
    overflow-wrap: anywhere;
  }

  .note-card__meta {
    display: flex;
    grid-column: 1 / 2;
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

  .note-card__actions {
    display: flex;
    grid-column: 2 / 3;
    grid-row: 1 / 3;
    gap: 0.4rem;
    opacity: 0.72;
    transition: opacity 0.15s ease;
  }

  .note-card:hover .note-card__actions,
  .note-card:focus-within .note-card__actions {
    opacity: 1;
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

    .note-card {
      grid-template-columns: 1fr;
    }

    .note-card__actions,
    .note-card__meta {
      grid-column: auto;
      grid-row: auto;
    }

    .note-card__actions {
      opacity: 1;
    }
  }
</style>
