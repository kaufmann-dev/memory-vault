<script lang="ts">
  import EmptyState from '$lib/components/EmptyState.svelte';
  import DiaryEntryModal from '$lib/components/DiaryEntryModal.svelte';
  import PageHeader from '$lib/components/PageHeader.svelte';
  import VaultNotice from '$lib/components/VaultNotice.svelte';
  import {
    diaryEntries,
    excerpt,
    formatMonth,
    formatShortDate,
    loadDiary,
    type DiaryItem
  } from '$lib/stores/diary';
  import { sessionDEK } from '$lib/stores/cryptoKey';
  import { get } from 'svelte/store';
  import { onMount } from 'svelte';
  import { goto } from '$app/navigation';
  import { Plus, Search, X } from '@lucide/svelte';
  import * as Card from '$lib/components/ui/card/index.js';
  import * as Select from '$lib/components/ui/select/index.js';
  import { Button } from '$lib/components/ui/button/index.js';
  import { Input } from '$lib/components/ui/input/index.js';
  import { Label } from '$lib/components/ui/label/index.js';

  type SortOrder = 'newest' | 'oldest';

  type DiaryGroup = {
    key: string;
    label: string;
    items: DiaryItem[];
  };

  let dek: CryptoKey | null = $state(null);
  let locked = $state(false);
  let loading = $state(true);
  let formOpen = $state(false);
  let query = $state('');
  let fromDate = $state('');
  let toDate = $state('');
  let sortOrder: SortOrder = $state('newest');

  let filteredEntries = $derived.by(() => {
    const normalizedQuery = query.trim().toLowerCase();

    return $diaryEntries
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

  let hasFilters = $derived(Boolean(query.trim() || fromDate || toDate || sortOrder !== 'newest'));

  function clearFilters() {
    query = '';
    fromDate = '';
    toDate = '';
    sortOrder = 'newest';
  }

  onMount(async () => {
    dek = get(sessionDEK);
    if (!dek) {
      locked = true;
      loading = false;
      return;
    }
    await loadDiary(dek);
    loading = false;
  });
</script>

<PageHeader title="Diary" description="One encrypted structure for diary entries, articles, notes, and ramblings.">
  <Button onclick={() => (formOpen = true)}>
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
    {:else if $diaryEntries.length === 0}
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
          <span>{filteredEntries.length} of {$diaryEntries.length}</span>
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
        <Card.Root class="mt-6 overflow-hidden p-0">
          {#each groupedEntries as group (group.key)}
            <div class="bg-muted/60 text-muted-foreground flex justify-between border-b px-4 py-2.5 text-[0.6875rem] font-semibold tracking-wider uppercase">
              <span>{group.label}</span>
              <span>{group.items.length}</span>
            </div>
            {#each group.items as item (item.record.id)}
              <a
                href="/diary/{item.record.id}"
                class="hover:bg-muted/50 grid w-full grid-cols-[3.5rem_minmax(0,1fr)] gap-3 border-b px-4 py-3.5 text-left transition-colors"
              >
                <span class="text-muted-foreground text-xs font-medium">{formatShortDate(item.payload.occurredAt)}</span>
                <span class="grid min-w-0 gap-1">
                  <strong class="truncate text-sm font-semibold">{item.payload.title || 'Untitled'}</strong>
                  <span class="text-muted-foreground line-clamp-2 text-[0.8125rem] leading-snug">
                    {excerpt(item.payload.body) || 'No body text'}
                  </span>
                </span>
              </a>
            {/each}
          {/each}
        </Card.Root>
      {/if}
    {/if}
  </section>

  {#if dek}
    <DiaryEntryModal
      open={formOpen}
      entry={null}
      {dek}
      onClose={() => (formOpen = false)}
      onSaved={(item) => {
        formOpen = false;
        goto(`/diary/${item.record.id}`);
      }}
    />
  {/if}
{/if}
