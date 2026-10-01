<script lang="ts">
  import * as Sidebar from '#lib/components/ui/sidebar/index.js';
  import { page } from '$app/state';
  import { afterNavigate } from '$app/navigation';
  import type { Component } from 'svelte';

  type NavItem = { href: string; label: string; icon: Component };

  let { items }: { items: NavItem[] } = $props();

  const sidebar = Sidebar.useSidebar();

  function closeMobile() {
    if (sidebar.isMobile) sidebar.setOpenMobile(false);
  }

  // Covers every in-app link (nav items, header logo, footer menu).
  afterNavigate(closeMobile);
</script>

<Sidebar.Menu>
  {#each items as item (item.href)}
    {@const Icon = item.icon}
    <Sidebar.MenuItem>
      <Sidebar.MenuButton isActive={page.url.pathname === item.href} tooltipContent={item.label}>
        {#snippet child({ props })}
          <a href={item.href} {...props} onclick={closeMobile}>
            <Icon />
            <span>{item.label}</span>
          </a>
        {/snippet}
      </Sidebar.MenuButton>
    </Sidebar.MenuItem>
  {/each}
</Sidebar.Menu>
