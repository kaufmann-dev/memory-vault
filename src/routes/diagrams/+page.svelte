<script lang="ts">
  import EmptyState from '$lib/components/EmptyState.svelte';
  import EntryModal from '$lib/components/EntryModal.svelte';
  import PageHeader from '$lib/components/PageHeader.svelte';
  import SimpleLineChart from '$lib/components/SimpleLineChart.svelte';
  import VaultNotice from '$lib/components/VaultNotice.svelte';
  import {
    createEncryptedRecord,
    decryptRecords,
    deleteEncryptedRecord,
    fetchEncryptedRecords,
    randomId,
    updateEncryptedRecord
  } from '$lib/client/records';
  import { sessionDEK } from '$lib/stores/cryptoKey';
  import type { DiagramField, DiagramMeasurement, DiagramPayload, EncryptedRecord } from '$lib/types';
  import { get } from 'svelte/store';
  import { onMount } from 'svelte';
  import { Pencil, Plus, Trash2 } from '@lucide/svelte';

  type DiagramItem = {
    record: EncryptedRecord;
    payload: DiagramPayload;
  };

  type MeasurementForm = {
    date: string;
    x: number | null | undefined;
    values: Record<string, number | null>;
  };

  type MeasurementMode = 'single' | 'batch';

  const colors = ['#2563eb', '#dc2626', '#0f766e', '#ca8a04', '#9333ea', '#0891b2'];
  const todayDateTime = () => new Date().toISOString().slice(0, 16);
  const defaultXAxis = () => ({ type: 'datetime' as const, label: 'Date', unit: '' });
  const emptyField = (): DiagramField => ({ id: randomId(), label: '', unit: '', color: colors[0] });
  const emptyDiagram = (): DiagramPayload => ({
    title: '',
    description: '',
    xAxis: defaultXAxis(),
    fields: [{ ...emptyField(), label: 'Value' }],
    measurements: []
  });
  const emptyMeasurement = (fields: DiagramField[] = []): MeasurementForm => ({
    date: todayDateTime(),
    x: null,
    values: Object.fromEntries(fields.map((field) => [field.id, null]))
  });

  let dek: CryptoKey | null = null;
  let locked = $state(false);
  let loading = $state(true);
  let diagrams: DiagramItem[] = $state([]);
  let selectedId: string | null = $state(null);
  let editingId: string | null = $state(null);
  let editingMeasurementId: string | null = $state(null);
  let diagramForm = $state(emptyDiagram());
  let measurementForm = $state(emptyMeasurement());
  let measurementMode: MeasurementMode = $state('single');
  let batchText = $state('');
  let batchError = $state('');
  let diagramFormOpen = $state(false);
  let measurementFormOpen = $state(false);

  let selected = $derived(diagrams.find((diagram) => diagram.record.id === selectedId) ?? diagrams[0] ?? null);

  function normalizeDiagramPayload(payload: DiagramPayload): DiagramPayload {
    return {
      ...payload,
      xAxis: payload.xAxis ?? defaultXAxis(),
      fields: payload.fields,
      measurements: payload.measurements.map((measurement) => ({
        ...measurement,
        x: measurement.x ?? null,
        values: { ...measurement.values }
      }))
    };
  }

  function xAxis(payload: DiagramPayload) {
    return payload.xAxis ?? defaultXAxis();
  }

  function isNumberAxis(payload: DiagramPayload) {
    return xAxis(payload).type === 'number';
  }

  function sortedMeasurements(payload: DiagramPayload) {
    return [...payload.measurements].sort((a, b) => {
      if (isNumberAxis(payload)) return (a.x ?? 0) - (b.x ?? 0);
      return a.date.localeCompare(b.date);
    });
  }

  function resetMeasurement(payload: DiagramPayload | null = selected?.payload ?? null) {
    measurementForm = emptyMeasurement(payload?.fields ?? []);
    measurementMode = 'single';
    editingMeasurementId = null;
    batchText = '';
    batchError = '';
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
      xAxis: { ...xAxis(item.payload) },
      fields: item.payload.fields.map((field) => ({ ...field })),
      measurements: item.payload.measurements.map((measurement) => ({
        ...measurement,
        x: measurement.x ?? null,
        values: { ...measurement.values }
      }))
    };
    diagramFormOpen = true;
  }

  function cancelEdit() {
    editingId = null;
    diagramForm = emptyDiagram();
    diagramFormOpen = false;
  }

  function openCreate() {
    editingId = null;
    diagramForm = emptyDiagram();
    diagramFormOpen = true;
  }

  function addFormField() {
    diagramForm.fields = [
      ...diagramForm.fields,
      { ...emptyField(), color: colors[diagramForm.fields.length % colors.length] }
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
    diagrams = (await decryptRecords<DiagramPayload>(records, dek)).map((item) => ({
      ...item,
      payload: normalizeDiagramPayload(item.payload)
    }));
    if (!selectedId && diagrams[0]) {
      selectedId = diagrams[0].record.id;
      resetMeasurement(diagrams[0].payload);
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
        color: field.color || colors[index % colors.length]
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
        unit: diagramForm.xAxis?.unit.trim() || (diagramForm.xAxis?.type === 'number' ? 'min' : '')
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
    measurementMode = 'single';
    batchText = '';
    batchError = '';
    measurementFormOpen = true;
  }

  function openEditMeasurement(item: DiagramItem, measurement: DiagramMeasurement) {
    editingMeasurementId = measurement.id;
    measurementMode = 'single';
    batchText = '';
    batchError = '';
    measurementForm = {
      date: measurement.date,
      x: measurement.x ?? null,
      values: Object.fromEntries(item.payload.fields.map((field) => [field.id, measurement.values[field.id] ?? null]))
    };
    measurementFormOpen = true;
  }

  function setMeasurementMode(mode: MeasurementMode) {
    measurementMode = mode;
    batchError = '';
  }

  function fieldByLabel(fields: DiagramField[]) {
    const labels: Array<{ label: string; field: DiagramField }> = [];
    for (const field of fields) {
      const label = field.label.trim();
      const aliases = field.unit.trim() ? [label, `${label} (${field.unit.trim()})`] : [label];
      for (const alias of aliases) {
        if (labels.some((entry) => entry.label === alias)) {
          throw new Error(`Series labels must be unique before batch import. "${alias}" is used more than once.`);
        }
        labels.push({ label: alias, field });
      }
    }
    return labels;
  }

  function normalizeBatchDate(value: string, rowNumber: number) {
    const match = value.trim().match(/^(\d{4}-\d{2}-\d{2})[ T](\d{2}:\d{2})$/);
    if (!match) throw new Error(`Row ${rowNumber}: the date must use YYYY-MM-DD HH:mm.`);
    const normalized = `${match[1]}T${match[2]}`;
    if (Number.isNaN(new Date(normalized).getTime())) throw new Error(`Row ${rowNumber}: the date is not valid.`);
    return normalized;
  }

  function parseBatchNumber(value: string, rowNumber: number, column: string) {
    const trimmed = value.trim();
    if (!trimmed) return null;
    const parsed = Number(trimmed);
    if (!Number.isFinite(parsed)) throw new Error(`Row ${rowNumber}: "${column}" must be a number.`);
    return parsed;
  }

  function parseBatchMeasurements(item: DiagramItem) {
    const lines = batchText
      .split('\n')
      .map((line) => line.replace(/\r$/, ''))
      .filter((line) => line.trim());
    if (lines.length < 2) throw new Error('Paste one header row and at least one measurement row.');

    const header = lines[0].split('\t').map((column) => column.trim());
    const expectedFirstColumn = isNumberAxis(item.payload) ? 'x' : 'date';
    if (header[0] !== expectedFirstColumn) throw new Error(`The first column header must be "${expectedFirstColumn}".`);
    if (header.length < 2) throw new Error('Add at least one series column after the first column.');

    const labels = fieldByLabel(item.payload.fields);
    const fields = header.slice(1).map((label) => {
      const field = labels.find((entry) => entry.label === label)?.field;
      if (!field) throw new Error(`The column "${label}" does not match any series label.`);
      return field;
    });
    if (fields.some((field, index) => fields.findIndex((candidate) => candidate.id === field.id) !== index)) {
      throw new Error('Each series label can only appear once in the header row.');
    }

    return lines.slice(1).map((line, index): DiagramMeasurement => {
      const rowNumber = index + 2;
      const cells = line.split('\t');
      if (cells.length !== header.length) {
        throw new Error(`Row ${rowNumber}: expected ${header.length} tab-separated columns, got ${cells.length}.`);
      }
      const values: Record<string, number | null> = Object.fromEntries(item.payload.fields.map((field) => [field.id, null]));
      for (const [cellIndex, field] of fields.entries()) {
        values[field.id] = parseBatchNumber(cells[cellIndex + 1], rowNumber, field.label);
      }
      if (!Object.values(values).some((value) => value !== null)) {
        throw new Error(`Row ${rowNumber}: at least one series value is required.`);
      }

      if (isNumberAxis(item.payload)) {
        const xValue = parseBatchNumber(cells[0], rowNumber, 'x');
        if (xValue === null) throw new Error(`Row ${rowNumber}: "x" is required.`);
        return {
          id: randomId(),
          date: todayDateTime(),
          x: xValue,
          values
        };
      }

      return {
        id: randomId(),
        date: normalizeBatchDate(cells[0], rowNumber),
        x: null,
        values
      };
    });
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
      x: isNumberAxis(item.payload) ? xValue : null,
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

  async function saveBatchMeasurements(item: DiagramItem) {
    if (!dek) return;
    batchError = '';
    let imported: DiagramMeasurement[];
    try {
      imported = parseBatchMeasurements(item);
    } catch (error) {
      batchError = error instanceof Error ? error.message : 'The batch text could not be imported.';
      return;
    }

    const payload = { ...item.payload, measurements: [...item.payload.measurements, ...imported] };
    diagrams = diagrams.map((d) => (d.record.id === item.record.id ? { ...d, payload } : d));
    await updateEncryptedRecord(item.record.id, 'diagram', payload, dek);
    resetMeasurement(payload);
    measurementFormOpen = false;
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

  function points(item: DiagramItem, field: DiagramField) {
    return sortedMeasurements(item.payload).map((measurement) => ({
      x: isNumberAxis(item.payload) ? measurement.x : new Date(measurement.date).getTime(),
      xLabel: xLabel(item.payload, measurement),
      y: measurement.values[field.id] ?? null
    }));
  }

  function xLabel(payload: DiagramPayload, measurement: DiagramMeasurement) {
    if (!isNumberAxis(payload)) return new Date(measurement.date).toLocaleString();
    const axis = xAxis(payload);
    return `${measurement.x ?? ''}${axis.unit ? ` ${axis.unit}` : ''}`;
  }

  function measurementValue(payload: DiagramPayload, measurement: DiagramMeasurement) {
    return payload.fields
      .map((field) => {
        const value = measurement.values[field.id];
        return value === null || value === undefined ? '' : `${field.label}: ${value}${field.unit ? ` ${field.unit}` : ''}`;
      })
      .filter(Boolean)
      .join(', ');
  }

  function batchExample(payload: DiagramPayload) {
    const firstHeader = isNumberAxis(payload) ? 'x' : 'date';
    const header = [firstHeader, ...payload.fields.map((field) => field.label)].join('\t');
    const firstRowStart = isNumberAxis(payload) ? '0' : '2022-09-24 16:22';
    const secondRowStart = isNumberAxis(payload) ? '30' : '2022-09-26 03:34';
    const firstValues = payload.fields.map((_, index) => String(120 - index * 20)).join('\t');
    const secondValues = payload.fields.map((_, index) => String(125 - index * 18)).join('\t');
    return [header, `${firstRowStart}\t${firstValues}`, `${secondRowStart}\t${secondValues}`].join('\n');
  }

  onMount(async () => {
    dek = get(sessionDEK);
    if (!dek) {
      locked = true;
      loading = false;
      return;
    }
    await loadDiagrams();
  });
</script>

<PageHeader title="Diagrams" description="Encrypted custom measurements, decrypted in the browser and rendered locally.">
  <button class="focus-ring vault-btn-primary" type="button" onclick={openCreate}>
    <Plus size={16} />
    New
  </button>
</PageHeader>

{#if locked}
  <VaultNotice />
{:else}
  <div class="grid gap-6 lg:grid-cols-[340px_minmax(0,1fr)]">
    <aside class="space-y-4">
      {#if diagrams.length}
        <nav class="space-y-2">
          {#each diagrams as item (item.record.id)}
            <button
              class="focus-ring w-full rounded-xl px-3 py-2.5 text-left text-sm font-medium transition-all"
              class:vault-card={selected?.record.id === item.record.id}
              style={selected?.record.id === item.record.id
                ? 'color: var(--foreground); background: var(--accent-light); border: 1px solid var(--border)'
                : 'color: var(--foreground); background: transparent; border: 1px solid transparent'}
              type="button"
              onclick={() => selectDiagram(item)}
            >
              {item.payload.title}
            </button>
          {/each}
        </nav>
      {/if}
    </aside>

    <section>
      {#if loading}
        <p class="text-sm" style="color: var(--muted)">Decrypting diagrams...</p>
      {:else if !selected}
        <EmptyState title="No diagrams yet" description="Create a diagram to start tracking measurements." />
      {:else}
        <div class="space-y-6">
          <section class="vault-card p-5">
            <div class="mb-4 space-y-3">
              <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <h2 class="min-w-0 break-words text-lg font-semibold" style="color: var(--foreground)">{selected.payload.title}</h2>
                <div class="flex shrink-0 gap-2">
                  <button class="focus-ring vault-btn-primary" type="button" onclick={() => openCreateMeasurement(selected)}>+ Add</button>
                  <button class="focus-ring vault-btn-secondary" type="button" onclick={() => startEdit(selected)}>
                    <Pencil size={15} />
                    Edit
                  </button>
                  <button class="focus-ring vault-btn-danger" type="button" onclick={() => removeDiagram(selected)} aria-label="Delete diagram">
                    <Trash2 size={15} />
                  </button>
                </div>
              </div>
              {#if selected.payload.description}
                <p class="text-sm" style="color: var(--muted)">{selected.payload.description}</p>
              {/if}
            </div>

            <SimpleLineChart
              xAxis={xAxis(selected.payload)}
              series={selected.payload.fields.map((field) => ({
                label: field.unit ? `${field.label} (${field.unit})` : field.label,
                points: points(selected, field),
                color: field.color
              }))}
            />
          </section>

          <section>
            <h2 class="mb-3 text-lg font-semibold" style="color: var(--foreground)">Measurements</h2>
            {#if selected.payload.measurements.length === 0}
              <EmptyState title="No measurements yet" description="Add a measurement above to populate this chart." />
            {:else}
              <div class="overflow-hidden vault-card">
                <table class="w-full text-left text-sm">
                  <thead>
                    <tr style="background: var(--surface)">
                      <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider" style="color: var(--muted)">
                        {isNumberAxis(selected.payload) ? xAxis(selected.payload).label : 'Date'}
                      </th>
                      <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider" style="color: var(--muted)">Value</th>
                      <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider" style="color: var(--muted)"></th>
                    </tr>
                  </thead>
                  <tbody class="divide-y" style="border-color: var(--border)">
                    {#each sortedMeasurements(selected.payload) as measurement (measurement.id)}
                      <tr class="transition-colors hover:bg-neutral-50/50">
                        <td class="px-4 py-3 text-sm" style="color: var(--muted)">{xLabel(selected.payload, measurement)}</td>
                        <td class="px-4 py-3 font-medium" style="color: var(--foreground)">
                          {measurementValue(selected.payload, measurement)}
                        </td>
                        <td class="px-4 py-3 text-right">
                          <button
                            class="focus-ring vault-btn-ghost"
                            type="button"
                            onclick={() => openEditMeasurement(selected, measurement)}
                            aria-label="Edit measurement"
                          >
                            <Pencil size={15} />
                          </button>
                          <button
                            class="focus-ring vault-btn-ghost"
                            type="button"
                            onclick={() => removeMeasurement(selected, measurement.id)}
                            aria-label="Delete measurement"
                          >
                            <Trash2 size={15} />
                          </button>
                        </td>
                      </tr>
                    {/each}
                  </tbody>
                </table>
              </div>
            {/if}
          </section>
        </div>
      {/if}
    </section>
  </div>

  <EntryModal
    open={diagramFormOpen}
    title={editingId ? 'Edit diagram' : 'New diagram'}
    description="Define the chart and the numeric series you want to track."
    onClose={cancelEdit}
  >
    <form
      class="space-y-4"
      onsubmit={(event) => {
        event.preventDefault();
        saveDiagram();
      }}
    >
      <label class="block text-sm font-medium">
        Title
        <input class="focus-ring vault-input mt-1.5" bind:value={diagramForm.title} required />
      </label>
      <label class="block text-sm font-medium">
        Description
        <textarea class="focus-ring vault-input mt-1.5" bind:value={diagramForm.description}></textarea>
      </label>

      <div class="space-y-3 rounded-lg border p-3" style="border-color: var(--border)">
        <h3 class="text-sm font-semibold" style="color: var(--foreground)">X axis</h3>
        <div class="grid gap-3 sm:grid-cols-3">
          <label class="block text-sm font-medium">
            Type
            <select
              class="focus-ring vault-input mt-1.5"
              value={diagramForm.xAxis.type}
              onchange={(event) => setXAxisType(event.currentTarget.value === 'number' ? 'number' : 'datetime')}
            >
              <option value="datetime">Date/time</option>
              <option value="number">Number</option>
            </select>
          </label>
          <label class="block text-sm font-medium">
            Label
            <input
              class="focus-ring vault-input mt-1.5"
              bind:value={diagramForm.xAxis.label}
              placeholder={diagramForm.xAxis.type === 'number' ? 'Minutes' : 'Date'}
            />
          </label>
          <label class="block text-sm font-medium">
            Unit
            <input
              class="focus-ring vault-input mt-1.5"
              bind:value={diagramForm.xAxis.unit}
              placeholder={diagramForm.xAxis.type === 'number' ? 'min' : 'optional'}
            />
          </label>
        </div>
      </div>

      <div class="space-y-3">
        <div class="flex items-center justify-between gap-3">
          <h3 class="text-sm font-semibold" style="color: var(--foreground)">Series</h3>
          <button class="focus-ring vault-btn-ghost" type="button" onclick={addFormField} aria-label="Add series">
            <Plus size={15} />
          </button>
        </div>

        {#each diagramForm.fields as field (field.id)}
          <div class="grid grid-cols-[2.25rem_minmax(0,1fr)_4.5rem_2.25rem] gap-2">
            <input class="focus-ring h-9 w-9 rounded-lg border-0 p-1" type="color" bind:value={field.color} aria-label="Series color" />
            <input class="focus-ring vault-input min-w-0" bind:value={field.label} placeholder="Name" required />
            <input class="focus-ring vault-input min-w-0" bind:value={field.unit} placeholder="Unit" />
            <button
              class="focus-ring vault-btn-ghost"
              type="button"
              onclick={() => removeFormField(field.id)}
              aria-label="Remove series"
              disabled={diagramForm.fields.length === 1}
            >
              <Trash2 size={15} />
            </button>
          </div>
        {/each}
      </div>

      <div class="flex justify-end">
        <button class="focus-ring vault-btn-primary" type="submit">{editingId ? 'Save' : 'Create'}</button>
      </div>
    </form>
  </EntryModal>

  {#if selected}
    <EntryModal
      open={measurementFormOpen}
      title={editingMeasurementId ? 'Edit measurement' : measurementMode === 'batch' ? 'Batch add measurements' : 'New measurement'}
      description={`${editingMeasurementId ? 'Update' : 'Add'} values to ${selected.payload.title}. Empty series are skipped.`}
      onClose={() => closeMeasurementForm(selected)}
    >
      <form
        class="space-y-4"
        onsubmit={(event) => {
          event.preventDefault();
          if (measurementMode === 'batch' && !editingMeasurementId) {
            saveBatchMeasurements(selected);
          } else {
            saveMeasurement(selected);
          }
        }}
      >
        {#if !editingMeasurementId}
          <div class="inline-flex rounded-lg border p-1" style="border-color: var(--border)">
            <button
              class="focus-ring rounded-md px-3 py-1.5 text-sm font-medium"
              class:vault-btn-primary={measurementMode === 'single'}
              type="button"
              onclick={() => setMeasurementMode('single')}
            >
              Single
            </button>
            <button
              class="focus-ring rounded-md px-3 py-1.5 text-sm font-medium"
              class:vault-btn-primary={measurementMode === 'batch'}
              type="button"
              onclick={() => setMeasurementMode('batch')}
            >
              Batch
            </button>
          </div>
        {/if}

        {#if measurementMode === 'batch' && !editingMeasurementId}
          <div class="space-y-3 rounded-lg border p-3 text-sm" style="border-color: var(--border); color: var(--muted)">
            <p>
              Paste exactly one tab-separated table: row 1 is the header, every later non-empty row is one measurement, columns are
              separated by real tab characters and not commas, every row must have the same number of columns as the header, the
              first header must be <code>{isNumberAxis(selected.payload) ? 'x' : 'date'}</code>, {isNumberAxis(selected.payload)
                ? 'the x column must contain a plain finite number such as 0, 30, or 120'
                : 'the date column must contain YYYY-MM-DD HH:mm text such as 2022-09-24 16:22 and must not contain Unix timestamps'},
              every other header must exactly match one existing series name in this diagram such as sys, dia, or pul, or
              <code>Name (unit)</code> when that series has a unit, units are read from the diagram setup and must not be written in
              value cells, value cells must be plain finite numbers using dot decimals, and an empty value cell skips only that series
              for that row.
            </p>
          </div>
          <label class="block text-sm font-medium">
            Batch measurements
            <textarea class="focus-ring vault-input mt-1.5 min-h-60 font-mono text-xs" bind:value={batchText} spellcheck="false"></textarea>
          </label>
          <div class="rounded-lg border p-3" style="border-color: var(--border)">
            <p class="mb-2 text-xs font-semibold uppercase tracking-wider" style="color: var(--muted)">Example</p>
            <pre class="overflow-auto whitespace-pre-wrap text-xs" style="color: var(--foreground)">{batchExample(selected.payload)}</pre>
          </div>
          {#if batchError}
            <p class="text-sm font-medium" style="color: var(--danger)">{batchError}</p>
          {/if}
        {:else}
          {#if isNumberAxis(selected.payload)}
            <label class="block text-sm font-medium">
              {xAxis(selected.payload).unit
                ? `${xAxis(selected.payload).label} (${xAxis(selected.payload).unit})`
                : xAxis(selected.payload).label}
              <input class="focus-ring vault-input mt-1.5" type="number" step="0.001" bind:value={measurementForm.x} required />
            </label>
          {:else}
            <label class="block text-sm font-medium">
              Date
              <input class="focus-ring vault-input mt-1.5" type="datetime-local" bind:value={measurementForm.date} required />
            </label>
          {/if}
          <div class="grid gap-3 sm:grid-cols-2">
            {#each selected.payload.fields as field (field.id)}
              <label class="block text-sm font-medium">
                {field.unit ? `${field.label} (${field.unit})` : field.label}
                <input
                  class="focus-ring vault-input mt-1.5"
                  type="number"
                  step="0.001"
                  bind:value={measurementForm.values[field.id]}
                />
              </label>
            {/each}
          </div>
        {/if}
        <div class="flex justify-end">
          <button class="focus-ring vault-btn-primary" type="submit">
            {editingMeasurementId ? 'Save' : measurementMode === 'batch' ? 'Add measurements' : 'Add'}
          </button>
        </div>
      </form>
    </EntryModal>
  {/if}
{/if}
