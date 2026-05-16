<script lang="ts">
  import PageHeader from '$lib/components/PageHeader.svelte';
  import { Activity, BookOpen, CalendarDays, ListChecks, UsersRound } from '@lucide/svelte';

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
      label: 'Day counters',
      description: 'Track elapsed time since important dates.',
      icon: CalendarDays
    },
    {
      href: '/family',
      label: 'Family tree',
      description: 'A cleaner encrypted tree editor and viewer.',
      icon: UsersRound
    }
  ];
</script>

<PageHeader title="Home" description="Everything here is private and backed by encrypted records." />

<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
  {#each features as feature}
    <a
      class="focus-ring rounded-xl border p-5 text-inherit hover:no-underline"
      style="border-color: var(--border); background: var(--surface)"
      href={feature.href}
    >
      <svelte:component this={feature.icon} size={22} color="var(--accent)" />
      <h2 class="mt-4 text-base font-semibold">{feature.label}</h2>
      <p class="mt-2 text-sm leading-6" style="color: var(--muted)">{feature.description}</p>
    </a>
  {/each}
</div>
