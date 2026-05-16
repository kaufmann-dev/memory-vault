<script lang="ts">
  let { message = 'Unlock your vault to decrypt this page.' }: { message?: string } = $props();
</script>

<div class="rounded-2xl p-6 shadow-sm" style="background: var(--surface)">
  <h2 class="text-base font-semibold">Vault locked</h2>
  <p class="mt-2 text-sm leading-6" style="color: var(--muted)">{message}</p>
  <a class="mt-4 inline-flex rounded-lg px-4 py-2 text-sm font-medium text-white focus-ring" style="background: var(--accent)" href="/login">
    Unlock
  </a>
</div>
