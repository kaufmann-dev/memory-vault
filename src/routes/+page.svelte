<script lang="ts">
  import PageHeader from '$lib/components/PageHeader.svelte';
  import * as Card from '$lib/components/ui/card/index.js';
  import { Activity, BookOpen, CalendarDays, ListChecks, StickyNote } from '@lucide/svelte';

  const features = [
    {
      href: '/diary',
      label: 'Diary',
      description: 'One encrypted place for articles, diary entries, and ramblings.',
      icon: BookOpen
    },
    {
      href: '/notes',
      label: 'Notes',
      description: 'Quick encrypted notes with groups for triage and cleanup.',
      icon: StickyNote
    },
    {
      href: '/lists',
      label: 'Lists',
      description: 'Encrypted checklists and plain lists for changing plans.',
      icon: ListChecks
    },
    {
      href: '/diagrams',
      label: 'Diagrams',
      description: 'Encrypted health metrics with client-side charts.',
      icon: Activity
    },
    {
      href: '/day-counters',
      label: 'Milestones',
      description: 'Track elapsed time since important dates.',
      icon: CalendarDays
    }
  ];
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
        </Card.Header>
        <Card.Content>
          <p class="text-muted-foreground text-sm leading-relaxed">{feature.description}</p>
        </Card.Content>
      </Card.Root>
    </a>
  {/each}
</div>
