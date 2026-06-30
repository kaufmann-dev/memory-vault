<script lang="ts">
  import PageHeader from '$lib/components/PageHeader.svelte';
  import { Skeleton } from '$lib/components/ui/skeleton/index.js';
  import { fetchRecordCounts } from '$lib/client/records';
  import type { RecordType } from '$lib/types';
  import { onMount } from 'svelte';
  import {
    Activity,
    BookOpen,
    CalendarDays,
    ChevronRight,
    KeyRound,
    ListChecks,
    StickyNote
  } from '@lucide/svelte';

  const features = [
    {
      href: '/diary',
      label: 'Diary',
      description: 'One encrypted place for articles, diary entries, and ramblings.',
      icon: BookOpen,
      type: 'diary' as RecordType,
      one: 'entry',
      many: 'entries'
    },
    {
      href: '/notes',
      label: 'Notes',
      description: 'Quick encrypted notes with groups for triage and cleanup.',
      icon: StickyNote,
      type: 'note' as RecordType,
      one: 'note',
      many: 'notes'
    },
    {
      href: '/lists',
      label: 'Lists',
      description: 'Encrypted checklists and plain lists for changing plans.',
      icon: ListChecks,
      type: 'list' as RecordType,
      one: 'list',
      many: 'lists'
    },
    {
      href: '/diagrams',
      label: 'Diagrams',
      description: 'Encrypted health metrics with client-side charts.',
      icon: Activity,
      type: 'diagram' as RecordType,
      one: 'diagram',
      many: 'diagrams'
    },
    {
      href: '/day-counters',
      label: 'Milestones',
      description: 'Track elapsed time since important dates.',
      icon: CalendarDays,
      type: 'day_counter' as RecordType,
      one: 'milestone',
      many: 'milestones'
    },
    {
      href: '/secrets',
      label: 'Secrets',
      description: 'Encrypted passwords, keys, accounts, connections, and more.',
      icon: KeyRound,
      type: 'secret' as RecordType,
      one: 'secret',
      many: 'secrets'
    }
  ];

  let counts = $state<Record<RecordType, number> | null>(null);
  let loading = $state(true);

  const label = (n: number, one: string, many: string) => `${n} ${n === 1 ? one : many}`;

  onMount(async () => {
    try {
      counts = await fetchRecordCounts();
    } catch {
      counts = null;
    } finally {
      loading = false;
    }
  });
</script>

<PageHeader title="Home" description="Everything here is private and backed by encrypted records." />

<div class="divide-border border-border divide-y overflow-hidden rounded-xl border">
  {#each features as feature (feature.href)}
    {@const Icon = feature.icon}
    <a
      href={feature.href}
      class="group focus-visible:ring-ring hover:bg-muted/40 flex items-center gap-4 p-4 outline-none transition-colors focus-visible:ring-2 focus-visible:ring-inset"
    >
      <div class="bg-muted text-foreground flex size-10 shrink-0 items-center justify-center rounded-lg">
        <Icon class="size-5" />
      </div>
      <div class="min-w-0 flex-1">
        <p class="font-medium">{feature.label}</p>
        <p class="text-muted-foreground truncate text-sm">{feature.description}</p>
      </div>
      <div class="text-muted-foreground flex shrink-0 items-center gap-3 text-sm">
        {#if loading}
          <Skeleton class="h-4 w-14" />
        {:else if counts}
          <span class="tabular-nums">{label(counts[feature.type], feature.one, feature.many)}</span>
        {/if}
        <ChevronRight class="size-4 transition-transform group-hover:translate-x-0.5" />
      </div>
    </a>
  {/each}
</div>
