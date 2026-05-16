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
  import { goto } from '$app/navigation';
  import { get } from 'svelte/store';
  import { onMount } from 'svelte';
  import { Pencil, Plus, Trash2 } from '@lucide/svelte';

  type DiagramItem = {
    record: EncryptedRecord;
    payload: DiagramPayload;
  };

  type MeasurementForm = {
    date: string;
    values: Record<string, number | null>;
  };

  const colors = ['#2563eb', '#0f172a', '#64748b', '#94a3b8', '#dc2626'];
  const todayDateTime = () => new Date().toISOString().slice(0, 16);
  const emptyField = (): DiagramField => ({ id: randomId(), label: '', unit: '', color: colors[0] });
  const emptyDiagram = (): DiagramPayload => ({
    title: '',
    description: '',
    fields: [{ ...emptyField(), label: 'Value' }],
    measurements: []
  });
  const emptyMeasurement = (fields: DiagramField[] = []): MeasurementForm => ({
    date: todayDateTime(),
    values: Object.fromEntries(fields.map((field) => [field.id, null]))
  });

  let dek: CryptoKey | null = null;
  let locked = $state(false);
  let loading = $state(true);
  let diagrams: DiagramItem[] = $state([]);
  let selectedId: string | null = $state(null);
  let editingId: string | null = $state(null);
  let diagramForm = $state(emptyDiagram());
  let measurementForm = $state(emptyMeasurement());
  let diagramFormOpen = $state(false);
  let measurementFormOpen = $state(false);

  let selected = $derived(diagrams.find((diagram) => diagram.record.id === selectedId) ?? diagrams[0] ?? null);

  function sortedMeasurements(measurements: DiagramMeasurement[]) {
    return [...measurements].sort((a, b) => a.date.localeCompare(b.date));
  }

  function resetMeasurement(payload: DiagramPayload | null = selected?.payload ?? null) {
    measurementForm = emptyMeasurement(payload?.fields ?? []);
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
      fields: item.payload.fields.map((field) => ({ ...field })),
      measurements: item.payload.measurements.map((measurement) => ({
        ...measurement,
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

  async function loadDiagrams() {
    if (!dek) return;
    loading = true;
    const records = await fetchEncryptedRecords('diagram');
    diagrams = await decryptRecords<DiagramPayload>(records, dek);
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
      fields
    };

    if (editingId) {
      await updateEncryptedRecord(editingId, 'diagram', payload, dek);
      selectedId = editingId;
    } else {
      const record = await createEncryptedRecord('diagram', payload, dek);
      selectedId = record.id;
    }

    cancelEdit();
    await loadDiagrams();
  }

  async function removeDiagram(item: DiagramItem) {
    if (!confirm('Delete this diagram?')) return;
    await deleteEncryptedRecord(item.record.id);
    selectedId = null;
    cancelEdit();
    await loadDiagrams();
  }

  async function addMeasurement(item: DiagramItem) {
    if (!dek) return;
    const values = Object.fromEntries(
      item.payload.fields.map((field) => {
        const value = measurementForm.values[field.id];
        return [field.id, value === undefined || value === null || Number.isNaN(value) ? null : value];
      })
    );
    if (!Object.values(values).some((value) => value !== null)) return;

    await updateEncryptedRecord(
      item.record.id,
      'diagram',
      {
        ...item.payload,
        measurements: [...item.payload.measurements, { id: randomId(), date: measurementForm.date, values }]
      },
      dek
    );
    resetMeasurement(item.payload);
    measurementFormOpen = false;
    await loadDiagrams();
  }

  async function removeMeasurement(item: DiagramItem, measurementId: string) {
    if (!dek) return;
    await updateEncryptedRecord(
      item.record.id,
      'diagram',
      {
        ...item.payload,
        measurements: item.payload.measurements.filter((measurement) => measurement.id !== measurementId)
      },
      dek
    );
    await loadDiagrams();
  }

  function points(item: DiagramItem, field: DiagramField) {
    return sortedMeasurements(item.payload.measurements).map((measurement) => ({
      x: measurement.date,
      y: measurement.values[field.id] ?? null
    }));
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

  onMount(async () => {
    dek = get(sessionDEK);
    if (!dek) {
      locked = true;
      loading = false;
      await goto('/login');
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
            <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
              <div>
                <h2 class="text-lg font-semibold" style="color: var(--foreground)">{selected.payload.title}</h2>
                {#if selected.payload.description}
                  <p class="mt-1 text-sm" style="color: var(--muted)">{selected.payload.description}</p>
                {/if}
              </div>
              <div class="flex gap-2">
                <button class="focus-ring vault-btn-primary" type="button" onclick={() => (measurementFormOpen = true)}>New measurement</button>
                <button class="focus-ring vault-btn-secondary" type="button" onclick={() => startEdit(selected)}>
                  <Pencil size={15} />
                  Edit
                </button>
                <button class="focus-ring vault-btn-danger" type="button" onclick={() => removeDiagram(selected)}>
                  <Trash2 size={15} />
                </button>
              </div>
            </div>

            <SimpleLineChart
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
                      <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider" style="color: var(--muted)">Date</th>
                      <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider" style="color: var(--muted)">Value</th>
                      <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider" style="color: var(--muted)"></th>
                    </tr>
                  </thead>
                  <tbody class="divide-y" style="border-color: var(--border)">
                    {#each sortedMeasurements(selected.payload.measurements) as measurement (measurement.id)}
                      <tr class="transition-colors hover:bg-neutral-50/50">
                        <td class="px-4 py-3 text-sm" style="color: var(--muted)">{measurement.date}</td>
                        <td class="px-4 py-3 font-medium" style="color: var(--foreground)">
                          {measurementValue(selected.payload, measurement)}
                        </td>
                        <td class="px-4 py-3 text-right">
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
      title="New measurement"
      description={`Add values to ${selected.payload.title}. Empty series are skipped.`}
      onClose={() => {
        measurementFormOpen = false;
        resetMeasurement(selected.payload);
      }}
    >
      <form
        class="space-y-4"
        onsubmit={(event) => {
          event.preventDefault();
          addMeasurement(selected);
        }}
      >
        <label class="block text-sm font-medium">
          Date
          <input class="focus-ring vault-input mt-1.5" type="datetime-local" bind:value={measurementForm.date} required />
        </label>
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
        <div class="flex justify-end">
          <button class="focus-ring vault-btn-primary" type="submit">Add</button>
        </div>
      </form>
    </EntryModal>
  {/if}
{/if}
