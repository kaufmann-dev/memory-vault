<script lang="ts">
  import { Upload } from '@lucide/svelte';
  import { Button } from '#lib/components/ui/button/index.js';

  let {
    accept,
    onLoad,
    label = 'Upload file'
  }: {
    accept: string;
    onLoad: (text: string) => void;
    label?: string;
  } = $props();

  let input: HTMLInputElement | null = $state(null);

  async function loadFile(event: Event & { currentTarget: HTMLInputElement }) {
    const target = event.currentTarget;
    const file = target.files?.[0];
    if (!file) return;
    onLoad(await file.text());
    target.value = '';
  }
</script>

<input bind:this={input} type="file" {accept} class="hidden" onchange={loadFile} />
<Button type="button" variant="outline" size="sm" onclick={() => input?.click()}>
  <Upload class="size-4" />
  {label}
</Button>
