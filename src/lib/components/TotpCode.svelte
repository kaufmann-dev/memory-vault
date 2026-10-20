<script lang="ts">
  import { copyToClipboard } from '#lib/client/clipboard.js';
  import { formatTotpCode, normalizeTotpPeriod, totpSecondsLeft } from '#lib/totp.js';
  import { Copy } from '@lucide/svelte';
  import { Button } from '#lib/components/ui/button/index.js';
  import { Progress } from '#lib/components/ui/progress/index.js';

  let { code, period, now }: { code: string | undefined; period: number; now: number } = $props();

  let normalizedPeriod = $derived(normalizeTotpPeriod(period));
  let secondsLeft = $derived(totpSecondsLeft(normalizedPeriod, now));
  let expiring = $derived(secondsLeft <= 5);
</script>

<div class="flex items-center gap-3">
  <div class="grid min-w-0 flex-1 gap-1.5">
    {#if code}
      <span class={['font-mono text-2xl font-semibold tracking-wider tabular-nums', expiring && 'text-destructive']}>
        {formatTotpCode(code)}
      </span>
    {:else if code === ''}
      <span class="text-destructive text-sm">Invalid secret key</span>
    {:else}
      <span class="text-muted-foreground font-mono text-2xl font-semibold tracking-wider">··· ···</span>
    {/if}
    <div class="flex items-center gap-2">
      <Progress
        value={(secondsLeft / normalizedPeriod) * 100}
        class={['h-1 max-w-40', expiring && '[&_[data-slot=progress-indicator]]:bg-destructive']}
        aria-label="Time until the next code"
      />
      <span class={['text-muted-foreground text-xs tabular-nums', expiring && 'text-destructive']}>{secondsLeft}s</span>
    </div>
  </div>
  <Button type="button" variant="ghost" size="sm" onclick={() => copyToClipboard(code ?? '', 'Code')} disabled={!code}>
    <Copy class="size-4" /> Copy
  </Button>
</div>
