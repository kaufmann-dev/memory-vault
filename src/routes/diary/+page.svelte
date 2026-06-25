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
  import { get } from 'svelte/store';
  import { onMount } from 'svelte';
  import { Pencil, Plus, Save, Search, Trash2, X } from '@lucide/svelte';
  import * as Card from '$lib/components/ui/card/index.js';
  import * as Select from '$lib/components/ui/select/index.js';
  import { Button } from '$lib/components/ui/button/index.js';
  import { Input } from '$lib/components/ui/input/index.js';
  import { Textarea } from '$lib/components/ui/textarea/index.js';
  import { Label } from '$lib/components/ui/label/index.js';
  import { Badge } from '$lib/components/ui/badge/index.js';
  import { Separator } from '$lib/components/ui/separator/index.js';
  import { ScrollArea } from '$lib/components/ui/scroll-area/index.js';

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

    if (editingId) {
      const id = editingId;
      entries = entries.map((entry) => (entry.record.id === id ? { ...entry, payload } : entry));
      await updateEncryptedRecord(id, 'diary', payload, dek);
      selectedEntryId = id;
    } else {
      const record = await createEncryptedRecord('diary', payload, dek);
      entries = [...entries, { record, payload }];
      selectedEntryId = record.id;
    }

    closeForm();
    saving = false;
  }

  async function removeEntry(id: string) {
    if (!confirm('Delete this entry?')) return;
    entries = entries.filter((entry) => entry.record.id !== id);
    if (selectedEntryId === id) selectedEntryId = null;
    if (editingId === id) startCreate();
    await deleteEncryptedRecord(id);
  }

  onMount(async () => {
    dek = get(sessionDEK);
    if (!dek) {
      locked = true;
      loading = false;
      return;
    }
    await loadEntries();
  });
</script>

<PageHeader title="Diary" description="One encrypted structure for diary entries, articles, notes, and ramblings.">
  <Button onclick={openCreate}>
    <Plus class="size-4" />
    New
  </Button>
</PageHeader>

{#if locked}
  <VaultNotice />
{:else}
  <section class="w-full">
    {#if loading}
      <p class="text-muted-foreground text-sm">Decrypting entries…</p>
    {:else if entries.length === 0}
      <EmptyState title="No diary entries yet" description="Create the first encrypted entry when you are ready." />
    {:else}
      <div class="bg-background sticky top-14 z-[5] flex flex-col gap-3 border-y py-3 sm:flex-row sm:flex-wrap sm:items-end">
        <div class="relative min-w-0 flex-1">
          <Search class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
          <Input bind:value={query} placeholder="Search entries, tags, languages" class="pl-9" aria-label="Search diary entries" />
        </div>

        <div class="grid gap-1.5">
          <Label for="diary-from" class="text-muted-foreground text-xs">From</Label>
          <Input id="diary-from" type="date" bind:value={fromDate} class="w-auto" />
        </div>
        <div class="grid gap-1.5">
          <Label for="diary-to" class="text-muted-foreground text-xs">To</Label>
          <Input id="diary-to" type="date" bind:value={toDate} class="w-auto" />
        </div>
        <div class="grid gap-1.5">
          <Label class="text-muted-foreground text-xs">Sort</Label>
          <Select.Root type="single" value={sortOrder} onValueChange={(value) => (sortOrder = value as SortOrder)}>
            <Select.Trigger class="w-40">{sortOrder === 'newest' ? 'Newest first' : 'Oldest first'}</Select.Trigger>
            <Select.Content>
              <Select.Item value="newest" label="Newest first">Newest first</Select.Item>
              <Select.Item value="oldest" label="Oldest first">Oldest first</Select.Item>
            </Select.Content>
          </Select.Root>
        </div>

        <div class="text-muted-foreground flex items-center gap-2 text-sm sm:ml-auto">
          <span>{filteredEntries.length} of {entries.length}</span>
          {#if hasFilters}
            <Button variant="ghost" size="sm" onclick={clearFilters}>
              <X class="size-4" /> Clear
            </Button>
          {/if}
        </div>
      </div>

      {#if filteredEntries.length === 0}
        <div class="mt-6">
          <EmptyState title="No matching entries" description="Clear filters or broaden the search to find more diary entries." />
        </div>
      {:else}
        <div class="mt-6 grid gap-6 lg:grid-cols-[22rem_minmax(0,1fr)]">
          <Card.Root class="overflow-hidden p-0">
            <ScrollArea class="h-[min(70vh,40rem)]">
              {#each groupedEntries as group (group.key)}
                <div class="bg-muted/60 text-muted-foreground sticky top-0 z-[1] flex justify-between border-b px-4 py-2.5 text-[0.6875rem] font-semibold tracking-wider uppercase backdrop-blur">
                  <span>{group.label}</span>
                  <span>{group.items.length}</span>
                </div>
                {#each group.items as item (item.record.id)}
                  <button
                    type="button"
                    onclick={() => (selectedEntryId = item.record.id)}
                    aria-current={selectedEntry?.record.id === item.record.id ? 'true' : undefined}
                    class="hover:bg-muted/50 grid w-full grid-cols-[3.5rem_minmax(0,1fr)] gap-3 border-b border-l-2 px-4 py-3.5 text-left transition-colors {selectedEntry?.record.id === item.record.id ? 'border-l-primary bg-muted/60' : 'border-l-transparent'}"
                  >
                    <span class="text-muted-foreground text-xs font-medium">{formatShortDate(item.payload.occurredAt)}</span>
                    <span class="grid min-w-0 gap-1">
                      <strong class="truncate text-sm font-semibold">{item.payload.title || 'Untitled'}</strong>
                      <span class="text-muted-foreground line-clamp-2 text-[0.8125rem] leading-snug">
                        {excerpt(item.payload.body) || 'No body text'}
                      </span>
                    </span>
                  </button>
                {/each}
              {/each}
            </ScrollArea>
          </Card.Root>

          {#if selectedEntry}
            <article class="group min-w-0">
              <div class="text-muted-foreground flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
                <time datetime={selectedEntry.payload.occurredAt}>{formatDate(selectedEntry.payload.occurredAt)}</time>
                <span>·</span>
                <span>{selectedEntry.payload.language}</span>
                <span>·</span>
                <span>{selectedEntry.payload.wordCount} words</span>
              </div>

              <div class="mt-2 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <h2 class="text-3xl font-bold break-words">{selectedEntry.payload.title || 'Untitled'}</h2>
                <div class="flex shrink-0 gap-2">
                  <Button variant="outline" size="sm" onclick={() => startEdit(selectedEntry)}>
                    <Pencil class="size-4" /> Edit
                  </Button>
                  <Button
                    variant="outline"
                    size="icon"
                    class="text-destructive hover:text-destructive size-8"
                    onclick={() => removeEntry(selectedEntry.record.id)}
                    aria-label="Delete entry"
                  >
                    <Trash2 class="size-4" />
                  </Button>
                </div>
              </div>

              {#if selectedEntry.payload.tags.length}
                <div class="mt-4 flex flex-wrap gap-2">
                  {#each selectedEntry.payload.tags as tag (tag)}
                    <Badge variant="secondary">{tag}</Badge>
                  {/each}
                </div>
              {/if}

              <div class="mt-6 max-w-[74ch] text-[1.0625rem] leading-8 break-words whitespace-pre-wrap">
                {selectedEntry.payload.body}
              </div>
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
      class="grid gap-4"
      onsubmit={(event) => {
        event.preventDefault();
        saveEntry();
      }}
    >
      <div class="grid gap-2">
        <Label for="entry-title">Title</Label>
        <Input id="entry-title" bind:value={form.title} />
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div class="grid gap-2">
          <Label for="entry-date">Date</Label>
          <Input id="entry-date" type="date" bind:value={form.occurredAt} required />
        </div>
        <div class="grid gap-2">
          <Label>Language</Label>
          <Select.Root type="single" value={form.language} onValueChange={(value) => (form.language = value as DiaryPayload['language'])}>
            <Select.Trigger class="w-full">{form.language}</Select.Trigger>
            <Select.Content>
              <Select.Item value="English" label="English">English</Select.Item>
              <Select.Item value="German" label="German">German</Select.Item>
              <Select.Item value="Other" label="Other">Other</Select.Item>
            </Select.Content>
          </Select.Root>
        </div>
      </div>

      <div class="grid gap-2">
        <Label for="entry-tags">Tags</Label>
        <Input id="entry-tags" bind:value={tagInput} placeholder="comma, separated" />
      </div>

      <div class="grid gap-2">
        <Label for="entry-body">Body</Label>
        <Textarea id="entry-body" bind:value={form.body} required class="min-h-64 leading-relaxed" />
      </div>

      <div class="flex items-center justify-between">
        <span class="text-muted-foreground text-sm">{wordCount(form.body)} words</span>
        <Button type="submit" disabled={saving}>
          <Save class="size-4" />
          {saving ? 'Saving…' : 'Save'}
        </Button>
      </div>
    </form>
  </EntryModal>
{/if}
