<script lang="ts">
  import { House } from '@lucide/svelte';
  import { Button } from '#lib/components/ui/button/index.js';

  let { pinned, onclick }: { pinned: boolean; onclick: () => void } = $props();

  let label = $derived(pinned ? 'Unpin from home' : 'Pin to home');
</script>

<Button
  variant={pinned ? 'secondary' : 'ghost'}
  size="icon"
  class={[pinned && 'ring-border ring-1']}
  {onclick}
  aria-label={label}
  aria-pressed={pinned}
  title={label}
>
  <House class={['size-4', pinned && 'fill-current']} />
</Button>
