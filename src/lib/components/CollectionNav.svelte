<script lang="ts" generics="T extends { id: string; label: string; tone?: string }">
  import type { Snippet } from 'svelte';
  import * as Popover from '#lib/components/ui/popover/index.js';
  import * as Command from '#lib/components/ui/command/index.js';
  import { Button } from '#lib/components/ui/button/index.js';
  import { ChevronsUpDown } from '@lucide/svelte';
  import { cn } from '#lib/utils.js';

  let {
    items,
    selected,
    onSelect,
    row,
    title = '',
    searchPlaceholder = 'Search…',
    emptyText = 'Nothing found.',
    ariaLabel = 'Select',
    action
  }: {
    items: T[];
    selected: string | null;
    onSelect: (id: string) => void;
    row: Snippet<[T, boolean]>;
    title?: string;
    searchPlaceholder?: string;
    emptyText?: string;
    ariaLabel?: string;
    action?: Snippet;
  } = $props();

  let open = $state(false);
  let current = $derived(items.find((item) => item.id === selected) ?? null);

  function handleSelect(id: string) {
    onSelect(id);
    open = false;
  }
</script>

{#snippet body(listClass: string)}
  <Command.Root label={ariaLabel} class="bg-transparent rounded-none p-0">
    <Command.Input placeholder={searchPlaceholder} />
    <Command.List class={cn('mt-1 p-1', listClass)}>
      <Command.Empty>{emptyText}</Command.Empty>
      {#each items as item (item.id)}
        {@const active = selected === item.id}
        <Command.Item
          value={item.id}
          keywords={[item.label]}
          onSelect={() => handleSelect(item.id)}
          class={cn(
            'gap-2 rounded-l-none border-l-2 border-transparent [&>.cn-command-item-indicator]:hidden',
            active && 'border-foreground bg-accent text-accent-foreground font-medium'
          )}
        >
          {@render row(item, active)}
        </Command.Item>
      {/each}
    </Command.List>
  </Command.Root>
{/snippet}

{#if items.length}
  <!-- Below md: inline selector that opens a searchable popover -->
  <div class="grid gap-2 md:hidden">
    <Popover.Root bind:open>
      <Popover.Trigger>
        {#snippet child({ props })}
          <Button
            {...props}
            variant="outline"
            role="combobox"
            aria-expanded={open}
            class="h-10 w-full justify-between gap-2"
          >
            <span class="flex min-w-0 items-center gap-2">
              {#if current?.tone}
                <span class="size-2.5 shrink-0 rounded-full" style="background: {current.tone}"></span>
              {/if}
              <span class="truncate">{current?.label ?? ariaLabel}</span>
            </span>
            <span class="flex shrink-0 items-center gap-2">
              <span class="text-muted-foreground text-xs font-semibold">{items.length}</span>
              <ChevronsUpDown class="size-4 opacity-50" />
            </span>
          </Button>
        {/snippet}
      </Popover.Trigger>
      <Popover.Content class="w-(--bits-floating-anchor-width) p-1" align="start">
        {@render body('max-h-72')}
      </Popover.Content>
    </Popover.Root>
    {#if action}
      {@render action()}
    {/if}
  </div>

  <!-- md and up: searchable left rail -->
  <div class="hidden md:grid md:content-start md:gap-2">
    {#if title || action}
      <div class="flex items-center justify-between gap-2">
        {#if title}<h2 class="text-sm font-semibold">{title}</h2>{/if}
        {#if action}{@render action()}{/if}
      </div>
    {/if}
    <div class="rounded-lg border">
      {@render body('max-h-[calc(100vh-12rem)]')}
    </div>
  </div>
{/if}
