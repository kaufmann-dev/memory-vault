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
  <button class="focus-ring inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium text-white" style="background: var(--accent)" on:click={startCreate} type="button">
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
          <article class="rounded-2xl p-5 shadow-sm" style="background: var(--surface)">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
              <div>
                <h2 class="text-lg font-semibold">{item.payload.title || 'Untitled'}</h2>
                <p class="mt-1 text-sm" style="color: var(--muted)">
                  {item.payload.occurredAt} · {item.payload.language} · {item.payload.wordCount} words
                </p>
              </div>
              <div class="flex gap-2">
                <button class="focus-ring rounded-lg border px-3 py-2 text-sm" style="border-color: var(--border)" type="button" on:click={() => startEdit(item)}>Edit</button>
                <button class="focus-ring rounded-lg border px-3 py-2 text-sm" style="border-color: var(--danger); color: var(--danger)" type="button" on:click={() => removeEntry(item.record.id)}>
                  <Trash2 size={16} />
                </button>
              </div>
            </div>
            {#if item.payload.tags.length}
              <div class="mt-3 flex flex-wrap gap-2">
                {#each item.payload.tags as tag}
                  <span class="rounded-lg border px-2 py-1 text-xs" style="border-color: var(--border); color: var(--muted)">{tag}</span>
                {/each}
              </div>
            {/if}
            <p class="mt-4 whitespace-pre-wrap text-sm leading-6">{item.payload.body}</p>
          </article>
        {/each}
      {/if}
    </section>

    <aside class="rounded-2xl p-5 shadow-sm" style="background: var(--surface)">
      <div class="mb-4 flex items-center justify-between">
        <h2 class="text-base font-semibold">{editingId ? 'Edit entry' : 'New entry'}</h2>
        {#if editingId}
          <button class="focus-ring rounded-lg border p-2" style="border-color: var(--border)" type="button" on:click={startCreate}>
            <X size={16} />
          </button>
        {/if}
      </div>

      <form class="space-y-4" on:submit|preventDefault={saveEntry}>
        <label class="block text-sm font-medium">
          Title
          <input class="focus-ring mt-1 w-full rounded-lg border px-3 py-2" style="border-color: var(--border)" bind:value={form.title} />
        </label>

        <div class="grid grid-cols-2 gap-3">
          <label class="block text-sm font-medium">
            Date
            <input class="focus-ring mt-1 w-full rounded-lg border px-3 py-2" style="border-color: var(--border)" type="date" bind:value={form.occurredAt} required />
          </label>
          <label class="block text-sm font-medium">
            Language
            <select class="focus-ring mt-1 w-full rounded-lg border px-3 py-2" style="border-color: var(--border)" bind:value={form.language}>
              <option>English</option>
              <option>German</option>
              <option>Other</option>
            </select>
          </label>
        </div>

        <label class="block text-sm font-medium">
          Tags
          <input class="focus-ring mt-1 w-full rounded-lg border px-3 py-2" style="border-color: var(--border)" bind:value={tagInput} placeholder="comma, separated" />
        </label>

        <label class="block text-sm font-medium">
          Body
          <textarea class="focus-ring mt-1 min-h-64 w-full rounded-lg border px-3 py-2 leading-6" style="border-color: var(--border)" bind:value={form.body} required></textarea>
        </label>

        <div class="flex items-center justify-between">
          <span class="text-sm" style="color: var(--muted)">{form.wordCount} words</span>
          <button class="focus-ring inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium text-white" style="background: var(--accent)" type="submit" disabled={saving}>
            <Save size={16} />
            {saving ? 'Saving...' : 'Save'}
          </button>
        </div>
      </form>
    </aside>
  </div>
{/if}
