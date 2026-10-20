<script lang="ts">
  import SimpleLineChart from '#lib/components/SimpleLineChart.svelte';
  import { decryptRecord, updateEncryptedRecord } from '#lib/client/records.js';
  import { elapsedDays, progressPercent } from '#lib/day-counter.js';
  import { chartSeries, normalizeDiagramPayload, xAxis } from '#lib/diagram.js';
  import { sessionDEK } from '#lib/stores/cryptoKey.js';
  import type {
    DayCounterPayload,
    DiagramPayload,
    EncryptedRecord,
    ListPayload,
    NotePayload
  } from '#lib/types.js';
  import { get } from 'svelte/store';
  import { onMount } from 'svelte';
  import { Activity, CalendarDays, House, ListChecks, StickyNote } from '@lucide/svelte';
  import * as Card from '#lib/components/ui/card/index.js';
  import { Button } from '#lib/components/ui/button/index.js';
  import { Checkbox } from '#lib/components/ui/checkbox/index.js';
  import { Label } from '#lib/components/ui/label/index.js';
  import { Progress } from '#lib/components/ui/progress/index.js';

  type ListItem = { type: 'list'; record: EncryptedRecord; payload: ListPayload };
  type NoteItem = { type: 'note'; record: EncryptedRecord; payload: NotePayload };
  type CounterItem = { type: 'day_counter'; record: EncryptedRecord; payload: DayCounterPayload };
  type DiagramItem = { type: 'diagram'; record: EncryptedRecord; payload: DiagramPayload };
  type PinnedItem = ListItem | NoteItem | CounterItem | DiagramItem;

  const pinnableTypes: PinnedItem['type'][] = ['list', 'note', 'day_counter', 'diagram'];
  const icons = { list: ListChecks, note: StickyNote, day_counter: CalendarDays, diagram: Activity };

  let { records }: { records: EncryptedRecord[] } = $props();

  let dek: CryptoKey | null = null;
  let items: PinnedItem[] = $state([]);

  function title(item: PinnedItem) {
    if (item.type === 'list') return item.payload.title || 'Untitled list';
    if (item.type === 'note') return item.payload.title || 'Note';
    if (item.type === 'day_counter') return item.payload.name;
    return item.payload.title || 'Untitled diagram';
  }

  function href(item: PinnedItem) {
    if (item.type === 'list') return `/lists?id=${item.record.id}`;
    if (item.type === 'note') return `/notes?id=${item.record.id}`;
    if (item.type === 'day_counter') return '/day-counters';
    return `/diagrams?id=${item.record.id}`;
  }

  function sortItems(list: PinnedItem[]) {
    return [...list].sort(
      (a, b) => pinnableTypes.indexOf(a.type) - pinnableTypes.indexOf(b.type) || title(a).localeCompare(title(b))
    );
  }

  async function decryptPinned(record: EncryptedRecord, key: CryptoKey): Promise<PinnedItem> {
    const payload = await decryptRecord<PinnedItem['payload']>(record, key);
    if (record.type === 'diagram') {
      return { type: 'diagram', record, payload: normalizeDiagramPayload(payload as DiagramPayload) };
    }
    return { type: record.type, record, payload } as PinnedItem;
  }

  async function updateItem<T extends PinnedItem>(item: T, payload: T['payload']) {
    if (!dek) return;
    items = items.map((entry) => (entry.record.id === item.record.id ? ({ ...entry, payload } as PinnedItem) : entry));
    await updateEncryptedRecord(item.record.id, item.type, payload, dek);
  }

  async function toggleTask(item: ListItem, taskId: string) {
    await updateItem(item, {
      ...item.payload,
      tasks: item.payload.tasks.map((task) => (task.id === taskId ? { ...task, done: !task.done } : task))
    });
  }

  async function unpin(item: PinnedItem) {
    if (!dek) return;
    items = items.filter((entry) => entry.record.id !== item.record.id);
    await updateEncryptedRecord(item.record.id, item.type, { ...item.payload, homePinned: false }, dek);
  }

  onMount(async () => {
    const key = get(sessionDEK);
    if (!key) return;
    dek = key;
    const decrypted = await Promise.all(
      records
        .filter((record) => pinnableTypes.includes(record.type as PinnedItem['type']))
        .map((record) => decryptPinned(record, key))
    );
    items = sortItems(decrypted.filter((item) => item.payload.homePinned));
  });
</script>

{#snippet listBody(item: ListItem)}
  {@const openTasks = item.payload.checklist ? item.payload.tasks.filter((task) => !task.done) : item.payload.tasks}
  {@const completedCount = item.payload.tasks.length - openTasks.length}
  {#if openTasks.length === 0}
    <p class="text-muted-foreground text-sm">No open tasks</p>
  {:else if item.payload.checklist}
    <ul class="grid gap-2">
      {#each openTasks as task (task.id)}
        <li>
          <Label class="flex items-start gap-3 font-normal">
            <Checkbox checked={task.done} onCheckedChange={() => toggleTask(item, task.id)} class="mt-0.5" />
            <span class="min-w-0 flex-1 text-sm wrap-anywhere">{task.text}</span>
          </Label>
        </li>
      {/each}
    </ul>
  {:else}
    <ul class="grid list-disc gap-2 pl-5">
      {#each openTasks as task (task.id)}
        <li class="text-sm wrap-anywhere">{task.text}</li>
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
    <SimpleLineChart xAxis={xAxis(item.payload)} series={chartSeries(item.payload)} height={220} />
  {/if}
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
            {:else if (item.type === 'list' || item.type === 'diagram') && item.payload.description}
              <Card.Description class="wrap-anywhere">{item.payload.description}</Card.Description>
            {/if}
            <Card.Action>
              <Button
                variant="ghost"
                size="icon"
                class="text-primary hover:text-primary"
                onclick={() => unpin(item)}
                aria-label="Unpin from home"
                aria-pressed="true"
              >
                <House class="size-4" />
              </Button>
            </Card.Action>
          </Card.Header>
          <Card.Content class="min-w-0">
            {#if item.type === 'list'}
              {@render listBody(item)}
            {:else if item.type === 'note'}
              {@render noteBody(item)}
            {:else if item.type === 'day_counter'}
              {@render counterBody(item)}
            {:else}
              {@render diagramBody(item)}
            {/if}
          </Card.Content>
        </Card.Root>
      {/each}
    </div>
  </section>
{/if}
