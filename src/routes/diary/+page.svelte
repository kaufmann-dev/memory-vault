<script lang="ts">
  import EmptyState from '#lib/components/EmptyState.svelte';
  import LoadingState from '#lib/components/LoadingState.svelte';
  import DiaryEntryModal from '#lib/components/DiaryEntryModal.svelte';
  import DiaryTagsModal from '#lib/components/DiaryTagsModal.svelte';
  import PageHeader from '#lib/components/PageHeader.svelte';
  import {
    diaryEntries,
    excerpt,
    formatMonth,
    formatShortDate,
    loadDiary,
    replaceTag,
    type DiaryItem
  } from '#lib/stores/diary.js';
  import { sessionDEK } from '#lib/stores/cryptoKey.js';
  import { DIARY_LANGUAGES } from '#lib/diary-import.js';
  import { wordCount } from '#lib/markdown.js';
  import type { DiaryPayload } from '#lib/types.js';
  import { get } from 'svelte/store';
  import { onMount } from 'svelte';
  import { goto } from '$app/navigation';
  import { toast } from 'svelte-sonner';
  import { Plus, Search, SlidersHorizontal, Tags, X } from '@lucide/svelte';
  import * as Card from '#lib/components/ui/card/index.js';
  import { Badge } from '#lib/components/ui/badge/index.js';
  import * as Select from '#lib/components/ui/select/index.js';
  import { Button } from '#lib/components/ui/button/index.js';
  import { Input } from '#lib/components/ui/input/index.js';
  import { Label } from '#lib/components/ui/label/index.js';
  import { Slider } from '#lib/components/ui/slider/index.js';

  type SortOrder = 'newest' | 'oldest';
  type LanguageFilter = 'all' | DiaryPayload['language'];

  type DiaryGroup = {
    key: string;
    label: string;
    items: DiaryItem[];
  };

  let dek: CryptoKey | null = $state(null);
  let loading = $state(true);
  let formOpen = $state(false);
  let tagsOpen = $state(false);
  let query = $state('');
  let fromDate = $state('');
  let toDate = $state('');
  let sortOrder: SortOrder = $state('newest');
  let selectedTags: string[] = $state([]);
  let language: LanguageFilter = $state('all');
  // null means the full range, so the filter stays inactive as entries grow longer.
  let wordRange: [number, number] | null = $state(null);
  let filtersOpen = $state(false);

  let wordCounts = $derived(new Map($diaryEntries.map((item) => [item.record.id, wordCount(item.payload.body)])));
  let maxWords = $derived(Math.max(1, ...wordCounts.values()));
  const compactNumberFormat = new Intl.NumberFormat(undefined, { notation: 'compact' });

  let activeFilterCount = $derived(
    [fromDate || toDate, language !== 'all', selectedTags.length > 0, wordRange !== null].filter(Boolean).length
  );

  function setWordRange([low, high]: number[]) {
    wordRange = low <= 0 && high >= maxWords ? null : [low, high];
  }

  let tagCounts = $derived.by(() => {
    const counts = new Map<string, number>();
    for (const item of $diaryEntries) {
      for (const tag of item.payload.tags) counts.set(tag, (counts.get(tag) ?? 0) + 1);
    }
    return [...counts].sort(([a], [b]) => a.localeCompare(b));
  });

  let tagLabel = $derived(
    selectedTags.length === 0 ? 'All tags' : selectedTags.length === 1 ? selectedTags[0] : `${selectedTags.length} tags`
  );

  let filteredEntries = $derived.by(() => {
    const normalizedQuery = query.trim().toLowerCase();

    return $diaryEntries
      .filter((item) => {
        const occurredAt = item.payload.occurredAt;
        if (fromDate && occurredAt < fromDate) return false;
        if (toDate && occurredAt > toDate) return false;
        if (!selectedTags.every((tag) => item.payload.tags.includes(tag))) return false;
        if (language !== 'all' && item.payload.language !== language) return false;
        if (wordRange) {
          const words = wordCounts.get(item.record.id) ?? 0;
          if (words < wordRange[0] || words > wordRange[1]) return false;
        }

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

  let filteredWords = $derived(filteredEntries.reduce((sum, item) => sum + (wordCounts.get(item.record.id) ?? 0), 0));

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

  let hasFilters = $derived(Boolean(query.trim() || activeFilterCount || sortOrder !== 'newest'));

  function clearFilters() {
    query = '';
    fromDate = '';
    toDate = '';
    sortOrder = 'newest';
    selectedTags = [];
    language = 'all';
    wordRange = null;
  }

  function handleTagChanged(tag: string, next: string | null) {
    if (selectedTags.includes(tag)) selectedTags = replaceTag(selectedTags, tag, next);
  }

  onMount(async () => {
    dek = get(sessionDEK);
    if (!dek) {
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

<section class="w-full">
  {#if loading}
    <LoadingState message="Decrypting entries…" />
  {:else if $diaryEntries.length === 0}
    <EmptyState title="No diary entries yet" description="Create the first encrypted entry when you are ready." />
  {:else}
    <div class="grid gap-3 border-y py-3">
      <div class="flex flex-wrap items-center gap-2">
        <div class="relative min-w-0 basis-full sm:basis-auto sm:flex-1">
          <Search class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
          <Input bind:value={query} placeholder="Search entries, tags, languages" class="pl-9" aria-label="Search diary entries" />
        </div>

        <Button
          variant={filtersOpen ? 'secondary' : 'outline'}
          onclick={() => (filtersOpen = !filtersOpen)}
          aria-expanded={filtersOpen}
          aria-controls="diary-filters"
        >
          <SlidersHorizontal class="size-4" />
          Filters
          {#if activeFilterCount}
            <Badge class="h-5 min-w-5 px-1.5 tabular-nums">{activeFilterCount}</Badge>
          {/if}
        </Button>

        <Select.Root type="single" value={sortOrder} onValueChange={(value) => (sortOrder = value as SortOrder)}>
          <Select.Trigger class="w-36" aria-label="Sort order">{sortOrder === 'newest' ? 'Newest first' : 'Oldest first'}</Select.Trigger>
          <Select.Content>
            <Select.Item value="newest" label="Newest first">Newest first</Select.Item>
            <Select.Item value="oldest" label="Oldest first">Oldest first</Select.Item>
          </Select.Content>
        </Select.Root>

        <div class="text-muted-foreground ml-auto flex items-center gap-2 text-sm">
          <span class="whitespace-nowrap tabular-nums" title="{filteredWords.toLocaleString()} words">
            {filteredEntries.length} of {$diaryEntries.length} · {compactNumberFormat.format(filteredWords)} words
          </span>
          {#if hasFilters}
            <Button variant="ghost" size="sm" onclick={clearFilters}>
              <X class="size-4" /> Clear
            </Button>
          {/if}
        </div>
      </div>

      {#if filtersOpen}
        <div id="diary-filters" class="grid grid-cols-2 gap-x-3 gap-y-4 pt-1 lg:grid-cols-4">
          <div class="grid min-w-0 gap-1.5">
            <Label for="diary-from" class="text-muted-foreground text-xs">From</Label>
            <Input
              id="diary-from"
              type="date"
              bind:value={fromDate}
              class="h-8 w-full min-w-0 max-w-full appearance-none overflow-hidden py-1 text-sm leading-5 [min-inline-size:0] [&::-webkit-date-and-time-value]:m-0 [&::-webkit-date-and-time-value]:min-h-0 [&::-webkit-date-and-time-value]:text-left"
            />
          </div>
          <div class="grid min-w-0 gap-1.5">
            <Label for="diary-to" class="text-muted-foreground text-xs">To</Label>
            <Input
              id="diary-to"
              type="date"
              bind:value={toDate}
              class="h-8 w-full min-w-0 max-w-full appearance-none overflow-hidden py-1 text-sm leading-5 [min-inline-size:0] [&::-webkit-date-and-time-value]:m-0 [&::-webkit-date-and-time-value]:min-h-0 [&::-webkit-date-and-time-value]:text-left"
            />
          </div>

          <div class="grid min-w-0 gap-1.5">
            <Label for="diary-language" class="text-muted-foreground text-xs">Language</Label>
            <Select.Root type="single" value={language} onValueChange={(value) => (language = value as LanguageFilter)}>
              <Select.Trigger id="diary-language" class="w-full">{language === 'all' ? 'All languages' : language}</Select.Trigger>
              <Select.Content>
                <Select.Item value="all" label="All languages">All languages</Select.Item>
                {#each DIARY_LANGUAGES as option (option)}
                  <Select.Item value={option} label={option}>{option}</Select.Item>
                {/each}
              </Select.Content>
            </Select.Root>
          </div>

          <div class="grid min-w-0 gap-1.5">
            <Label for={tagCounts.length ? 'diary-tags' : undefined} class="text-muted-foreground text-xs">Tags</Label>
            {#if tagCounts.length}
              <div class="flex min-w-0 items-center gap-1">
                <Select.Root type="multiple" bind:value={selectedTags}>
                  <Select.Trigger id="diary-tags" class="min-w-0 flex-1">
                    <span class="truncate">{tagLabel}</span>
                  </Select.Trigger>
                  <Select.Content class="max-h-[min(18rem,var(--bits-select-content-available-height))]">
                    {#each tagCounts as [tag, count] (tag)}
                      <Select.Item value={tag} label={tag}>
                        <span class="truncate">{tag}</span>
                        <span class="text-muted-foreground ml-auto text-xs">{count}</span>
                      </Select.Item>
                    {/each}
                  </Select.Content>
                </Select.Root>
                <Button variant="ghost" size="icon" onclick={() => (tagsOpen = true)} aria-label="Manage tags" title="Manage tags">
                  <Tags class="size-4" />
                </Button>
              </div>
            {:else}
              <p class="text-muted-foreground flex h-8 items-center text-sm">No tags yet</p>
            {/if}
          </div>

          <div class="col-span-2 grid gap-3 lg:col-span-4">
            <div class="flex items-baseline justify-between gap-3">
              <span id="diary-words-label" class="text-muted-foreground text-xs font-medium">Word count</span>
              <span class="text-sm tabular-nums">
                {wordRange ? `${wordRange[0]}–${wordRange[1]} words` : 'Any length'}
              </span>
            </div>
            <Slider
              type="multiple"
              min={0}
              max={maxWords}
              step={1}
              bind:value={() => wordRange ?? [0, maxWords], setWordRange}
              aria-labelledby="diary-words-label"
              class="py-1"
            />
          </div>
        </div>
      {/if}
    </div>

    {#if filteredEntries.length === 0}
      <div class="mt-6">
        <EmptyState title="No matching entries" description="Clear filters or broaden the search to find more diary entries." />
      </div>
    {:else}
      <Card.Root class="mt-6 gap-0 overflow-hidden p-0">
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
                <span class="text-muted-foreground line-clamp-2 text-[0.8125rem] leading-snug wrap-anywhere">
                  {excerpt(item.payload.body) || 'No body text'}
                </span>
                {#if item.payload.tags.length}
                  <span class="mt-1 flex flex-wrap gap-1">
                    {#each item.payload.tags as tag (tag)}
                      <Badge
                        variant={selectedTags.includes(tag) ? 'default' : 'secondary'}
                        class="max-w-full text-[0.6875rem]"
                      >
                        <span class="truncate">{tag}</span>
                      </Badge>
                    {/each}
                  </span>
                {/if}
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
    onBatchSaved={(count) => {
      formOpen = false;
      toast.success(`${count} ${count === 1 ? 'entry' : 'entries'} added.`);
    }}
  />
  <DiaryTagsModal
    open={tagsOpen}
    tags={tagCounts}
    {dek}
    onClose={() => (tagsOpen = false)}
    onChanged={handleTagChanged}
  />
{/if}
