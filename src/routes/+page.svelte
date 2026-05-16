<script lang="ts">
  import PageHeader from '$lib/components/PageHeader.svelte';
  import { Activity, BookOpen, CalendarDays, ListChecks, UsersRound } from '@lucide/svelte';

  const features = [
    {
      href: '/diary',
      label: 'Diary',
      description: 'One encrypted place for articles, diary entries, and ramblings.',
      icon: BookOpen,
      gradient: 'from-blue-500 to-indigo-500'
    },
    {
      href: '/lists',
      label: 'Lists',
      description: 'Encrypted checklists and plain lists for changing plans.',
      icon: ListChecks,
      gradient: 'from-emerald-500 to-teal-500'
    },
    {
      href: '/diagrams',
      label: 'Diagrams',
      description: 'Encrypted health metrics with client-side charts.',
      icon: Activity,
      gradient: 'from-violet-500 to-purple-500'
    },
    {
      href: '/day-counters',
      label: 'Day counters',
      description: 'Track elapsed time since important dates.',
      icon: CalendarDays,
      gradient: 'from-amber-500 to-orange-500'
    },
    {
      href: '/family',
      label: 'Family tree',
      description: 'A cleaner encrypted tree editor and viewer.',
      icon: UsersRound,
      gradient: 'from-rose-500 to-pink-500'
    }
  ];
</script>

<div class="mb-10 vault-card p-8 relative overflow-hidden">
  <div class="absolute -top-20 -right-20 h-64 w-64 rounded-full opacity-20 blur-3xl" style="background: var(--accent)"></div>
  <div class="absolute -bottom-16 -left-16 h-48 w-48 rounded-full opacity-10 blur-3xl" style="background: var(--accent)"></div>
  <div class="relative">
    <p class="text-xs font-semibold uppercase tracking-widest" style="color: var(--accent)">Welcome back</p>
    <h1 class="mt-2 text-3xl font-extrabold tracking-tight sm:text-4xl" style="color: var(--foreground)">Your vault is secure</h1>
    <p class="mt-2 max-w-xl text-base leading-relaxed" style="color: var(--muted)">Everything here is private and backed by encrypted records. Choose a section to begin.</p>
  </div>
</div>

<div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
  {#each features as feature}
    <a
      class="focus-ring group relative overflow-hidden rounded-2xl p-6 vault-card vault-card-hover text-inherit hover:no-underline"
      href={feature.href}
    >
      <div class="mb-5 grid h-11 w-11 place-items-center rounded-xl bg-gradient-to-br {feature.gradient} shadow-lg shadow-blue-500/20">
        <svelte:component this={feature.icon} size={20} color="white" />
      </div>
      <h2 class="text-base font-semibold" style="color: var(--foreground)">{feature.label}</h2>
      <p class="mt-2 text-sm leading-relaxed" style="color: var(--muted)">{feature.description}</p>
    </a>
  {/each}
</div>
