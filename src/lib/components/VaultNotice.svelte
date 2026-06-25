<script lang="ts">
  import { Shield } from '@lucide/svelte';
  import * as Alert from '$lib/components/ui/alert/index.js';
  import { buttonVariants } from '$lib/components/ui/button/index.js';

  let { message = 'Unlock your vault to decrypt this page.' }: { message?: string } = $props();
</script>

<Alert.Root>
  <Shield class="size-4" />
  <Alert.Title>Vault locked</Alert.Title>
  <Alert.Description class="flex flex-col items-start gap-3">
    <span>{message}</span>
    <a href="/login" class={buttonVariants({ size: 'sm' })}>Unlock vault</a>
  </Alert.Description>
</Alert.Root>
