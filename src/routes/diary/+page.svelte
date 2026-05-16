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
  import { Pencil, Plus, Save, Trash2 } from '@lucide/svelte';

  type DiaryItem = {
    record: EncryptedRecord;
    payload: DiaryPayload;
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
  let form = $state(emptyForm());
  let tagInput = $state('');
  let entries: DiaryItem[] = $state([]);

  function formatDate(value: string) {
    return new Intl.DateTimeFormat(undefined, {
      year: 'numeric',
      month: 'long',
      day: 'numeric'
    }).format(new Date(`${value}T00:00:00`));
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

    if (editingId) {
      await updateEncryptedRecord(editingId, 'diary', payload, dek);
    } else {
      await createEncryptedRecord('diary', payload, dek);
    }

    await loadEntries();
    closeForm();
    saving = false;
  }

  async function removeEntry(id: string) {
    if (!confirm('Delete this entry?')) return;
    await deleteEncryptedRecord(id);
    await loadEntries();
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
      <div class="diary-reader">
        {#each entries as item (item.record.id)}
          <article class="diary-entry">
            <div class="diary-entry__meta">
              <time datetime={item.payload.occurredAt}>{formatDate(item.payload.occurredAt)}</time>
              <span>{item.payload.language}</span>
              <span>{item.payload.wordCount} words</span>
            </div>

            <div class="diary-entry__header">
              <h2>{item.payload.title || 'Untitled'}</h2>
              <div class="diary-entry__actions" aria-label="Entry actions">
                <button class="focus-ring vault-btn-secondary" type="button" onclick={() => startEdit(item)}>
                  <Pencil size={15} />
                  Edit
                </button>
                <button
                  class="focus-ring vault-btn-danger"
                  type="button"
                  onclick={() => removeEntry(item.record.id)}
                  aria-label="Delete entry"
                >
                  <Trash2 size={16} />
                </button>
              </div>
            </div>

            {#if item.payload.tags.length}
              <div class="diary-entry__tags">
                {#each item.payload.tags as tag (tag)}
                  <span class="vault-tag">{tag}</span>
                {/each}
              </div>
            {/if}

            <div class="diary-entry__body">{item.payload.body}</div>
          </article>
        {/each}
      </div>
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
  .diary-reader {
    width: 100%;
    border-top: 1px solid var(--border);
  }

  .diary-entry {
    width: 100%;
    border-bottom: 1px solid var(--border);
    padding: 2.5rem 0;
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
    max-width: 46rem;
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
