<script lang="ts">
  import LoadingState from '$lib/components/LoadingState.svelte';
  import DiaryEntryModal from '$lib/components/DiaryEntryModal.svelte';
  import { deleteEncryptedRecord } from '$lib/client/records';
  import {
    diaryEntries,
    formatDate,
    loadDiary,
    removeDiaryEntry
  } from '$lib/stores/diary';
  import { sessionDEK } from '$lib/stores/cryptoKey';
  import { get } from 'svelte/store';
  import { onMount } from 'svelte';
  import { goto } from '$app/navigation';
  import { page } from '$app/state';
  import { ArrowLeft, Pencil, Trash2 } from '@lucide/svelte';
  import { Button } from '$lib/components/ui/button/index.js';
  import { Badge } from '$lib/components/ui/badge/index.js';

  let dek: CryptoKey | null = $state(null);
  let loading = $state(true);
  let editOpen = $state(false);

  let id = $derived(page.params.id);
  let entry = $derived($diaryEntries.find((item) => item.record.id === id) ?? null);

  async function removeEntry() {
    if (!entry || !confirm('Delete this entry?')) return;
    const removedId = entry.record.id;
    removeDiaryEntry(removedId);
    await deleteEncryptedRecord(removedId);
    goto('/diary');
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

<div class="mx-auto w-full max-w-3xl">
  <a
    href="/diary"
    class="text-muted-foreground hover:text-foreground inline-flex items-center gap-1.5 text-sm transition-colors"
  >
    <ArrowLeft class="size-4" /> Back to entries
  </a>

  {#if loading}
    <LoadingState message="Decrypting entries…" class="mt-8" />
  {:else if !entry}
    <div class="mt-8">
      <h1 class="text-2xl font-bold tracking-tight">Entry not found</h1>
      <p class="text-muted-foreground mt-2 text-sm">
        This entry may have been deleted, or the link is no longer valid.
      </p>
    </div>
  {:else}
    <article class="mt-6">
      <div class="text-muted-foreground flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
        <time datetime={entry.payload.occurredAt}>{formatDate(entry.payload.occurredAt)}</time>
        <span>·</span>
        <span>{entry.payload.language}</span>
        <span>·</span>
        <span>{entry.payload.wordCount} words</span>
      </div>

      <div class="mt-2 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <h1 class="text-3xl font-bold break-words">{entry.payload.title || 'Untitled'}</h1>
        <div class="flex shrink-0 gap-2">
          <Button variant="outline" size="sm" onclick={() => (editOpen = true)}>
            <Pencil class="size-4" /> Edit
          </Button>
          <Button
            variant="outline"
            size="icon"
            class="text-destructive hover:text-destructive size-8"
            onclick={removeEntry}
            aria-label="Delete entry"
          >
            <Trash2 class="size-4" />
          </Button>
        </div>
      </div>

      {#if entry.payload.tags.length}
        <div class="mt-4 flex flex-wrap gap-2">
          {#each entry.payload.tags as tag (tag)}
            <Badge variant="secondary">{tag}</Badge>
          {/each}
        </div>
      {/if}

      <div class="mt-6 text-[1.0625rem] leading-8 break-words whitespace-pre-wrap">
        {entry.payload.body}
      </div>
    </article>
  {/if}
</div>

{#if dek && entry}
  <DiaryEntryModal
    open={editOpen}
    {entry}
    {dek}
    onClose={() => (editOpen = false)}
    onSaved={() => (editOpen = false)}
  />
{/if}
