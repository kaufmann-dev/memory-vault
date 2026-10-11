<script lang="ts">
  import { toast } from 'svelte-sonner';
  import { ChevronDown, Copy } from '@lucide/svelte';
  import * as Collapsible from '#lib/components/ui/collapsible/index.js';
  import { Button } from '#lib/components/ui/button/index.js';
  import { cn } from '#lib/utils.js';

  let { instructions, example }: { instructions: string[]; example: string } = $props();

  let open = $state(false);

  async function copyInstructions() {
    const text = `${instructions.map((line) => `- ${line}`).join('\n')}\n\nExample:\n${example}`;
    try {
      await navigator.clipboard.writeText(text);
      toast.success('Instructions copied.');
    } catch {
      toast.error('Could not copy the instructions.');
    }
  }
</script>

<Collapsible.Root bind:open class="rounded-lg border p-2">
  <div class="flex flex-wrap items-center justify-between gap-2">
    <Collapsible.Trigger>
      {#snippet child({ props })}
        <Button {...props} type="button" variant="ghost" size="sm">
          <ChevronDown class={cn('size-4 transition-transform', open && 'rotate-180')} />
          Format instructions
        </Button>
      {/snippet}
    </Collapsible.Trigger>
    <Button type="button" variant="outline" size="sm" onclick={copyInstructions}>
      <Copy class="size-4" />
      Copy
    </Button>
  </div>
  <Collapsible.Content class="grid gap-3 px-2 pt-2 pb-1">
    <ul class="text-muted-foreground grid list-disc gap-1 pl-5 text-sm">
      {#each instructions as line (line)}
        <li>{line}</li>
      {/each}
    </ul>
    <div class="rounded-lg border p-3">
      <p class="text-muted-foreground mb-2 text-xs font-semibold tracking-wider uppercase">Example</p>
      <pre class="overflow-auto text-xs whitespace-pre-wrap">{example}</pre>
    </div>
  </Collapsible.Content>
</Collapsible.Root>
