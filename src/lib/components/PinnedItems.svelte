<script lang="ts">
  import HomePinButton from '#lib/components/HomePinButton.svelte';
  import SimpleLineChart from '#lib/components/SimpleLineChart.svelte';
  import TotpCode from '#lib/components/TotpCode.svelte';
  import { decryptRecord, updateEncryptedRecord } from '#lib/client/records.js';
  import { elapsedDays, progressPercent } from '#lib/day-counter.js';
  import { chartSeries } from '#lib/diagram.js';
  import { sessionDEK } from '#lib/stores/cryptoKey.js';
  import { secretTotp } from '#lib/totp.js';
  import type {
    DayCounterPayload,
    DiagramPayload,
    EncryptedRecord,
    ListEntry,
    ListPayload,
    NotePayload,
    SecretPayload
  } from '#lib/types.js';
  import { get } from 'svelte/store';
  import { onMount } from 'svelte';
  import { Activity, CalendarDays, KeyRound, ListChecks, StickyNote } from '@lucide/svelte';
  import * as Card from '#lib/components/ui/card/index.js';
  import { Checkbox } from '#lib/components/ui/checkbox/index.js';
  import { Label } from '#lib/components/ui/label/index.js';
  import { Progress } from '#lib/components/ui/progress/index.js';

  type ListItem = { type: 'list'; record: EncryptedRecord; payload: ListPayload };
  type NoteItem = { type: 'note'; record: EncryptedRecord; payload: NotePayload };
  type CounterItem = { type: 'day_counter'; record: EncryptedRecord; payload: DayCounterPayload };
  type DiagramItem = { type: 'diagram'; record: EncryptedRecord; payload: DiagramPayload };
  type SecretItem = { type: 'secret'; record: EncryptedRecord; payload: SecretPayload };
  type PinnedItem = ListItem | NoteItem | CounterItem | DiagramItem | SecretItem;

  const pinnableTypes: PinnedItem['type'][] = ['list', 'note', 'day_counter', 'diagram', 'secret'];
  const icons = { list: ListChecks, note: StickyNote, day_counter: CalendarDays, diagram: Activity, secret: KeyRound };

  let { records }: { records: EncryptedRecord[] } = $props();

  let dek: CryptoKey | null = null;
  let items: PinnedItem[] = $state([]);
  let now = $state(Date.now());
  let codes = $state.raw<Record<string, string>>({});

  function title(item: PinnedItem) {
    if (item.type === 'list') return item.payload.title || 'Untitled list';
    if (item.type === 'note') return item.payload.title || 'Note';
    if (item.type === 'day_counter') return item.payload.name;
    if (item.type === 'secret') return item.payload.title || 'Untitled 2FA code';
    return item.payload.title || 'Untitled diagram';
  }

  function href(item: PinnedItem) {
    if (item.type === 'list') return `/lists?id=${item.record.id}`;
    if (item.type === 'note') return `/notes?id=${item.record.id}`;
    if (item.type === 'day_counter') return '/day-counters';
    if (item.type === 'secret') return `/secrets?id=${item.record.id}`;
    return `/diagrams?id=${item.record.id}`;
  }

  function sortItems(list: PinnedItem[]) {
    return [...list].sort(
      (a, b) => pinnableTypes.indexOf(a.type) - pinnableTypes.indexOf(b.type) || title(a).localeCompare(title(b))
    );
  }

  async function decryptPinned(record: EncryptedRecord, key: CryptoKey): Promise<PinnedItem> {
    return { type: record.type, record, payload: await decryptRecord(record, key) } as PinnedItem;
  }

  function isPinned(item: PinnedItem) {
    if (item.type === 'secret') return item.payload.category === 'totp' && item.payload.homePinned;
    return item.payload.homePinned;
  }

  async function refreshCodes() {
    const time = Date.now();
    now = time;
    const secrets = items.filter((item): item is SecretItem => item.type === 'secret');
    if (secrets.length === 0) return;
    const entries = await Promise.all(
      secrets.map(async (item) => [item.record.id, await secretTotp(item.payload, time)] as const)
    );
    codes = Object.fromEntries(entries);
  }

  async function updateItem<T extends PinnedItem>(item: T, payload: T['payload']) {
    if (!dek) return;
    items = items.map((entry) => (entry.record.id === item.record.id ? ({ ...entry, payload } as PinnedItem) : entry));
    await updateEncryptedRecord(item.record.id, item.type, payload, dek);
  }

  async function toggleEntry(item: ListItem, entryId: string) {
    await updateItem(item, {
      ...item.payload,
      entries: item.payload.entries.map((entry) => (entry.id === entryId ? { ...entry, done: !entry.done } : entry))
    });
  }

  async function unpin(item: PinnedItem) {
    if (!dek) return;
    items = items.filter((entry) => entry.record.id !== item.record.id);
    await updateEncryptedRecord(item.record.id, item.type, { ...item.payload, homePinned: false }, dek);
  }

  async function load() {
    const key = get(sessionDEK);
    if (!key) return;
    dek = key;
    const decrypted = await Promise.all(
      records
        .filter((record) => pinnableTypes.includes(record.type as PinnedItem['type']))
        .map((record) => decryptPinned(record, key))
    );
    items = sortItems(decrypted.filter(isPinned));
    await refreshCodes();
  }

  onMount(() => {
    const timer = setInterval(() => void refreshCodes(), 1000);
    void load();
    return () => clearInterval(timer);
  });
</script>

{#snippet entryText(item: ListItem, entry: ListEntry)}
  {@const [first, ...rest] = item.payload.columns}
  {@const details = rest.map((column) => entry.values[column.id]).filter(Boolean)}
  <span class="grid min-w-0 flex-1 gap-0.5 wrap-anywhere">
    <span class="text-sm">{entry.values[first.id]}</span>
    {#if details.length > 0}
      <span class="text-muted-foreground text-xs">{details.join(' · ')}</span>
    {/if}
  </span>
{/snippet}

{#snippet listBody(item: ListItem)}
  {@const openEntries = item.payload.checklist
    ? item.payload.entries.filter((entry) => !entry.done)
    : item.payload.entries}
  {@const completedCount = item.payload.entries.length - openEntries.length}
  {#if openEntries.length === 0}
    <p class="text-muted-foreground text-sm">No open entries</p>
  {:else if item.payload.checklist}
    <ul class="grid gap-2">
      {#each openEntries as entry (entry.id)}
        <li>
          <Label class="flex items-start gap-3 font-normal">
            <Checkbox checked={entry.done} onCheckedChange={() => toggleEntry(item, entry.id)} class="mt-0.5" />
            {@render entryText(item, entry)}
          </Label>
        </li>
      {/each}
    </ul>
  {:else}
    <ul class="grid list-disc gap-2 pl-5">
      {#each openEntries as entry (entry.id)}
        <li class="text-sm">{@render entryText(item, entry)}</li>
      {/each}
    </ul>
  {/if}
  {#if completedCount > 0}
    <p class="text-muted-foreground mt-3 text-xs">{completedCount} completed</p>
  {/if}
{/snippet}

{#snippet noteBody(item: NoteItem)}
  <p class="line-clamp-6 text-sm leading-relaxed wrap-anywhere whitespace-pre-wrap">{item.payload.text}</p>
{/snippet}

{#snippet counterBody(item: CounterItem)}
  {@const progress = progressPercent(item.payload)}
  <p class="text-4xl font-bold tracking-tight">{elapsedDays(item.payload.initiated)}</p>
  <p class="text-muted-foreground mt-1 text-sm font-medium">days elapsed</p>
  {#if progress !== null}
    <Progress value={progress} class="mt-4" />
  {/if}
{/snippet}

{#snippet diagramBody(item: DiagramItem)}
  {#if item.payload.measurements.length === 0}
    <p class="text-muted-foreground text-sm">No measurements yet</p>
  {:else}
    <SimpleLineChart xAxis={item.payload.xAxis} series={chartSeries(item.payload)} height={220} />
  {/if}
{/snippet}

{#snippet secretBody(item: SecretItem)}
  <TotpCode code={codes[item.record.id]} period={item.payload.otpPeriod} {now} />
{/snippet}

{#if items.length > 0}
  <section class="mb-8">
    <h2 class="text-muted-foreground mb-3 text-xs font-medium tracking-wide uppercase">Pinned</h2>
    <div class="grid gap-4 md:grid-cols-2">
      {#each items as item (item.record.id)}
        {@const Icon = icons[item.type]}
        <Card.Root class="min-w-0">
          <Card.Header>
            <Card.Title class="flex min-w-0 items-center gap-2">
              <Icon class="text-muted-foreground size-4 shrink-0" />
              <a href={href(item)} class="focus-visible:ring-ring hover:text-muted-foreground min-w-0 truncate rounded-sm outline-none transition-colors focus-visible:ring-2">
                {title(item)}
              </a>
            </Card.Title>
            {#if item.type === 'day_counter'}
              <Card.Description>Since {item.payload.initiated}</Card.Description>
            {:else if item.type === 'secret' && item.payload.username}
              <Card.Description class="truncate">{item.payload.username}</Card.Description>
            {:else if (item.type === 'list' || item.type === 'diagram') && item.payload.description}
              <Card.Description class="wrap-anywhere">{item.payload.description}</Card.Description>
            {/if}
            <Card.Action>
              <HomePinButton pinned onclick={() => unpin(item)} />
            </Card.Action>
          </Card.Header>
          <Card.Content class="min-w-0">
            {#if item.type === 'list'}
              {@render listBody(item)}
            {:else if item.type === 'note'}
              {@render noteBody(item)}
            {:else if item.type === 'day_counter'}
              {@render counterBody(item)}
            {:else if item.type === 'secret'}
              {@render secretBody(item)}
            {:else}
              {@render diagramBody(item)}
            {/if}
          </Card.Content>
        </Card.Root>
      {/each}
    </div>
  </section>
{/if}
