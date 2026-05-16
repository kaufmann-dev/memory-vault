<script lang="ts">
  import { lockVault } from '$lib/stores/cryptoKey';
  import type { SafeUser } from '$lib/types';
  import { page } from '$app/state';
  import type { Snippet } from 'svelte';
  import {
    Activity,
    BookOpen,
    CalendarDays,
    Home,
    ListChecks,
    LogOut,
    Settings,
    UsersRound
  } from '@lucide/svelte';

  let { user, children }: { user: SafeUser; children: Snippet } = $props();

  const nav = [
    { href: '/', label: 'Home', icon: Home },
    { href: '/diary', label: 'Diary', icon: BookOpen },
    { href: '/lists', label: 'Lists', icon: ListChecks },
    { href: '/diagrams', label: 'Diagrams', icon: Activity },
    { href: '/day-counters', label: 'Day counters', icon: CalendarDays },
    { href: '/family', label: 'Family', icon: UsersRound },
    { href: '/settings', label: 'Settings', icon: Settings }
  ];

  async function logout() {
    await fetch('/api/auth/logout', { method: 'POST' });
    lockVault();
    location.href = '/login';
  }
</script>

<div class="min-h-screen">
  <aside
    class="fixed inset-y-0 left-0 hidden w-64 border-r px-4 py-5 lg:block"
    style="border-color: var(--border); background: var(--surface)"
  >
    <a href="/" class="focus-ring flex items-center gap-3 rounded-lg px-2 py-2 text-sm font-semibold text-inherit hover:no-underline">
      <span class="grid h-8 w-8 place-items-center rounded-lg text-white" style="background: var(--accent)">T</span>
      The Second Directory
    </a>

    <nav class="mt-8 space-y-1">
      {#each nav as item (item.href)}
        {@const Icon = item.icon}
        <a
          href={item.href}
          class="focus-ring flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium hover:no-underline"
          class:bg-neutral-100={page.url.pathname === item.href}
          style={page.url.pathname === item.href ? 'color: var(--foreground)' : 'color: var(--muted)'}
        >
          <Icon size={18} />
          {item.label}
        </a>
      {/each}
    </nav>

    <div class="absolute bottom-5 left-4 right-4">
      <p class="truncate text-xs" style="color: var(--muted)">{user.email}</p>
      <button
        class="focus-ring mt-3 inline-flex w-full items-center justify-center gap-2 rounded-lg border px-3 py-2 text-sm font-medium"
        style="border-color: var(--border)"
        type="button"
        onclick={logout}
      >
        <LogOut size={16} />
        Logout
      </button>
    </div>
  </aside>

  <header class="sticky top-0 z-10 border-b px-4 py-3 lg:hidden" style="border-color: var(--border); background: var(--surface)">
    <div class="flex items-center justify-between">
      <a href="/" class="font-semibold text-inherit hover:no-underline">The Second Directory</a>
      <button class="focus-ring rounded-lg border px-3 py-2 text-sm" style="border-color: var(--border)" type="button" onclick={logout}>
        Logout
      </button>
    </div>
    <nav class="mt-3 flex gap-2 overflow-x-auto pb-1">
      {#each nav as item (item.href)}
        <a
          href={item.href}
          class="focus-ring whitespace-nowrap rounded-lg border px-3 py-2 text-sm hover:no-underline"
          style={page.url.pathname === item.href
            ? 'border-color: var(--accent); color: var(--accent)'
            : 'border-color: var(--border); color: var(--muted)'}
        >
          {item.label}
        </a>
      {/each}
    </nav>
  </header>

  <main class="mx-auto max-w-6xl px-4 py-8 lg:ml-64 lg:px-8">
    {@render children()}
  </main>
</div>
