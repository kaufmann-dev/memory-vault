<script lang="ts">
  import { page } from '$app/state';
  import { RotateCcw, SearchX, ShieldX, TriangleAlert } from '@lucide/svelte';
  import { Button } from '#lib/components/ui/button/index.js';
  import * as Empty from '#lib/components/ui/empty/index.js';

  // SvelteKit's generic messages ("Not Found", "Internal Error", ...) add nothing to the title.
  const detail = $derived.by(() => {
    const message = page.error?.message;
    const generic = ['Not Found', 'Forbidden', 'Internal Error', `Error: ${page.status}`];
    return message && !generic.includes(message) ? message : null;
  });

  const content = $derived.by(() => {
    if (page.status === 404)
      return {
        icon: SearchX,
        title: 'Page not found',
        description:
          detail ?? "This page doesn't exist. It may have been moved, or the address has a typo."
      };
    if (page.status === 403)
      return {
        icon: ShieldX,
        title: 'Access denied',
        description: detail ?? "You don't have permission to view this page."
      };
    if (page.status < 500)
      return {
        icon: TriangleAlert,
        title: 'Request could not be completed',
        description: detail ?? 'The request was invalid.'
      };
    return {
      icon: TriangleAlert,
      title: 'Something went wrong',
      description: 'An unexpected error occurred. Try again in a moment.'
    };
  });
</script>

<svelte:head>
  <title>{content.title} · Memory Vault</title>
</svelte:head>

<Empty.Root class="min-h-[60vh]">
  <Empty.Header>
    <Empty.Media variant="icon"><content.icon /></Empty.Media>
    <Empty.Title>{content.title}</Empty.Title>
    <Empty.Description>{content.description}</Empty.Description>
  </Empty.Header>
  <Empty.Content>
    <div class="flex flex-wrap justify-center gap-2">
      {#if page.status >= 500}
        <Button onclick={() => location.reload()}><RotateCcw />Try again</Button>
      {/if}
      <Button href="/" variant={page.status >= 500 ? 'outline' : 'default'}>
        Back to home
      </Button>
    </div>
    <p class="text-xs text-muted-foreground">Error {page.status}</p>
  </Empty.Content>
</Empty.Root>
