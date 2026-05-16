<script lang="ts">
  import EmptyState from '$lib/components/EmptyState.svelte';
  import PageHeader from '$lib/components/PageHeader.svelte';
  import SimpleLineChart from '$lib/components/SimpleLineChart.svelte';
  import VaultNotice from '$lib/components/VaultNotice.svelte';
  import {
    createEncryptedRecord,
    decryptRecords,
    deleteEncryptedRecord,
    fetchEncryptedRecords
  } from '$lib/client/records';
  import { sessionDEK } from '$lib/stores/cryptoKey';
  import type { BloodPayload, EncryptedRecord, HormonePayload, WeightPayload } from '$lib/types';
  import { goto } from '$app/navigation';
  import { get } from 'svelte/store';
  import { onMount } from 'svelte';
  import { Trash2 } from '@lucide/svelte';

  type MetricItem<T> = {
    record: EncryptedRecord;
    payload: T;
  };

  const todayDateTime = () => new Date().toISOString().slice(0, 16);
  const todayDate = () => new Date().toISOString().slice(0, 10);

  let dek: CryptoKey | null = null;
  let locked = false;
  let loading = true;

  let weights: MetricItem<WeightPayload>[] = [];
  let blood: MetricItem<BloodPayload>[] = [];
  let hormones: MetricItem<HormonePayload>[] = [];

  let weightForm: WeightPayload = { date: todayDateTime(), weight: 0 };
  let bloodForm: BloodPayload = { date: todayDateTime(), sys: 0, dia: 0, pul: 0 };
  let hormoneForm: HormonePayload = {
    date: todayDate(),
    lh: null,
    fsh: null,
    e2: null,
    prog: null,
    prl: null,
    t: null,
    bat: null,
    shbg: null,
    tsh: null
  };
  const hormoneKeys: Array<Exclude<keyof HormonePayload, 'date'>> = [
    'lh',
    'fsh',
    'e2',
    'prog',
    'prl',
    't',
    'bat',
    'shbg',
    'tsh'
  ];

  const sortByDate = <T extends { date: string }>(items: MetricItem<T>[]) =>
    [...items].sort((a, b) => a.payload.date.localeCompare(b.payload.date));

  async function loadMetrics() {
    if (!dek) return;
    loading = true;
    const [weightRecords, bloodRecords, hormoneRecords] = await Promise.all([
      fetchEncryptedRecords('metric_weight'),
      fetchEncryptedRecords('metric_blood'),
      fetchEncryptedRecords('metric_hormone')
    ]);

    weights = sortByDate(await decryptRecords<WeightPayload>(weightRecords, dek));
    blood = sortByDate(await decryptRecords<BloodPayload>(bloodRecords, dek));
    hormones = sortByDate(await decryptRecords<HormonePayload>(hormoneRecords, dek));
    loading = false;
  }

  async function saveWeight() {
    if (!dek || weightForm.weight <= 0) return;
    await createEncryptedRecord('metric_weight', weightForm, dek);
    weightForm = { date: todayDateTime(), weight: 0 };
    await loadMetrics();
  }

  async function saveBlood() {
    if (!dek || bloodForm.sys <= 0 || bloodForm.dia <= 0 || bloodForm.pul <= 0) return;
    await createEncryptedRecord('metric_blood', bloodForm, dek);
    bloodForm = { date: todayDateTime(), sys: 0, dia: 0, pul: 0 };
    await loadMetrics();
  }

  async function saveHormone() {
    if (!dek) return;
    await createEncryptedRecord('metric_hormone', hormoneForm, dek);
    hormoneForm = {
      date: todayDate(),
      lh: null,
      fsh: null,
      e2: null,
      prog: null,
      prl: null,
      t: null,
      bat: null,
      shbg: null,
      tsh: null
    };
    await loadMetrics();
  }

  async function removeMetric(id: string) {
    if (!confirm('Delete this measurement?')) return;
    await deleteEncryptedRecord(id);
    await loadMetrics();
  }

  const points = <T extends { date: string }>(items: MetricItem<T>[], key: keyof T) =>
    items.map((item) => ({ x: item.payload.date, y: Number(item.payload[key]) || null }));

  onMount(async () => {
    dek = get(sessionDEK);
    if (!dek) {
      locked = true;
      loading = false;
      await goto('/login');
      return;
    }
    await loadMetrics();
  });
</script>

<PageHeader title="Diagrams" description="Encrypted measurements, decrypted in the browser and rendered locally." />

{#if locked}
  <VaultNotice />
{:else if loading}
  <p class="text-sm" style="color: var(--muted)">Decrypting metrics...</p>
{:else}
  <div class="space-y-10">
    <section>
      <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <h2 class="text-xl font-semibold">Weight</h2>
          <p class="mt-1 text-sm" style="color: var(--muted)">Body weight over time.</p>
        </div>
        <form class="flex flex-col gap-2 sm:flex-row" on:submit|preventDefault={saveWeight}>
          <input class="focus-ring rounded-lg border px-3 py-2 text-sm" style="border-color: var(--border)" type="datetime-local" bind:value={weightForm.date} required />
          <input class="focus-ring rounded-lg border px-3 py-2 text-sm" style="border-color: var(--border)" type="number" step="0.01" min="0" bind:value={weightForm.weight} placeholder="kg" required />
          <button class="focus-ring rounded-lg px-4 py-2 text-sm font-medium text-white" style="background: var(--accent)" type="submit">Add</button>
        </form>
      </div>
      <SimpleLineChart series={[{ label: 'Weight', points: points(weights, 'weight'), color: 'var(--accent)' }]} />
      {#if weights.length === 0}
        <div class="mt-4"><EmptyState title="No weight data" description="Add a measurement to start this chart." /></div>
      {/if}
    </section>

    <section>
      <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <h2 class="text-xl font-semibold">Blood</h2>
          <p class="mt-1 text-sm" style="color: var(--muted)">Blood pressure and pulse.</p>
        </div>
        <form class="grid gap-2 sm:grid-cols-5" on:submit|preventDefault={saveBlood}>
          <input class="focus-ring rounded-lg border px-3 py-2 text-sm sm:col-span-2" style="border-color: var(--border)" type="datetime-local" bind:value={bloodForm.date} required />
          <input class="focus-ring rounded-lg border px-3 py-2 text-sm" style="border-color: var(--border)" type="number" min="0" bind:value={bloodForm.sys} placeholder="SYS" required />
          <input class="focus-ring rounded-lg border px-3 py-2 text-sm" style="border-color: var(--border)" type="number" min="0" bind:value={bloodForm.dia} placeholder="DIA" required />
          <input class="focus-ring rounded-lg border px-3 py-2 text-sm" style="border-color: var(--border)" type="number" min="0" bind:value={bloodForm.pul} placeholder="PUL" required />
          <button class="focus-ring rounded-lg px-4 py-2 text-sm font-medium text-white sm:col-span-5" style="background: var(--accent)" type="submit">Add</button>
        </form>
      </div>
      <SimpleLineChart
        series={[
          { label: 'SYS', points: points(blood, 'sys'), color: 'var(--accent)' },
          { label: 'DIA', points: points(blood, 'dia'), color: 'var(--success)' },
          { label: 'PUL', points: points(blood, 'pul'), color: 'var(--danger)' }
        ]}
      />
    </section>

    <section>
      <div class="mb-4">
        <h2 class="text-xl font-semibold">Hormones</h2>
        <p class="mt-1 text-sm" style="color: var(--muted)">Lab values with optional fields.</p>
      </div>
      <form class="mb-4 grid gap-2 sm:grid-cols-5" on:submit|preventDefault={saveHormone}>
        <input class="focus-ring rounded-lg border px-3 py-2 text-sm" style="border-color: var(--border)" type="date" bind:value={hormoneForm.date} required />
        {#each hormoneKeys as key}
          <input
            class="focus-ring rounded-lg border px-3 py-2 text-sm"
            style="border-color: var(--border)"
            type="number"
            step="0.001"
            min="0"
            bind:value={hormoneForm[key]}
            placeholder={key.toUpperCase()}
          />
        {/each}
        <button class="focus-ring rounded-lg px-4 py-2 text-sm font-medium text-white sm:col-span-5" style="background: var(--accent)" type="submit">Add</button>
      </form>
      <SimpleLineChart
        series={[
          { label: 'LH', points: points(hormones, 'lh'), color: 'var(--accent)' },
          { label: 'FSH', points: points(hormones, 'fsh'), color: 'var(--success)' },
          { label: 'E2', points: points(hormones, 'e2'), color: 'var(--warn)' },
          { label: 'T', points: points(hormones, 't'), color: 'var(--danger)' },
          { label: 'TSH', points: points(hormones, 'tsh'), color: 'var(--muted)' }
        ]}
      />
    </section>

    <section>
      <h2 class="mb-3 text-xl font-semibold">Recent measurements</h2>
      {#if weights.length + blood.length + hormones.length === 0}
        <EmptyState title="No measurements yet" description="Add measurements above to populate this area." />
      {:else}
        <div class="overflow-hidden rounded-2xl shadow-sm" style="background: var(--surface)">
          <table class="w-full text-left text-sm">
            <thead style="background: var(--background)">
              <tr>
                <th class="px-4 py-3 font-medium">Type</th>
                <th class="px-4 py-3 font-medium">Date</th>
                <th class="px-4 py-3 font-medium">Value</th>
                <th class="px-4 py-3 font-medium"></th>
              </tr>
            </thead>
            <tbody class="divide-y" style="border-color: var(--border)">
              {#each weights as item}
                <tr>
                  <td class="px-4 py-3">Weight</td>
                  <td class="px-4 py-3">{item.payload.date}</td>
                  <td class="px-4 py-3">{item.payload.weight} kg</td>
                  <td class="px-4 py-3 text-right"><button class="focus-ring rounded-lg border p-2" style="border-color: var(--border)" on:click={() => removeMetric(item.record.id)}><Trash2 size={15} /></button></td>
                </tr>
              {/each}
              {#each blood as item}
                <tr>
                  <td class="px-4 py-3">Blood</td>
                  <td class="px-4 py-3">{item.payload.date}</td>
                  <td class="px-4 py-3">{item.payload.sys}/{item.payload.dia}, {item.payload.pul}</td>
                  <td class="px-4 py-3 text-right"><button class="focus-ring rounded-lg border p-2" style="border-color: var(--border)" on:click={() => removeMetric(item.record.id)}><Trash2 size={15} /></button></td>
                </tr>
              {/each}
              {#each hormones as item}
                <tr>
                  <td class="px-4 py-3">Hormones</td>
                  <td class="px-4 py-3">{item.payload.date}</td>
                  <td class="px-4 py-3">Lab record</td>
                  <td class="px-4 py-3 text-right"><button class="focus-ring rounded-lg border p-2" style="border-color: var(--border)" on:click={() => removeMetric(item.record.id)}><Trash2 size={15} /></button></td>
                </tr>
              {/each}
            </tbody>
          </table>
        </div>
      {/if}
    </section>
  </div>
{/if}
