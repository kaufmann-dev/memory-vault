<script lang="ts">
  import PageHeader from '$lib/components/PageHeader.svelte';
  import * as Card from '$lib/components/ui/card/index.js';
  import { Skeleton } from '$lib/components/ui/skeleton/index.js';
  import { fetchRecordCounts } from '$lib/client/records';
  import type { RecordType } from '$lib/types';
  import { onMount } from 'svelte';
  import { Activity, BookOpen, CalendarDays, ListChecks, StickyNote } from '@lucide/svelte';

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

<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
  {#each features as feature (feature.href)}
    {@const Icon = feature.icon}
    <a href={feature.href} class="group focus-visible:ring-ring rounded-xl outline-none focus-visible:ring-2">
      <Card.Root class="hover:border-foreground/20 h-full transition-colors">
        <Card.Header>
          <div class="flex items-center gap-3">
            <div class="bg-muted text-foreground flex size-9 items-center justify-center rounded-lg">
              <Icon class="size-5" />
            </div>
            <Card.Title>{feature.label}</Card.Title>
          </div>
          <Card.Action>
            {#if loading}
              <Skeleton class="h-4 w-16" />
            {:else if counts}
              <span class="text-muted-foreground text-sm">
                {label(counts[feature.type], feature.one, feature.many)}
              </span>
            {/if}
          </Card.Action>
        </Card.Header>
        <Card.Content>
          <p class="text-muted-foreground text-sm leading-relaxed">{feature.description}</p>
        </Card.Content>
      </Card.Root>
    </a>
  {/each}
</div>
