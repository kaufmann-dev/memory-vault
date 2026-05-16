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
    updateEncryptedRecord,
    wordCount
  } from '$lib/client/records';
  import { sessionDEK } from '$lib/stores/cryptoKey';
  import type { DiaryPayload, EncryptedRecord } from '$lib/types';
  import { goto } from '$app/navigation';
  import { get } from 'svelte/store';
  import { onMount } from 'svelte';
  import { Pencil, Plus, Save, Search, Trash2, X } from '@lucide/svelte';

  type DiaryItem = {
    record: EncryptedRecord;
    payload: DiaryPayload;
  };

  type SortOrder = 'newest' | 'oldest';

  type DiaryGroup = {
    key: string;
    label: string;
    items: DiaryItem[];
  };

  const emptyForm = (): DiaryPayload => ({
    title: '',
    body: '',
    occurredAt: new Date().toISOString().slice(0, 10),
    language: 'English',
    tags: [],
    wordCount: 0
  });

  let dek: CryptoKey | null = null;
  let locked = $state(false);
  let loading = $state(true);
  let saving = $state(false);
  let formOpen = $state(false);
  let editingId: string | null = $state(null);
  let selectedEntryId: string | null = $state(null);
  let query = $state('');
  let fromDate = $state('');
  let toDate = $state('');
  let sortOrder: SortOrder = $state('newest');
  let form = $state(emptyForm());
  let tagInput = $state('');
  let entries: DiaryItem[] = $state([]);

  let filteredEntries = $derived.by(() => {
    const normalizedQuery = query.trim().toLowerCase();

    return entries
      .filter((item) => {
        const occurredAt = item.payload.occurredAt;
        if (fromDate && occurredAt < fromDate) return false;
        if (toDate && occurredAt > toDate) return false;

        if (!normalizedQuery) return true;

        const searchable = [
          item.payload.title,
          item.payload.body,
          item.payload.language,
          item.payload.tags.join(' ')
        ]
          .join(' ')
          .toLowerCase();

        return searchable.includes(normalizedQuery);
      })
      .sort((a, b) => {
        const direction = sortOrder === 'newest' ? -1 : 1;
        const byDate = a.payload.occurredAt.localeCompare(b.payload.occurredAt);
        if (byDate !== 0) return byDate * direction;
        return a.record.createdAt.localeCompare(b.record.createdAt) * direction;
      });
  });

  let groupedEntries: DiaryGroup[] = $derived.by(() => {
    const groups: DiaryGroup[] = [];

    for (const item of filteredEntries) {
      const key = item.payload.occurredAt.slice(0, 7);
      const lastGroup = groups.at(-1);

      if (lastGroup?.key === key) {
        lastGroup.items.push(item);
      } else {
        groups.push({
          key,
          label: formatMonth(key),
          items: [item]
        });
      }
    }

    return groups;
  });

  let selectedEntry = $derived(
    filteredEntries.find((item) => item.record.id === selectedEntryId) ?? filteredEntries[0] ?? null
  );

  let hasFilters = $derived(Boolean(query.trim() || fromDate || toDate || sortOrder !== 'newest'));

  function formatDate(value: string) {
    return new Intl.DateTimeFormat(undefined, {
      year: 'numeric',
      month: 'long',
      day: 'numeric'
    }).format(new Date(`${value}T00:00:00`));
  }

  function formatShortDate(value: string) {
    return new Intl.DateTimeFormat(undefined, {
      month: 'short',
      day: 'numeric'
    }).format(new Date(`${value}T00:00:00`));
  }

  function formatMonth(value: string) {
    return new Intl.DateTimeFormat(undefined, {
      year: 'numeric',
      month: 'long'
    }).format(new Date(`${value}-01T00:00:00`));
  }

  function excerpt(value: string) {
    const compact = value.replace(/\s+/g, ' ').trim();
    if (compact.length <= 150) return compact;
    return `${compact.slice(0, 150).trim()}...`;
  }

  async function loadEntries() {
    if (!dek) return;
    loading = true;
    const records = await fetchEncryptedRecords('diary');
    entries = (await decryptRecords<DiaryPayload>(records, dek)).sort((a, b) =>
      b.payload.occurredAt.localeCompare(a.payload.occurredAt)
    );
    loading = false;
  }

  function startCreate() {
    editingId = null;
    form = emptyForm();
    tagInput = '';
  }

  function openCreate() {
    startCreate();
    formOpen = true;
  }

  function startEdit(item: DiaryItem) {
    editingId = item.record.id;
    form = {
      ...item.payload,
      tags: [...item.payload.tags]
    };
    tagInput = item.payload.tags.join(', ');
    formOpen = true;
  }

  function clearFilters() {
    query = '';
    fromDate = '';
    toDate = '';
    sortOrder = 'newest';
  }

  function closeForm() {
    formOpen = false;
    startCreate();
  }

  async function saveEntry() {
    if (!dek || saving) return;
    saving = true;
    const payload = {
      ...form,
      title: form.title.trim(),
      body: form.body.trim(),
      wordCount: wordCount(form.body),
      tags: tagInput
        .split(',')
        .map((tag) => tag.trim())
        .filter(Boolean)
    };

    let savedId = editingId;

    if (savedId) {
      await updateEncryptedRecord(savedId, 'diary', payload, dek);
    } else {
      savedId = (await createEncryptedRecord('diary', payload, dek)).id;
    }

    await loadEntries();
    selectedEntryId = savedId;
    closeForm();
    saving = false;
  }

  async function removeEntry(id: string) {
    if (!confirm('Delete this entry?')) return;
    await deleteEncryptedRecord(id);
    await loadEntries();
    if (selectedEntryId === id) selectedEntryId = null;
    if (editingId === id) startCreate();
  }

  onMount(async () => {
    dek = get(sessionDEK);
    if (!dek) {
      locked = true;
      loading = false;
      await goto('/login');
      return;
    }
    await loadEntries();
  });
</script>

<PageHeader title="Diary" description="One encrypted structure for diary entries, articles, notes, and ramblings.">
  <button class="focus-ring vault-btn-primary" onclick={openCreate} type="button">
    <Plus size={16} />
    New
  </button>
</PageHeader>

{#if locked}
  <VaultNotice />
{:else}
  <section class="w-full">
    {#if loading}
      <p class="text-sm" style="color: var(--muted)">Decrypting entries...</p>
    {:else if entries.length === 0}
      <EmptyState title="No diary entries yet" description="Create the first encrypted entry when you are ready." />
    {:else}
      <div class="diary-tools" aria-label="Diary navigation tools">
        <label class="diary-search">
          <span class="sr-only">Search diary entries</span>
          <Search size={16} />
          <input bind:value={query} placeholder="Search entries, tags, languages" />
        </label>

        <div class="diary-filters">
          <label>
            <span>From</span>
            <input class="focus-ring vault-input" type="date" bind:value={fromDate} />
          </label>
          <label>
            <span>To</span>
            <input class="focus-ring vault-input" type="date" bind:value={toDate} />
          </label>
          <label>
            <span>Sort</span>
            <select class="focus-ring vault-input" bind:value={sortOrder}>
              <option value="newest">Newest first</option>
              <option value="oldest">Oldest first</option>
            </select>
          </label>
        </div>

        <div class="diary-results">
          <span>{filteredEntries.length} of {entries.length} entries</span>
          {#if hasFilters}
            <button class="focus-ring vault-btn-ghost" type="button" onclick={clearFilters}>
              <X size={15} />
              Clear
            </button>
          {/if}
        </div>
      </div>

      {#if filteredEntries.length === 0}
        <EmptyState title="No matching entries" description="Clear filters or broaden the search to find more diary entries." />
      {:else}
        <div class="diary-browser">
          <aside class="diary-index" aria-label="Diary entry index">
            {#each groupedEntries as group (group.key)}
              <section class="diary-month">
                <div class="diary-month__header">
                  <span>{group.label}</span>
                  <span>{group.items.length}</span>
                </div>

                <div class="diary-month__entries">
                  {#each group.items as item (item.record.id)}
                    <button
                      class="focus-ring diary-index-row"
                      class:diary-index-row--active={selectedEntry?.record.id === item.record.id}
                      type="button"
                      onclick={() => (selectedEntryId = item.record.id)}
                      aria-current={selectedEntry?.record.id === item.record.id ? 'true' : undefined}
                    >
                      <span class="diary-index-row__date">{formatShortDate(item.payload.occurredAt)}</span>
                      <span class="diary-index-row__content">
                        <strong>{item.payload.title || 'Untitled'}</strong>
                        <span>{excerpt(item.payload.body) || 'No body text'}</span>
                      </span>
                    </button>
                  {/each}
                </div>
              </section>
            {/each}
          </aside>

          {#if selectedEntry}
            <article class="diary-entry">
              <div class="diary-entry__meta">
                <time datetime={selectedEntry.payload.occurredAt}>{formatDate(selectedEntry.payload.occurredAt)}</time>
                <span>{selectedEntry.payload.language}</span>
                <span>{selectedEntry.payload.wordCount} words</span>
              </div>

              <div class="diary-entry__header">
                <h2>{selectedEntry.payload.title || 'Untitled'}</h2>
                <div class="diary-entry__actions" aria-label="Entry actions">
                  <button class="focus-ring vault-btn-secondary" type="button" onclick={() => startEdit(selectedEntry)}>
                    <Pencil size={15} />
                    Edit
                  </button>
                  <button
                    class="focus-ring vault-btn-danger"
                    type="button"
                    onclick={() => removeEntry(selectedEntry.record.id)}
                    aria-label="Delete entry"
                  >
                    <Trash2 size={16} />
                  </button>
                </div>
              </div>

              {#if selectedEntry.payload.tags.length}
                <div class="diary-entry__tags">
                  {#each selectedEntry.payload.tags as tag (tag)}
                    <span class="vault-tag">{tag}</span>
                  {/each}
                </div>
              {/if}

              <div class="diary-entry__body">{selectedEntry.payload.body}</div>
            </article>
          {/if}
        </div>
      {/if}
    {/if}
  </section>

  <EntryModal
    open={formOpen}
    title={editingId ? 'Edit entry' : 'New entry'}
    description="Write privately. The content is encrypted before it leaves this browser."
    onClose={closeForm}
  >
    <form
      class="space-y-4"
      onsubmit={(event) => {
        event.preventDefault();
        saveEntry();
      }}
    >
      <label class="block text-sm font-medium">
        Title
        <input class="focus-ring vault-input mt-1.5" bind:value={form.title} />
      </label>

      <div class="grid grid-cols-2 gap-3">
        <label class="block text-sm font-medium">
          Date
          <input class="focus-ring vault-input mt-1.5" type="date" bind:value={form.occurredAt} required />
        </label>
        <label class="block text-sm font-medium">
          Language
          <select class="focus-ring vault-input mt-1.5" bind:value={form.language}>
            <option>English</option>
            <option>German</option>
            <option>Other</option>
          </select>
        </label>
      </div>

      <label class="block text-sm font-medium">
        Tags
        <input class="focus-ring vault-input mt-1.5" bind:value={tagInput} placeholder="comma, separated" />
      </label>

      <label class="block text-sm font-medium">
        Body
        <textarea class="focus-ring vault-input mt-1.5 min-h-64 leading-relaxed" bind:value={form.body} required></textarea>
      </label>

      <div class="flex items-center justify-between">
        <span class="text-sm" style="color: var(--muted)">{wordCount(form.body)} words</span>
        <button class="focus-ring vault-btn-primary" type="submit" disabled={saving}>
          <Save size={16} />
          {saving ? 'Saving...' : 'Save'}
        </button>
      </div>
    </form>
  </EntryModal>
{/if}

<style>
  .diary-tools {
    position: sticky;
    top: 0;
    z-index: 5;
    display: grid;
    grid-template-columns: minmax(16rem, 1fr) auto auto;
    gap: 0.75rem;
    align-items: end;
    padding: 0.75rem 0;
    background: var(--background);
    border-top: 1px solid var(--border);
    border-bottom: 1px solid var(--border);
  }

  .diary-search {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    width: 100%;
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 0.5rem 0.75rem;
    color: var(--muted);
    background: var(--background);
    transition: all 0.15s ease;
  }

  .diary-search:focus-within {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px var(--ring);
  }

  .diary-search input {
    width: 100%;
    min-width: 0;
    border: 0;
    outline: 0;
    background: transparent;
    color: var(--foreground);
    font-size: 0.875rem;
    line-height: 1.25rem;
  }

  .diary-search input::placeholder {
    color: #A3A3A3;
  }

  .diary-filters {
    display: flex;
    gap: 0.5rem;
    align-items: end;
  }

  .diary-filters label {
    display: grid;
    gap: 0.25rem;
    min-width: 8.5rem;
    color: var(--muted);
    font-size: 0.75rem;
    font-weight: 500;
    line-height: 1rem;
  }

  .diary-results {
    display: flex;
    gap: 0.5rem;
    align-items: center;
    justify-content: flex-end;
    min-height: 2.375rem;
    color: var(--muted);
    font-size: 0.8125rem;
    line-height: 1.25rem;
    white-space: nowrap;
  }

  .diary-browser {
    display: grid;
    grid-template-columns: minmax(18rem, 24rem) minmax(0, 1fr);
    min-height: 32rem;
    border-bottom: 1px solid var(--border);
  }

  .diary-index {
    max-height: calc(100vh - 10rem);
    overflow: auto;
    border-right: 1px solid var(--border);
  }

  .diary-month {
    border-bottom: 1px solid var(--border);
  }

  .diary-month__header {
    position: sticky;
    top: 0;
    z-index: 1;
    display: flex;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.75rem 1rem;
    color: var(--muted);
    background: var(--surface);
    border-bottom: 1px solid var(--border);
    font-size: 0.6875rem;
    font-weight: 600;
    line-height: 1rem;
    letter-spacing: 0.05em;
    text-transform: uppercase;
  }

  .diary-month__entries {
    display: grid;
  }

  .diary-index-row {
    display: grid;
    grid-template-columns: 3.75rem minmax(0, 1fr);
    gap: 0.75rem;
    width: 100%;
    border: 0;
    border-left: 2px solid transparent;
    border-bottom: 1px solid var(--border);
    padding: 0.875rem 1rem 0.875rem calc(1rem - 2px);
    text-align: left;
    background: var(--background);
    transition: background 0.15s ease, border-color 0.15s ease;
  }

  .diary-index-row:hover {
    background: var(--surface);
  }

  .diary-index-row--active {
    border-left-color: var(--accent);
    background: var(--accent-light);
  }

  .diary-index-row__date {
    color: var(--muted);
    font-size: 0.75rem;
    font-weight: 500;
    line-height: 1rem;
  }

  .diary-index-row__content {
    display: grid;
    gap: 0.3rem;
    min-width: 0;
  }

  .diary-index-row__content strong {
    color: var(--foreground);
    font-size: 0.875rem;
    font-weight: 600;
    line-height: 1.2;
    overflow-wrap: anywhere;
  }

  .diary-index-row__content span {
    display: -webkit-box;
    overflow: hidden;
    color: var(--muted);
    font-size: 0.8125rem;
    line-height: 1.45;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
    line-clamp: 2;
  }

  .diary-entry {
    width: 100%;
    padding: 2rem 0 3rem 2rem;
  }

  .diary-entry__meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    align-items: center;
    font-size: 0.8125rem;
    line-height: 1.25rem;
    color: var(--muted);
  }

  .diary-entry__meta span::before {
    content: "/";
    margin-right: 0.5rem;
    color: var(--border-strong);
  }

  .diary-entry__header {
    display: flex;
    gap: 1.5rem;
    align-items: flex-start;
    justify-content: space-between;
    margin-top: 0.65rem;
  }

  .diary-entry__header h2 {
    max-width: 42rem;
    margin: 0;
    color: var(--foreground);
    font-size: clamp(1.5rem, 1.1rem + 1vw, 2.25rem);
    font-weight: 700;
    line-height: 1.12;
    overflow-wrap: anywhere;
  }

  .diary-entry__actions {
    display: flex;
    flex-shrink: 0;
    gap: 0.5rem;
    opacity: 0.72;
    transition: opacity 0.15s ease;
  }

  .diary-entry:hover .diary-entry__actions,
  .diary-entry:focus-within .diary-entry__actions {
    opacity: 1;
  }

  .diary-entry__tags {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-top: 1.25rem;
  }

  .diary-entry__body {
    width: 100%;
    max-width: 74ch;
    margin-top: 1.4rem;
    white-space: pre-wrap;
    color: var(--foreground);
    font-size: 1.0625rem;
    line-height: 1.8;
    overflow-wrap: anywhere;
  }

  @media (max-width: 640px) {
    .diary-tools {
      position: static;
      grid-template-columns: 1fr;
      align-items: stretch;
    }

    .diary-filters {
      display: grid;
      grid-template-columns: 1fr;
    }

    .diary-results {
      justify-content: space-between;
    }

    .diary-browser {
      grid-template-columns: 1fr;
    }

    .diary-index {
      max-height: none;
      border-right: 0;
      border-bottom: 1px solid var(--border);
    }

    .diary-entry {
      padding: 2rem 0;
    }

    .diary-entry__header {
      flex-direction: column;
      gap: 1rem;
    }

    .diary-entry__actions {
      width: 100%;
      opacity: 1;
    }

    .diary-entry__actions :global(.vault-btn-secondary) {
      flex: 1;
    }
  }
</style>
