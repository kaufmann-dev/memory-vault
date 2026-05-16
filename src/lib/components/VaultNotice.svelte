<script lang="ts">
  import { Shield } from '@lucide/svelte';
  let { message = 'Unlock your vault to decrypt this page.' }: { message?: string } = $props();
</script>

<div class="vault-card p-6">
  <div class="flex items-start gap-4">
    <div class="grid h-10 w-10 shrink-0 place-items-center" style="background: var(--accent-light)">
      <Shield size={20} color="var(--accent)" />
    </div>
    <div>
      <h2 class="text-base font-semibold" style="color: var(--foreground)">Vault locked</h2>
      <p class="mt-1 text-sm leading-relaxed" style="color: var(--muted)">{message}</p>
      <a class="focus-ring vault-btn-primary mt-4" href="/login">Unlock vault</a>
    </div>
  </div>
</div>
