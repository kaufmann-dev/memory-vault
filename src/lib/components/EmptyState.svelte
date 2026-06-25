<script lang="ts">
  import type { Snippet } from 'svelte';
  import * as Empty from '$lib/components/ui/empty/index.js';

  let {
    title,
    description,
    children
  }: {
    title: string;
    description: string;
    children?: Snippet;
  } = $props();
</script>

<Empty.Root class="border">
  <Empty.Header>
    <Empty.Title>{title}</Empty.Title>
    <Empty.Description>{description}</Empty.Description>
  </Empty.Header>
  {#if children}
    <Empty.Content>
      {@render children()}
    </Empty.Content>
  {/if}
</Empty.Root>
