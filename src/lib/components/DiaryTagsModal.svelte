<script lang="ts">
  import EntryModal from '#lib/components/EntryModal.svelte';
  import { replaceDiaryTag } from '#lib/stores/diary.js';
  import { toast } from 'svelte-sonner';
  import { Check, Pencil, Trash2, X } from '@lucide/svelte';
  import { Button } from '#lib/components/ui/button/index.js';
  import { Input } from '#lib/components/ui/input/index.js';

  let {
    open,
    tags,
    dek,
    onClose,
    onChanged
  }: {
    open: boolean;
    tags: Array<[tag: string, count: number]>;
    dek: CryptoKey;
    onClose: () => void;
    onChanged: (tag: string, next: string | null) => void;
  } = $props();

  let busy = $state(false);
  let editing: string | null = $state(null);
  let draft = $state('');
  let renameError = $state('');

  const entryCount = (count: number) => (count === 1 ? '1 entry' : `${count} entries`);

  function startRename(tag: string) {
    editing = tag;
    draft = tag;
    renameError = '';
  }

  function cancelRename() {
    editing = null;
    renameError = '';
  }

  async function replace(tag: string, next: string | null, success: string) {
    busy = true;
    try {
      await replaceDiaryTag(tag, next, dek);
      onChanged(tag, next);
      editing = null;
      toast.success(success);
    } catch (error) {
      toast.error(error instanceof Error ? error.message : 'The tag could not be changed.');
    } finally {
      busy = false;
    }
  }

  async function rename(tag: string, count: number) {
    const next = draft.trim();
    if (!next) {
      renameError = 'Enter a tag name.';
      return;
    }
    // Tags are typed as a comma-separated list, so a comma would split this tag in two.
    if (next.includes(',')) {
      renameError = 'Tags cannot contain commas.';
      return;
    }
    if (next === tag) {
      cancelRename();
      return;
    }
    if (tags.some(([existing]) => existing === next) && !confirm(`"${next}" already exists. Merge "${tag}" into it?`)) {
      return;
    }
    await replace(tag, next, `Renamed "${tag}" to "${next}" on ${entryCount(count)}.`);
  }

  async function remove(tag: string, count: number) {
    if (!confirm(`Delete the tag "${tag}"? It will be removed from ${entryCount(count)}.`)) return;
    await replace(tag, null, `Removed "${tag}" from ${entryCount(count)}.`);
  }
</script>

<EntryModal
  {open}
  title="Manage tags"
  description="Renaming or deleting a tag changes every entry that uses it. The entries themselves stay."
  onClose={() => {
    cancelRename();
    onClose();
  }}
>
  {#if tags.length === 0}
    <p class="text-muted-foreground text-sm">No tags yet.</p>
  {:else}
    <ul class="divide-y rounded-2xl border">
      {#each tags as [tag, count] (tag)}
        <li class="py-1.5 pr-1.5 pl-4">
          {#if editing === tag}
            <form
              class="flex items-center gap-2"
              onsubmit={(event) => {
                event.preventDefault();
                rename(tag, count);
              }}
            >
              <Input
                bind:value={draft}
                aria-label={`New name for ${tag}`}
                aria-invalid={renameError ? 'true' : undefined}
                class="h-8 min-w-0 flex-1"
                autofocus
                onkeydown={(event) => {
                  if (event.key === 'Escape') {
                    event.preventDefault();
                    event.stopPropagation();
                    cancelRename();
                  }
                }}
              />
              <Button type="submit" variant="ghost" size="icon-sm" disabled={busy} aria-label="Save tag name">
                <Check class="size-4" />
              </Button>
              <Button variant="ghost" size="icon-sm" disabled={busy} onclick={cancelRename} aria-label="Cancel rename">
                <X class="size-4" />
              </Button>
            </form>
            {#if renameError}
              <p class="text-destructive mt-1 text-xs font-medium">{renameError}</p>
            {/if}
          {:else}
            <div class="flex items-center gap-3">
              <span class="min-w-0 flex-1 truncate text-sm font-medium">{tag}</span>
              <span class="text-muted-foreground shrink-0 text-xs">{entryCount(count)}</span>
              <div class="flex shrink-0 items-center">
                <Button
                  variant="ghost"
                  size="icon-sm"
                  disabled={busy}
                  onclick={() => startRename(tag)}
                  aria-label={`Rename tag ${tag}`}
                >
                  <Pencil class="size-4" />
                </Button>
                <Button
                  variant="ghost"
                  size="icon-sm"
                  class="text-destructive hover:text-destructive"
                  disabled={busy}
                  onclick={() => remove(tag, count)}
                  aria-label={`Delete tag ${tag}`}
                >
                  <Trash2 class="size-4" />
                </Button>
              </div>
            </div>
          {/if}
        </li>
      {/each}
    </ul>
  {/if}
</EntryModal>
