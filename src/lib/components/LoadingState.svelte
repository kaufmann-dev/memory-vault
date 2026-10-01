<script lang="ts">
  import { Spinner } from '#lib/components/ui/spinner/index.js';
  import { cn } from '#lib/utils.js';

  let { message, class: className }: { message: string; class?: string } = $props();
</script>

<p class={cn('text-muted-foreground flex items-center gap-2 text-sm', className)}>
  <Spinner class="size-4" />
  {message}
</p>
