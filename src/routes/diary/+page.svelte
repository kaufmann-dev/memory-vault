<script lang="ts">
  import EmptyState from '$lib/components/EmptyState.svelte';
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
  import { Plus, Save, Trash2, X } from '@lucide/svelte';

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
  let locked = false;
  let loading = true;
  let saving = false;
  let editingId: string | null = null;
  let form = emptyForm();
  let tagInput = '';
  let entries: DiaryItem[] = [];

  $: form.wordCount = wordCount(form.body);

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

  function startEdit(item: DiaryItem) {
    editingId = item.record.id;
    form = {
      ...item.payload,
      tags: [...item.payload.tags]
    };
    tagInput = item.payload.tags.join(', ');
  }

  async function saveEntry() {
    if (!dek || saving) return;
    saving = true;
    const payload = {
      ...form,
      title: form.title.trim(),
      body: form.body.trim(),
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
    startCreate();
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
  <button class="focus-ring vault-btn-primary" on:click={startCreate} type="button">
    <Plus size={16} />
    New
  </button>
</PageHeader>

{#if locked}
  <VaultNotice />
{:else}
  <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_380px]">
    <section class="space-y-4">
      {#if loading}
        <p class="text-sm" style="color: var(--muted)">Decrypting entries...</p>
      {:else if entries.length === 0}
        <EmptyState title="No diary entries yet" description="Create the first encrypted entry when you are ready." />
      {:else}
        {#each entries as item}
          <article class="vault-card p-5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
              <div>
                <h2 class="text-lg font-semibold" style="color: var(--foreground)">{item.payload.title || 'Untitled'}</h2>
                <p class="mt-1 text-sm" style="color: var(--muted)">
                  {item.payload.occurredAt} · {item.payload.language} · {item.payload.wordCount} words
                </p>
              </div>
              <div class="flex gap-2">
                <button class="focus-ring vault-btn-secondary" type="button" on:click={() => startEdit(item)}>Edit</button>
                <button class="focus-ring vault-btn-danger" type="button" on:click={() => removeEntry(item.record.id)}>
                  <Trash2 size={16} />
                </button>
              </div>
            </div>
            {#if item.payload.tags.length}
              <div class="mt-3 flex flex-wrap gap-2">
                {#each item.payload.tags as tag}
                  <span class="vault-tag">{tag}</span>
                {/each}
              </div>
            {/if}
            <p class="mt-4 whitespace-pre-wrap text-sm leading-relaxed" style="color: var(--foreground)">{item.payload.body}</p>
          </article>
        {/each}
      {/if}
    </section>

    <aside class="vault-card p-5">
      <div class="mb-4 flex items-center justify-between">
        <h2 class="text-base font-semibold" style="color: var(--foreground)">{editingId ? 'Edit entry' : 'New entry'}</h2>
        {#if editingId}
          <button class="focus-ring vault-btn-ghost" type="button" on:click={startCreate}>
            <X size={16} />
          </button>
        {/if}
      </div>

      <form class="space-y-4" on:submit|preventDefault={saveEntry}>
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
          <span class="text-sm" style="color: var(--muted)">{form.wordCount} words</span>
          <button class="focus-ring vault-btn-primary" type="submit" disabled={saving}>
            <Save size={16} />
            {saving ? 'Saving...' : 'Save'}
          </button>
        </div>
      </form>
    </aside>
  </div>
{/if}
