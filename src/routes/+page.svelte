<script lang="ts">
  import PageHeader from '$lib/components/PageHeader.svelte';
  import { Activity, BookOpen, CalendarDays, ListChecks } from '@lucide/svelte';

  const features = [
    {
      href: '/diary',
      label: 'Diary',
      description: 'One encrypted place for articles, diary entries, and ramblings.',
      icon: BookOpen
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
  {#each features as feature}
    <a
      class="focus-ring group vault-card vault-card-hover p-5 text-inherit hover:no-underline"
      href={feature.href}
    >
      <div class="mb-4 flex items-center gap-3">
        <span style="color: var(--accent)">
          <svelte:component this={feature.icon} size={20} />
        </span>
        <h2 class="text-sm font-semibold" style="color: var(--foreground)">{feature.label}</h2>
      </div>
      <p class="text-sm leading-relaxed" style="color: var(--muted)">{feature.description}</p>
    </a>
  {/each}
</div>
