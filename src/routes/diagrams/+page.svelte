<script lang="ts">
  import EmptyState from '#lib/components/EmptyState.svelte';
  import LoadingState from '#lib/components/LoadingState.svelte';
  import EntryModal from '#lib/components/EntryModal.svelte';
  import PageHeader from '#lib/components/PageHeader.svelte';
  import SimpleLineChart from '#lib/components/SimpleLineChart.svelte';
  import CollectionNav from '#lib/components/CollectionNav.svelte';
  import HomePinButton from '#lib/components/HomePinButton.svelte';
  import BatchImportHelp from '#lib/components/BatchImportHelp.svelte';
  import TextFileButton from '#lib/components/TextFileButton.svelte';
  import { localDateTime } from '#lib/date.js';
  import {
    createEncryptedRecord,
    decryptRecords,
    deleteEncryptedRecord,
    fetchEncryptedRecords,
    randomId,
    updateEncryptedRecord
  } from '#lib/client/records.js';
  import {
    chartSeries,
    defaultXAxis,
    isNumberAxis,
    parseDiagramImport,
    SERIES_COLORS,
    sortedMeasurements,
    xLabel
  } from '#lib/diagram.js';
  import { sessionDEK } from '#lib/stores/cryptoKey.js';
  import type { DiagramField, DiagramMeasurement, DiagramPayload, EncryptedRecord } from '#lib/types.js';
  import { page } from '$app/state';
  import { get } from 'svelte/store';
  import { onMount } from 'svelte';
  import { Pencil, Plus, Trash2 } from '@lucide/svelte';
  import * as Card from '#lib/components/ui/card/index.js';
  import * as Select from '#lib/components/ui/select/index.js';
  import { Button } from '#lib/components/ui/button/index.js';
  import { Input } from '#lib/components/ui/input/index.js';
  import { Textarea } from '#lib/components/ui/textarea/index.js';
  import { Label } from '#lib/components/ui/label/index.js';
  import { Checkbox } from '#lib/components/ui/checkbox/index.js';

  type DiagramItem = {
    record: EncryptedRecord;
    payload: DiagramPayload;
  };

  type MeasurementForm = {
    date: string;
    x: number | null | undefined;
    values: Record<string, number | null>;
  };

  type MeasurementEntry = {
    id: string;
    label: string;
    value: string;
    color: string;
  };

  type DiagramMode = 'single' | 'batch';

  const measurementNumberFormat = new Intl.NumberFormat(undefined, { maximumFractionDigits: 2 });
  const emptyField = (): DiagramField => ({ id: randomId(), label: '', unit: '', color: SERIES_COLORS[0] });
  const emptyDiagram = (): DiagramPayload => ({
    title: '',
    description: '',
    xAxis: defaultXAxis(),
    fields: [{ ...emptyField(), label: 'Value' }],
    measurements: [],
    relativeToPeak: false
  });
  const diagramBatchInstructions = [
    'Paste exactly one JSON object that defines the whole diagram: its settings, series, and measurements.',
    '"title" is required and must be a non-empty string; "description" is optional.',
    '"xAxis" is required. "type" is "datetime" for calendar dates and times or "number" for numeric x values such as minutes since a dose; "label" is required; "unit" is optional, e.g. "min".',
    '"relativeToPeak" is optional (default false). Set it to true to plot each series as a percentage of its own peak, which helps when series have very different magnitudes.',
    '"series" is required: a non-empty array of objects with a unique "name", an optional "unit", and an optional "color" written as #rrggbb.',
    '"measurements" is optional: an array where each object is one point on the x axis.',
    'On a datetime axis, each measurement needs "date" as local YYYY-MM-DD HH:mm text, such as 2024-05-01 08:30. On a number axis, it needs "x" as a plain number instead.',
    '"values" maps series names to plain numbers with dot decimals and no units. Omit a series or use null to skip it, but every measurement needs at least one number.',
    'No other keys are allowed anywhere. If anything is invalid, nothing is saved.'
  ];
  const diagramBatchExample = JSON.stringify(
    {
      title: 'Morning vitals',
      description: 'Measured right after waking up.',
      xAxis: { type: 'datetime', label: 'Date' },
      relativeToPeak: false,
      series: [
        { name: 'Weight', unit: 'kg', color: '#2563eb' },
        { name: 'Resting heart rate', unit: 'bpm' }
      ],
      measurements: [
        { date: '2024-05-01 07:15', values: { Weight: 72.4, 'Resting heart rate': 58 } },
        { date: '2024-05-02 07:20', values: { Weight: 72.1 } }
      ]
    },
    null,
    2
  );
  const emptyMeasurement = (fields: DiagramField[] = []): MeasurementForm => ({
    date: localDateTime(),
    x: null,
    values: Object.fromEntries(fields.map((field) => [field.id, null]))
  });

  let dek: CryptoKey | null = null;
  let loading = $state(true);
  let diagrams: DiagramItem[] = $state([]);
  let selectedId: string | null = $state(null);
  let editingId: string | null = $state(null);
  let editingMeasurementId: string | null = $state(null);
  let diagramForm = $state(emptyDiagram());
  let measurementForm = $state(emptyMeasurement());
  let diagramMode = $state<DiagramMode>('single');
  let batchText = $state('');
  let batchError = $state('');
  let diagramFormOpen = $state(false);
  let measurementFormOpen = $state(false);

  let batchActive = $derived(!editingId && diagramMode === 'batch');

  let selected = $derived(diagrams.find((diagram) => diagram.record.id === selectedId) ?? diagrams[0] ?? null);

  let navItems = $derived(
    diagrams.map((item) => ({
      id: item.record.id,
      label: item.payload.title || 'Untitled diagram',
      measurementCount: item.payload.measurements.length
    }))
  );

  function resetMeasurement(payload: DiagramPayload | null = selected?.payload ?? null) {
    measurementForm = emptyMeasurement(payload?.fields ?? []);
    editingMeasurementId = null;
  }

  function selectDiagram(item: DiagramItem) {
    selectedId = item.record.id;
    resetMeasurement(item.payload);
    measurementFormOpen = false;
  }

  function startEdit(item: DiagramItem) {
    editingId = item.record.id;
    diagramForm = {
      ...item.payload,
      xAxis: { ...item.payload.xAxis },
      fields: item.payload.fields.map((field) => ({ ...field })),
      measurements: item.payload.measurements.map((measurement) => ({
        ...measurement,
        values: { ...measurement.values }
      }))
    };
    diagramFormOpen = true;
  }

  function resetDiagramForm() {
    editingId = null;
    diagramForm = emptyDiagram();
    diagramMode = 'single';
    batchText = '';
    batchError = '';
  }

  function cancelEdit() {
    resetDiagramForm();
    diagramFormOpen = false;
  }

  function openCreate() {
    resetDiagramForm();
    diagramFormOpen = true;
  }

  function setDiagramMode(mode: DiagramMode) {
    diagramMode = mode;
    batchError = '';
  }

  function addFormField() {
    diagramForm.fields = [
      ...diagramForm.fields,
      { ...emptyField(), color: SERIES_COLORS[diagramForm.fields.length % SERIES_COLORS.length] }
    ];
  }

  function removeFormField(fieldId: string) {
    if (diagramForm.fields.length === 1) return;
    diagramForm.fields = diagramForm.fields.filter((field) => field.id !== fieldId);
    diagramForm.measurements = diagramForm.measurements.map((measurement) => {
      const { [fieldId]: _removed, ...values } = measurement.values;
      return { ...measurement, values };
    });
  }

  function setXAxisType(type: 'datetime' | 'number') {
    const previousLabel = diagramForm.xAxis.label.trim();
    diagramForm.xAxis.type = type;
    if (type === 'number' && (!previousLabel || previousLabel === 'Date')) {
      diagramForm.xAxis.label = 'Minutes';
      diagramForm.xAxis.unit = 'min';
    }
    if (type === 'datetime' && (!previousLabel || previousLabel === 'Minutes' || previousLabel === 'X')) {
      diagramForm.xAxis.label = 'Date';
      diagramForm.xAxis.unit = '';
    }
  }

  async function loadDiagrams() {
    if (!dek) return;
    loading = true;
    const records = await fetchEncryptedRecords('diagram');
    diagrams = await decryptRecords<DiagramPayload>(records, dek);
    if (!selectedId) {
      const linkedId = page.url.searchParams.get('id');
      const initial = diagrams.find((diagram) => diagram.record.id === linkedId) ?? diagrams[0];
      if (initial) {
        selectedId = initial.record.id;
        resetMeasurement(initial.payload);
      }
    }
    loading = false;
  }

  async function saveDiagram() {
    if (!dek || !diagramForm.title.trim()) return;

    const fields = diagramForm.fields
      .map((field, index) => ({
        ...field,
        label: field.label.trim(),
        unit: field.unit.trim(),
        color: field.color || SERIES_COLORS[index % SERIES_COLORS.length]
      }))
      .filter((field) => field.label);
    if (fields.length === 0) return;

    const payload: DiagramPayload = {
      ...diagramForm,
      title: diagramForm.title.trim(),
      description: diagramForm.description.trim(),
      xAxis: {
        type: diagramForm.xAxis?.type ?? 'datetime',
        label: diagramForm.xAxis?.label.trim() || (diagramForm.xAxis?.type === 'number' ? 'Minutes' : 'Date'),
        unit: diagramForm.xAxis.unit.trim()
      },
      fields
    };

    if (editingId) {
      const id = editingId;
      diagrams = diagrams.map((d) => (d.record.id === id ? { ...d, payload } : d));
      await updateEncryptedRecord(id, 'diagram', payload, dek);
      selectedId = id;
    } else {
      const record = await createEncryptedRecord('diagram', payload, dek);
      diagrams = [...diagrams, { record, payload }];
      selectedId = record.id;
    }

    cancelEdit();
  }

  async function saveBatchDiagram() {
    if (!dek) return;
    batchError = '';
    let payload: DiagramPayload;
    try {
      payload = parseDiagramImport(batchText, localDateTime());
    } catch (error) {
      batchError = error instanceof Error ? error.message : 'The diagram JSON could not be imported.';
      return;
    }

    try {
      const record = await createEncryptedRecord('diagram', payload, dek);
      diagrams = [...diagrams, { record, payload }];
      selectedId = record.id;
    } catch (error) {
      batchError = error instanceof Error ? error.message : 'The diagram could not be saved.';
      return;
    }

    cancelEdit();
  }

  async function removeDiagram(item: DiagramItem) {
    if (!confirm('Delete this diagram?')) return;
    diagrams = diagrams.filter((d) => d.record.id !== item.record.id);
    selectedId = null;
    cancelEdit();
    await deleteEncryptedRecord(item.record.id);
  }

  function openCreateMeasurement(item: DiagramItem) {
    editingMeasurementId = null;
    measurementForm = emptyMeasurement(item.payload.fields);
    measurementFormOpen = true;
  }

  function openEditMeasurement(item: DiagramItem, measurement: DiagramMeasurement) {
    editingMeasurementId = measurement.id;
    measurementForm = {
      date: measurement.date,
      x: measurement.x,
      values: Object.fromEntries(item.payload.fields.map((field) => [field.id, measurement.values[field.id] ?? null]))
    };
    measurementFormOpen = true;
  }

  function closeMeasurementForm(item: DiagramItem) {
    measurementFormOpen = false;
    resetMeasurement(item.payload);
  }

  async function saveMeasurement(item: DiagramItem) {
    if (!dek) return;
    const xValue = measurementForm.x;
    if (isNumberAxis(item.payload) && (xValue === null || xValue === undefined || Number.isNaN(xValue))) return;
    const values = Object.fromEntries(
      item.payload.fields.map((field) => {
        const value = measurementForm.values[field.id];
        return [field.id, value === undefined || value === null || Number.isNaN(value) ? null : value];
      })
    );
    if (!Object.values(values).some((value) => value !== null)) return;

    const measurement: DiagramMeasurement = {
      id: editingMeasurementId ?? randomId(),
      date: measurementForm.date,
      x: isNumberAxis(item.payload) ? (xValue ?? null) : null,
      values
    };
    const measurements = editingMeasurementId
      ? item.payload.measurements.map((existing) => (existing.id === editingMeasurementId ? measurement : existing))
      : [...item.payload.measurements, measurement];

    const payload = { ...item.payload, measurements };
    diagrams = diagrams.map((d) => (d.record.id === item.record.id ? { ...d, payload } : d));
    await updateEncryptedRecord(item.record.id, 'diagram', payload, dek);
    resetMeasurement(payload);
    measurementFormOpen = false;
  }

  async function toggleHomePinned(item: DiagramItem) {
    if (!dek) return;
    const payload = { ...item.payload, homePinned: !item.payload.homePinned };
    diagrams = diagrams.map((d) => (d.record.id === item.record.id ? { ...d, payload } : d));
    await updateEncryptedRecord(item.record.id, 'diagram', payload, dek);
  }

  async function removeMeasurement(item: DiagramItem, measurementId: string) {
    if (!dek) return;
    const payload = {
      ...item.payload,
      measurements: item.payload.measurements.filter((measurement) => measurement.id !== measurementId)
    };
    diagrams = diagrams.map((d) => (d.record.id === item.record.id ? { ...d, payload } : d));
    await updateEncryptedRecord(item.record.id, 'diagram', payload, dek);
  }

  function measurementHeading(payload: DiagramPayload) {
    return isNumberAxis(payload) ? payload.xAxis.label : 'Date';
  }

  function measurementEntries(payload: DiagramPayload, measurement: DiagramMeasurement): MeasurementEntry[] {
    return payload.fields.flatMap((field) => {
      const value = measurement.values[field.id];
      if (value === null || value === undefined) return [];
      return [
        {
          id: field.id,
          label: field.unit ? `${field.label} (${field.unit})` : field.label,
          value: `${measurementNumberFormat.format(value)}${field.unit ? ` ${field.unit}` : ''}`,
          color: field.color
        }
      ];
    });
  }

  onMount(async () => {
    dek = get(sessionDEK);
    if (!dek) {
      loading = false;
      return;
    }
    await loadDiagrams();
  });
</script>

<PageHeader title="Diagrams" description="Encrypted custom measurements, decrypted in the browser and rendered locally.">
  <Button onclick={openCreate}>
    <Plus class="size-4" />
    New
  </Button>
</PageHeader>

{#snippet diagramRow(item: (typeof navItems)[number])}
  <span class="min-w-0 flex-1 truncate text-left">{item.label}</span>
  <span class="text-muted-foreground ml-auto shrink-0 text-xs whitespace-nowrap">
    {item.measurementCount} {item.measurementCount === 1 ? 'point' : 'points'}
  </span>
{/snippet}

<div class="grid gap-6 md:grid-cols-[16rem_minmax(0,1fr)]">
  <aside class="min-w-0">
    <CollectionNav
      items={navItems}
      selected={selected?.record.id ?? null}
      onSelect={(id) => {
        const item = diagrams.find((diagram) => diagram.record.id === id);
        if (item) selectDiagram(item);
      }}
      row={diagramRow}
      searchPlaceholder="Search diagrams"
      emptyText="No diagrams found."
      ariaLabel="Select diagram"
    />
  </aside>

  <section class="min-w-0">
    {#if loading}
      <LoadingState message="Decrypting diagrams…" />
    {:else if !selected}
      <EmptyState title="No diagrams yet" description="Create a diagram to start tracking measurements." />
    {:else}
      <div class="grid gap-6">
        <Card.Root class="min-w-0">
          <Card.Header>
            <Card.Title class="text-lg wrap-anywhere">{selected.payload.title}</Card.Title>
            {#if selected.payload.description}
              <Card.Description class="wrap-anywhere">{selected.payload.description}</Card.Description>
            {/if}
            <Card.Action class="flex gap-1">
              <Button variant="ghost" size="icon" onclick={() => openCreateMeasurement(selected)} aria-label="Add measurement">
                <Plus class="size-4" />
              </Button>
              <HomePinButton pinned={selected.payload.homePinned ?? false} onclick={() => toggleHomePinned(selected)} />
              <Button variant="ghost" size="icon" onclick={() => startEdit(selected)} aria-label="Edit diagram">
                <Pencil class="size-4" />
              </Button>
              <Button
                variant="ghost"
                size="icon"
                class="text-destructive hover:text-destructive"
                onclick={() => removeDiagram(selected)}
                aria-label="Delete diagram"
              >
                <Trash2 class="size-4" />
              </Button>
            </Card.Action>
          </Card.Header>
          <Card.Content class="min-w-0">
            <SimpleLineChart
              xAxis={selected.payload.xAxis}
              series={chartSeries(selected.payload)}
            />
          </Card.Content>
        </Card.Root>

        <section>
          <h2 class="mb-3 text-lg font-semibold">Measurements</h2>
          {#if selected.payload.measurements.length === 0}
            <EmptyState title="No measurements yet" description="Add a measurement above to populate this chart." />
          {:else}
            <div class="grid gap-3">
              {#each sortedMeasurements(selected.payload) as measurement (measurement.id)}
                {@const entries = measurementEntries(selected.payload, measurement)}
                <Card.Root class="gap-4 px-4 py-4 sm:px-5">
                  <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                      <p class="text-muted-foreground text-xs font-semibold tracking-[0.12em] uppercase">
                        {measurementHeading(selected.payload)}
                      </p>
                      <p class="mt-1 text-sm font-medium wrap-anywhere sm:text-base">{xLabel(selected.payload, measurement)}</p>
                    </div>
                    <div class="flex items-center gap-1 self-start">
                      <Button
                        variant="ghost"
                        size="icon"
                        class="size-8"
                        onclick={() => openEditMeasurement(selected, measurement)}
                        aria-label="Edit measurement"
                      >
                        <Pencil class="size-4" />
                      </Button>
                      <Button
                        variant="ghost"
                        size="icon"
                        class="text-muted-foreground hover:text-destructive size-8"
                        onclick={() => removeMeasurement(selected, measurement.id)}
                        aria-label="Delete measurement"
                      >
                        <Trash2 class="size-4" />
                      </Button>
                    </div>
                  </div>

                  <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                    {#each entries as entry (entry.id)}
                      <div class="bg-muted/20 rounded-xl border px-3 py-2">
                        <div class="flex items-start gap-2">
                          <span class="mt-1 size-2.5 shrink-0 rounded-full" style="background: {entry.color}"></span>
                          <div class="min-w-0">
                            <p class="text-muted-foreground text-xs font-semibold wrap-anywhere">{entry.label}</p>
                            <p class="mt-1 text-sm font-medium wrap-anywhere">{entry.value}</p>
                          </div>
                        </div>
                      </div>
                    {/each}
                  </div>
                </Card.Root>
              {/each}
            </div>
          {/if}
        </section>
      </div>
    {/if}
  </section>
</div>

<EntryModal
  open={diagramFormOpen}
  title={editingId ? 'Edit diagram' : batchActive ? 'Batch create diagram' : 'New diagram'}
  description={batchActive
    ? 'Paste one JSON object that defines the whole diagram, including its measurements.'
    : 'Define the chart and the numeric series you want to track.'}
  onClose={cancelEdit}
>
  <form
    class="grid gap-4"
    onsubmit={(event) => {
      event.preventDefault();
      if (batchActive) {
        saveBatchDiagram();
      } else {
        saveDiagram();
      }
    }}
  >
    {#if !editingId}
      <div class="bg-muted inline-flex gap-1 rounded-lg p-1">
        <Button variant={diagramMode === 'single' ? 'default' : 'ghost'} size="sm" onclick={() => setDiagramMode('single')}>
          Single
        </Button>
        <Button variant={diagramMode === 'batch' ? 'default' : 'ghost'} size="sm" onclick={() => setDiagramMode('batch')}>
          Batch
        </Button>
      </div>
    {/if}

    {#if batchActive}
      <BatchImportHelp instructions={diagramBatchInstructions} example={diagramBatchExample} />
      <div class="grid gap-2">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <Label for="diagram-json">Diagram JSON</Label>
          <TextFileButton accept=".json,application/json" onLoad={(text) => (batchText = text)} />
        </div>
        <Textarea id="diagram-json" bind:value={batchText} spellcheck="false" class="min-h-60 font-mono text-xs" />
      </div>
      {#if batchError}
        <p class="text-destructive text-sm font-medium">{batchError}</p>
      {/if}
      <div class="flex justify-end">
        <Button type="submit">Create diagram</Button>
      </div>
    {:else}
      <div class="grid gap-2">
        <Label for="diagram-title">Title</Label>
        <Input id="diagram-title" bind:value={diagramForm.title} required />
      </div>
      <div class="grid gap-2">
        <Label for="diagram-description">Description</Label>
        <Textarea id="diagram-description" bind:value={diagramForm.description} />
      </div>

      <div class="grid gap-3 rounded-lg border p-3">
        <h3 class="text-sm font-semibold">X axis</h3>
        <div class="grid gap-3 sm:grid-cols-3">
          <div class="grid gap-2">
            <Label>Type</Label>
            <Select.Root
              type="single"
              value={diagramForm.xAxis.type}
              onValueChange={(value) => setXAxisType(value === 'number' ? 'number' : 'datetime')}
            >
              <Select.Trigger class="w-full">{diagramForm.xAxis.type === 'number' ? 'Number' : 'Date/time'}</Select.Trigger>
              <Select.Content>
                <Select.Item value="datetime" label="Date/time">Date/time</Select.Item>
                <Select.Item value="number" label="Number">Number</Select.Item>
              </Select.Content>
            </Select.Root>
          </div>
          <div class="grid gap-2">
            <Label for="xaxis-label">Label</Label>
            <Input
              id="xaxis-label"
              bind:value={diagramForm.xAxis.label}
              placeholder={diagramForm.xAxis.type === 'number' ? 'Minutes' : 'Date'}
            />
          </div>
          <div class="grid gap-2">
            <Label for="xaxis-unit">Unit</Label>
            <Input
              id="xaxis-unit"
              bind:value={diagramForm.xAxis.unit}
              placeholder={diagramForm.xAxis.type === 'number' ? 'min' : 'optional'}
            />
          </div>
        </div>
      </div>

      <Label for="diagram-relative" class="flex items-center gap-2 font-normal">
        <Checkbox id="diagram-relative" bind:checked={diagramForm.relativeToPeak} />
        Show each series relative to its peak (%)
      </Label>

      <div class="grid gap-3">
        <div class="flex items-center justify-between gap-3">
          <h3 class="text-sm font-semibold">Series</h3>
          <Button variant="ghost" size="icon" class="size-8" onclick={addFormField} aria-label="Add series">
            <Plus class="size-4" />
          </Button>
        </div>

        {#each diagramForm.fields as field (field.id)}
          <div class="grid grid-cols-[2.5rem_minmax(0,1fr)_5rem_2.5rem] gap-2">
            <input
              class="border-input h-9 w-10 cursor-pointer rounded-md border bg-transparent p-1"
              type="color"
              bind:value={field.color}
              aria-label="Series color"
            />
            <Input class="min-w-0" bind:value={field.label} placeholder="Name" required />
            <Input class="min-w-0" bind:value={field.unit} placeholder="Unit" />
            <Button
              variant="ghost"
              size="icon"
              class="text-muted-foreground hover:text-destructive size-8"
              onclick={() => removeFormField(field.id)}
              aria-label="Remove series"
              disabled={diagramForm.fields.length === 1}
            >
              <Trash2 class="size-4" />
            </Button>
          </div>
        {/each}
      </div>

      <div class="flex justify-end">
        <Button type="submit">{editingId ? 'Save' : 'Create'}</Button>
      </div>
    {/if}
  </form>
</EntryModal>

{#if selected}
  <EntryModal
    open={measurementFormOpen}
    title={editingMeasurementId ? 'Edit measurement' : 'New measurement'}
    description={`${editingMeasurementId ? 'Update' : 'Add'} values to ${selected.payload.title}. Empty series are skipped.`}
    onClose={() => closeMeasurementForm(selected)}
  >
    <form
      class="grid gap-4"
      onsubmit={(event) => {
        event.preventDefault();
        saveMeasurement(selected);
      }}
    >
      {#if isNumberAxis(selected.payload)}
        <div class="grid gap-2">
          <Label for="measurement-x" class="wrap-anywhere">
            {selected.payload.xAxis.unit
              ? `${selected.payload.xAxis.label} (${selected.payload.xAxis.unit})`
              : selected.payload.xAxis.label}
          </Label>
          <Input id="measurement-x" type="number" step="0.001" bind:value={measurementForm.x} required />
        </div>
      {:else}
        <div class="grid gap-2">
          <Label for="measurement-date">Date</Label>
          <Input id="measurement-date" type="datetime-local" bind:value={measurementForm.date} required />
        </div>
      {/if}
      <div class="grid gap-3 sm:grid-cols-2">
        {#each selected.payload.fields as field (field.id)}
          <div class="grid gap-2">
            <Label for={`measurement-${field.id}`} class="wrap-anywhere">{field.unit ? `${field.label} (${field.unit})` : field.label}</Label>
            <Input
              id={`measurement-${field.id}`}
              type="number"
              step="0.001"
              bind:value={measurementForm.values[field.id]}
            />
          </div>
        {/each}
      </div>
      <div class="flex justify-end">
        <Button type="submit">{editingMeasurementId ? 'Save' : 'Add'}</Button>
      </div>
    </form>
  </EntryModal>
{/if}
