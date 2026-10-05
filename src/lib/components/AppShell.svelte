<script lang="ts">
  import { forgetRememberedDEK } from '#lib/client/rememberedDevice.js';
  import { lockVault } from '#lib/stores/cryptoKey.js';
  import type { SafeUser } from '#lib/types.js';
  import type { Snippet } from 'svelte';
  import * as Sidebar from '#lib/components/ui/sidebar/index.js';
  import AppSidebarNav from '#lib/components/AppSidebarNav.svelte';
  import * as DropdownMenu from '#lib/components/ui/dropdown-menu/index.js';
  import { Separator } from '#lib/components/ui/separator/index.js';
  import {
    Activity,
    BookOpen,
    CalendarDays,
    ChevronsUpDown,
    Home,
    KeyRound,
    ListChecks,
    LogOut,
    Settings,
    StickyNote
  } from '@lucide/svelte';

  let { user, children }: { user: SafeUser; children: Snippet } = $props();
  let logoutForm: HTMLFormElement;

  const nav = [
    { href: '/', label: 'Home', icon: Home },
    { href: '/diary', label: 'Diary', icon: BookOpen },
    { href: '/notes', label: 'Notes', icon: StickyNote },
    { href: '/lists', label: 'Lists', icon: ListChecks },
    { href: '/diagrams', label: 'Diagrams', icon: Activity },
    { href: '/day-counters', label: 'Milestones', icon: CalendarDays },
    { href: '/secrets', label: 'Secrets', icon: KeyRound },
    { href: '/settings', label: 'Settings', icon: Settings }
  ];

  async function logout() {
    await forgetRememberedDEK(user.email).catch(() => undefined);
    lockVault();
    logoutForm.submit();
  }
</script>

<Sidebar.Provider>
  <Sidebar.Root>
    <Sidebar.Header>
      <Sidebar.Menu>
        <Sidebar.MenuItem>
          <Sidebar.MenuButton size="lg">
            {#snippet child({ props })}
              <a href="/" {...props}>
                <div class="flex aspect-square size-8 items-center justify-center">
                  <img src="/memory-vault.svg" alt="" class="size-8" />
                </div>
                <div class="grid flex-1 text-left text-sm leading-tight">
                  <span class="truncate font-semibold">Memory Vault</span>
                  <span class="text-muted-foreground truncate text-xs">Private &amp; encrypted</span>
                </div>
              </a>
            {/snippet}
          </Sidebar.MenuButton>
        </Sidebar.MenuItem>
      </Sidebar.Menu>
    </Sidebar.Header>

    <Sidebar.Content>
      <Sidebar.Group>
        <Sidebar.GroupContent>
          <AppSidebarNav items={nav} />
        </Sidebar.GroupContent>
      </Sidebar.Group>
    </Sidebar.Content>

    <Sidebar.Footer>
      <Sidebar.Menu>
        <Sidebar.MenuItem>
          <DropdownMenu.Root>
            <DropdownMenu.Trigger>
              {#snippet child({ props })}
                <Sidebar.MenuButton
                  size="lg"
                  class="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
                  {...props}
                >
                  <div class="bg-sidebar-accent text-sidebar-accent-foreground flex aspect-square size-8 items-center justify-center rounded-lg text-xs font-semibold uppercase">
                    {user.email.charAt(0)}
                  </div>
                  <div class="grid flex-1 text-left text-sm leading-tight">
                    <span class="truncate font-medium">{user.name}</span>
                    <span class="text-muted-foreground truncate text-xs">{user.email}</span>
                  </div>
                  <ChevronsUpDown class="ml-auto size-4" />
                </Sidebar.MenuButton>
              {/snippet}
            </DropdownMenu.Trigger>
            <DropdownMenu.Content class="w-(--bits-dropdown-menu-anchor-width) min-w-56 rounded-lg" side="top" align="end">
              <DropdownMenu.Label class="text-muted-foreground text-xs font-normal">{user.email}</DropdownMenu.Label>
              <DropdownMenu.Separator />
              <DropdownMenu.Item>
                {#snippet child({ props })}
                  <a href="/settings" {...props}>
                    <Settings />
                    Settings
                  </a>
                {/snippet}
              </DropdownMenu.Item>
              <DropdownMenu.Separator />
              <DropdownMenu.Item onSelect={logout}>
                <LogOut />
                Log out
              </DropdownMenu.Item>
            </DropdownMenu.Content>
          </DropdownMenu.Root>
        </Sidebar.MenuItem>
      </Sidebar.Menu>
    </Sidebar.Footer>
    <Sidebar.Rail />
  </Sidebar.Root>

  <form bind:this={logoutForm} method="POST" action="/auth/logout" target="_top" hidden></form>

  <Sidebar.Inset class="min-w-0">
    <header class="bg-background sticky top-0 z-10 flex h-14 shrink-0 items-center gap-2 border-b px-4">
      <Sidebar.Trigger class="-ml-1" />
      <Separator orientation="vertical" class="mr-1 data-[orientation=vertical]:h-4" />
      <a href="/" class="flex items-center gap-2 font-semibold lg:hidden">
        <img src="/memory-vault.svg" alt="" class="size-6" />
        Memory Vault
      </a>
    </header>
    <main class="mx-auto w-full max-w-6xl flex-1 px-4 py-8 lg:px-8">
      {@render children()}
    </main>
  </Sidebar.Inset>
</Sidebar.Provider>
