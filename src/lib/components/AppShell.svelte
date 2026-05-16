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
    Menu,
    Settings,
    UsersRound,
    X
  } from '@lucide/svelte';
  import { fly, fade } from 'svelte/transition';
  import { cubicOut } from 'svelte/easing';

  let { user, children }: { user: SafeUser; children: Snippet } = $props();

  let drawerOpen = $state(false);

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

  function toggleDrawer() {
    drawerOpen = !drawerOpen;
  }

  function closeDrawer() {
    drawerOpen = false;
  }

  $effect(() => {
    if (typeof document !== 'undefined') {
      document.body.style.overflow = drawerOpen ? 'hidden' : '';
    }
  });
</script>

<svelte:window onkeydown={(e) => { if (e.key === 'Escape') closeDrawer(); }} />

<div class="min-h-screen">
  <!-- Desktop Sidebar -->
  <aside class="fixed inset-y-0 left-0 hidden w-64 lg:block" style="background: var(--surface); box-shadow: 1px 0 3px rgba(0,0,0,0.04)">
    <div class="flex h-full flex-col px-4 py-5">
      <a href="/" class="focus-ring flex items-center gap-3 rounded-xl px-2 py-2 text-sm font-bold text-inherit hover:no-underline">
        <span class="grid h-9 w-9 place-items-center rounded-xl text-base font-bold text-white" style="background: var(--accent)">M</span>
        Memory Vault
      </a>

      <nav class="mt-8 space-y-1">
        {#each nav as item (item.href)}
          {@const Icon = item.icon}
          {@const isActive = page.url.pathname === item.href}
          <a href={item.href} class="focus-ring vault-nav-item" data-active={isActive}>
            <Icon size={18} />
            {item.label}
          </a>
        {/each}
      </nav>

      <div class="mt-auto border-t pt-4" style="border-color: var(--border)">
        <p class="truncate text-xs" style="color: var(--muted)">{user.email}</p>
        <button class="focus-ring vault-btn-secondary mt-3 w-full" type="button" onclick={logout}>
          <LogOut size={16} />
          Logout
        </button>
      </div>
    </div>
  </aside>

  <!-- Mobile Header -->
  <header class="sticky top-0 z-10 border-b px-4 py-3 lg:hidden" style="border-color: var(--border); background: var(--surface)">
    <div class="flex items-center justify-between">
      <a href="/" class="flex items-center gap-2.5 font-bold text-inherit hover:no-underline">
        <span class="grid h-8 w-8 place-items-center rounded-lg text-sm font-bold text-white" style="background: var(--accent)">M</span>
        Memory Vault
      </a>
      <button class="focus-ring vault-btn-ghost" type="button" onclick={toggleDrawer} aria-label="Open menu">
        <Menu size={20} />
      </button>
    </div>
  </header>

  <!-- Mobile Drawer -->
  {#if drawerOpen}
    <div class="fixed inset-0 z-50 lg:hidden" role="dialog" aria-modal="true" transition:fade={{ duration: 200 }}>
      <div
        class="absolute inset-0 bg-black/40 backdrop-blur-sm"
        role="button"
        tabindex="-1"
        aria-label="Close menu"
        onclick={closeDrawer}
        onkeydown={(e) => { if (e.key === 'Enter' || e.key === ' ') closeDrawer(); }}
      ></div>
      <aside
        class="absolute right-0 top-0 h-full w-72"
        style="background: var(--surface); box-shadow: -4px 0 24px rgba(0,0,0,0.08)"
        transition:fly={{ x: 300, duration: 300, easing: cubicOut }}
      >
        <div class="flex items-center justify-between border-b px-4 py-3" style="border-color: var(--border)">
          <span class="font-bold text-sm">Menu</span>
          <button class="focus-ring vault-btn-ghost" type="button" onclick={closeDrawer} aria-label="Close menu">
            <X size={18} />
          </button>
        </div>

        <nav class="p-3 space-y-0.5">
          {#each nav as item (item.href)}
            {@const Icon = item.icon}
            {@const isActive = page.url.pathname === item.href}
            <a href={item.href} class="focus-ring vault-nav-item" data-active={isActive} onclick={closeDrawer}>
              <Icon size={18} />
              {item.label}
            </a>
          {/each}
        </nav>

        <div class="absolute bottom-0 left-0 right-0 border-t p-4" style="border-color: var(--border)">
          <p class="truncate text-xs" style="color: var(--muted)">{user.email}</p>
          <button class="focus-ring vault-btn-secondary mt-3 w-full" type="button" onclick={() => { closeDrawer(); logout(); }}>
            <LogOut size={16} />
            Logout
          </button>
        </div>
      </aside>
    </div>
  {/if}

  <main class="mx-auto max-w-6xl px-4 py-8 lg:ml-64 lg:px-8">
    {@render children()}
  </main>
</div>
