<script lang="ts">
  import EmptyState from '$lib/components/EmptyState.svelte';
  import PageHeader from '$lib/components/PageHeader.svelte';
  import VaultNotice from '$lib/components/VaultNotice.svelte';
  import {
    createEncryptedRecord,
    decryptRecords,
    fetchEncryptedRecords,
    randomId,
    updateEncryptedRecord
  } from '$lib/client/records';
  import { sessionDEK } from '$lib/stores/cryptoKey';
  import type { EncryptedRecord, FamilyPerson, FamilyTreePayload } from '$lib/types';
  import { goto } from '$app/navigation';
  import { get } from 'svelte/store';
  import { onMount } from 'svelte';
  import { Plus, Save, Trash2 } from '@lucide/svelte';

  const emptyPerson = (): FamilyPerson => ({
    id: '',
    parentId: null,
    name: '',
    relation: '',
    birth: '',
    death: '',
    notes: ''
  });

  let dek = $state<CryptoKey | null>(null);
  let locked = $state(false);
  let loading = $state(true);
  let treeRecord = $state<EncryptedRecord | null>(null);
  let people = $state<FamilyPerson[]>([]);
  let selectedId = $state<string | null>(null);
  let form = $state(emptyPerson());

  const selected = $derived(people.find((person) => person.id === selectedId) ?? null);
  const roots = $derived(people.filter((person) => !person.parentId));

  function childrenOf(parentId: string) {
    return people.filter((person) => person.parentId === parentId);
  }

  async function persist(nextPeople = people) {
    if (!dek) return;
    const payload: FamilyTreePayload = { people: nextPeople };
    if (treeRecord) {
      treeRecord = await updateEncryptedRecord(treeRecord.id, 'family_tree', payload, dek);
    } else {
      treeRecord = await createEncryptedRecord('family_tree', payload, dek);
    }
    people = nextPeople;
  }

  async function loadTree() {
    if (!dek) return;
    loading = true;
    const records = await fetchEncryptedRecords('family_tree');
    if (records[0]) {
      const [tree] = await decryptRecords<FamilyTreePayload>([records[0]], dek);
      treeRecord = tree.record;
      people = tree.payload.people;
      selectedId = people[0]?.id ?? null;
    }
    loading = false;
  }

  function newPerson(parentId: string | null = null) {
    selectedId = null;
    form = { ...emptyPerson(), parentId };
  }

  function editPerson(person: FamilyPerson) {
    selectedId = person.id;
    form = { ...person };
  }

  async function savePerson() {
    if (!form.name.trim()) return;
    const payload = {
      ...form,
      parentId: form.parentId || null,
      name: form.name.trim(),
      relation: form.relation.trim(),
      notes: form.notes.trim()
    };
    const nextPeople = payload.id
      ? people.map((person) => (person.id === payload.id ? payload : person))
      : [...people, { ...payload, id: randomId() }];
    await persist(nextPeople);
    form = emptyPerson();
  }

  function descendantIds(personId: string): string[] {
    const children = childrenOf(personId);
    return [personId, ...children.flatMap((child) => descendantIds(child.id))];
  }

  async function removePerson(person: FamilyPerson) {
    if (!confirm('Delete this person and their descendants?')) return;
    const ids = new Set(descendantIds(person.id));
    await persist(people.filter((candidate) => !ids.has(candidate.id)));
    selectedId = null;
    form = emptyPerson();
  }

  onMount(async () => {
    dek = get(sessionDEK);
    if (!dek) {
      locked = true;
      loading = false;
      await goto('/login');
      return;
    }
    await loadTree();
  });
</script>

<PageHeader title="Family tree" description="An encrypted tree editor with a clearer visual hierarchy and quick person editing.">
  <button class="focus-ring inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium text-white" style="background: var(--accent)" type="button" onclick={() => newPerson(null)}>
    <Plus size={16} />
    Root person
  </button>
</PageHeader>

{#if locked}
  <VaultNotice />
{:else if loading}
  <p class="text-sm" style="color: var(--muted)">Decrypting family tree...</p>
{:else}
  <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
    <section class="min-h-96 overflow-auto rounded-xl border p-6" style="border-color: var(--border); background: var(--surface)">
      {#if people.length === 0}
        <EmptyState title="No family tree yet" description="Create a root person to begin." />
      {:else}
        <div class="flex min-w-max gap-8">
          {#each roots as root (root.id)}
            <div class="space-y-4">
              {@render TreeNode(root, 0)}
            </div>
          {/each}
        </div>
      {/if}
    </section>

    <aside class="rounded-xl border p-5" style="border-color: var(--border); background: var(--surface)">
      <h2 class="mb-4 text-base font-semibold">{form.id ? 'Edit person' : 'Add person'}</h2>
      <form
        class="space-y-4"
        onsubmit={(event) => {
          event.preventDefault();
          savePerson();
        }}
      >
        <label class="block text-sm font-medium">
          Parent
          <select class="focus-ring mt-1 w-full rounded-lg border px-3 py-2" style="border-color: var(--border)" bind:value={form.parentId}>
            <option value="">No parent</option>
            {#each people.filter((person) => person.id !== form.id) as person (person.id)}
              <option value={person.id}>{person.name}</option>
            {/each}
          </select>
        </label>
        <label class="block text-sm font-medium">
          Name
          <input class="focus-ring mt-1 w-full rounded-lg border px-3 py-2" style="border-color: var(--border)" bind:value={form.name} required />
        </label>
        <label class="block text-sm font-medium">
          Relation
          <input class="focus-ring mt-1 w-full rounded-lg border px-3 py-2" style="border-color: var(--border)" bind:value={form.relation} placeholder="mother, grandfather, sibling" />
        </label>
        <div class="grid grid-cols-2 gap-3">
          <label class="block text-sm font-medium">
            Birth
            <input class="focus-ring mt-1 w-full rounded-lg border px-3 py-2" style="border-color: var(--border)" type="date" bind:value={form.birth} />
          </label>
          <label class="block text-sm font-medium">
            Death
            <input class="focus-ring mt-1 w-full rounded-lg border px-3 py-2" style="border-color: var(--border)" type="date" bind:value={form.death} />
          </label>
        </div>
        <label class="block text-sm font-medium">
          Notes
          <textarea class="focus-ring mt-1 min-h-28 w-full rounded-lg border px-3 py-2" style="border-color: var(--border)" bind:value={form.notes}></textarea>
        </label>
        <button class="focus-ring inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium text-white" style="background: var(--accent)" type="submit">
          <Save size={16} />
          Save
        </button>
      </form>
    </aside>
  </div>
{/if}

{#snippet TreeNode(person: FamilyPerson, depth: number)}
  <div class="flex items-start gap-4">
    <article
      class="w-64 rounded-xl border p-4"
      class:ring-2={selected?.id === person.id}
      style="border-color: var(--border); background: var(--background); --tw-ring-color: var(--accent)"
    >
      <div class="flex items-start justify-between gap-3">
        <button class="text-left hover:no-underline" type="button" onclick={() => editPerson(person)}>
          <h3 class="font-semibold">{person.name}</h3>
          {#if person.relation}
            <p class="mt-1 text-sm" style="color: var(--muted)">{person.relation}</p>
          {/if}
        </button>
        <div class="flex gap-1">
          <button class="focus-ring rounded-lg border p-2" style="border-color: var(--border)" type="button" onclick={() => newPerson(person.id)}>
            <Plus size={14} />
          </button>
          <button class="focus-ring rounded-lg border p-2" style="border-color: var(--danger); color: var(--danger)" type="button" onclick={() => removePerson(person)}>
            <Trash2 size={14} />
          </button>
        </div>
      </div>
      <div class="mt-3 text-xs leading-5" style="color: var(--muted)">
        {#if person.birth}<p>Born {person.birth}</p>{/if}
        {#if person.death}<p>Died {person.death}</p>{/if}
        {#if person.notes}<p class="mt-2 line-clamp-3">{person.notes}</p>{/if}
      </div>
    </article>
    {#if childrenOf(person.id).length}
      <div class="border-l pl-4" style="border-color: var(--border)">
        <div class="space-y-4">
          {#each childrenOf(person.id) as child (child.id)}
            {@render TreeNode(child, depth + 1)}
          {/each}
        </div>
      </div>
    {/if}
  </div>
{/snippet}
