<script lang="ts">
  import EntryModal from '#lib/components/EntryModal.svelte';
  import BatchImportHelp from '#lib/components/BatchImportHelp.svelte';
  import TextFileButton from '#lib/components/TextFileButton.svelte';
  import {
    createEncryptedRecord,
    createEncryptedRecords,
    updateEncryptedRecord,
    wordCount
  } from '#lib/client/records.js';
  import { DIARY_LANGUAGES, parseDiaryImport, type DiaryImportEntry } from '#lib/diary-import.js';
  import { addDiaryEntries, upsertDiaryEntry, type DiaryItem } from '#lib/stores/diary.js';
  import type { DiaryPayload } from '#lib/types.js';
  import { Save } from '@lucide/svelte';
  import * as Select from '#lib/components/ui/select/index.js';
  import { Button } from '#lib/components/ui/button/index.js';
  import { Input } from '#lib/components/ui/input/index.js';
  import { Textarea } from '#lib/components/ui/textarea/index.js';
  import { Label } from '#lib/components/ui/label/index.js';

  type EntryMode = 'single' | 'batch';

  let {
    open,
    entry,
    dek,
    onClose,
    onSaved,
    onBatchSaved
  }: {
    open: boolean;
    entry: DiaryItem | null;
    dek: CryptoKey;
    onClose: () => void;
    onSaved: (item: DiaryItem, isNew: boolean) => void;
    onBatchSaved?: (count: number) => void;
  } = $props();

  const emptyForm = (): DiaryPayload => ({
    title: '',
    body: '',
    occurredAt: new Date().toISOString().slice(0, 10),
    language: 'English',
    tags: [],
    wordCount: 0
  });

  const batchInstructions = [
    'Paste exactly one JSON array; every element is one diary entry object.',
    '"date" is required and must be YYYY-MM-DD text such as 2024-05-01.',
    '"body" is required and must be a non-empty string; write line breaks as \\n inside the JSON string.',
    '"title" is optional and must be a string.',
    `"language" is optional and must be one of ${DIARY_LANGUAGES.join(', ')}; it defaults to English.`,
    '"tags" is optional and must be an array of strings.',
    'No other keys are allowed.',
    'All entries are imported together; if any entry is invalid, nothing is saved.'
  ];

  const batchExample = JSON.stringify(
    [
      {
        date: '2024-05-01',
        title: 'Wanderung',
        body: 'Heute waren wir am See.',
        language: 'German',
        tags: ['outdoors', 'family']
      },
      {
        date: '2024-05-03',
        body: 'First paragraph.\n\nSecond paragraph.'
      }
    ],
    null,
    2
  );

  let saving = $state(false);
  let form = $state(emptyForm());
  let tagInput = $state('');
  let mode = $state<EntryMode>('single');
  let batchText = $state('');
  let batchError = $state('');

  let batchAvailable = $derived(!entry && Boolean(onBatchSaved));
  let batchActive = $derived(batchAvailable && mode === 'batch');

  // Reset the form whenever the modal opens for a new target.
  $effect(() => {
    if (!open) return;
    if (entry) {
      form = { ...entry.payload, tags: [...entry.payload.tags] };
      tagInput = entry.payload.tags.join(', ');
    } else {
      form = emptyForm();
      tagInput = '';
    }
    mode = 'single';
    batchText = '';
    batchError = '';
  });

  function setMode(next: EntryMode) {
    mode = next;
    batchError = '';
  }

  async function saveEntry() {
    if (saving) return;
    saving = true;
    try {
      const payload: DiaryPayload = {
        ...form,
        title: form.title.trim(),
        body: form.body.trim(),
        wordCount: wordCount(form.body),
        tags: tagInput
          .split(',')
          .map((tag) => tag.trim())
          .filter(Boolean)
      };

      if (entry) {
        const record = entry.record;
        const item: DiaryItem = { record, payload };
        await updateEncryptedRecord(record.id, 'diary', payload, dek);
        upsertDiaryEntry(item);
        onSaved(item, false);
      } else {
        const record = await createEncryptedRecord('diary', payload, dek);
        const item: DiaryItem = { record, payload };
        upsertDiaryEntry(item);
        onSaved(item, true);
      }
    } finally {
      saving = false;
    }
  }

  async function saveBatch() {
    if (saving) return;
    batchError = '';
    let imported: DiaryImportEntry[];
    try {
      imported = parseDiaryImport(batchText);
    } catch (error) {
      batchError = error instanceof Error ? error.message : 'The batch text could not be imported.';
      return;
    }

    saving = true;
    try {
      const payloads: DiaryPayload[] = imported.map((item) => ({ ...item, wordCount: wordCount(item.body) }));
      const records = await createEncryptedRecords('diary', payloads, dek);
      const items: DiaryItem[] = records.map((record, index) => ({ record, payload: payloads[index] }));
      addDiaryEntries(items);
      onBatchSaved?.(items.length);
    } catch (error) {
      batchError = error instanceof Error ? error.message : 'The entries could not be saved.';
    } finally {
      saving = false;
    }
  }
</script>

<EntryModal
  {open}
  title={entry ? 'Edit entry' : batchActive ? 'Batch add entries' : 'New entry'}
  description="Write privately. The content is encrypted before it leaves this browser."
  {onClose}
>
  <form
    class="grid gap-4"
    onsubmit={(event) => {
      event.preventDefault();
      if (batchActive) {
        saveBatch();
      } else {
        saveEntry();
      }
    }}
  >
    {#if batchAvailable}
      <div class="bg-muted inline-flex gap-1 rounded-lg p-1">
        <Button variant={mode === 'single' ? 'default' : 'ghost'} size="sm" onclick={() => setMode('single')}>
          Single
        </Button>
        <Button variant={mode === 'batch' ? 'default' : 'ghost'} size="sm" onclick={() => setMode('batch')}>
          Batch
        </Button>
      </div>
    {/if}

    {#if batchActive}
      <BatchImportHelp instructions={batchInstructions} example={batchExample} />
      <div class="grid gap-2">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <Label for="batch-entries">Batch entries</Label>
          <TextFileButton accept=".json,application/json" onLoad={(text) => (batchText = text)} />
        </div>
        <Textarea id="batch-entries" bind:value={batchText} spellcheck="false" class="min-h-60 font-mono text-xs" />
      </div>
      {#if batchError}
        <p class="text-destructive text-sm font-medium">{batchError}</p>
      {/if}
      <div class="flex justify-end">
        <Button type="submit" disabled={saving}>
          <Save class="size-4" />
          {saving ? 'Saving…' : 'Add entries'}
        </Button>
      </div>
    {:else}
      <div class="grid gap-2">
        <Label for="entry-title">Title</Label>
        <Input id="entry-title" bind:value={form.title} />
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div class="grid gap-2">
          <Label for="entry-date">Date</Label>
          <Input id="entry-date" type="date" bind:value={form.occurredAt} required />
        </div>
        <div class="grid gap-2">
          <Label>Language</Label>
          <Select.Root type="single" value={form.language} onValueChange={(value) => (form.language = value as DiaryPayload['language'])}>
            <Select.Trigger class="w-full">{form.language}</Select.Trigger>
            <Select.Content>
              {#each DIARY_LANGUAGES as language (language)}
                <Select.Item value={language} label={language}>{language}</Select.Item>
              {/each}
            </Select.Content>
          </Select.Root>
        </div>
      </div>

      <div class="grid gap-2">
        <Label for="entry-tags">Tags</Label>
        <Input id="entry-tags" bind:value={tagInput} placeholder="comma, separated" />
      </div>

      <div class="grid gap-2">
        <Label for="entry-body">Body</Label>
        <Textarea id="entry-body" bind:value={form.body} required class="min-h-64 leading-relaxed" />
      </div>

      <div class="flex items-center justify-between">
        <span class="text-muted-foreground text-sm">{wordCount(form.body)} words</span>
        <Button type="submit" disabled={saving}>
          <Save class="size-4" />
          {saving ? 'Saving…' : 'Save'}
        </Button>
      </div>
    {/if}
  </form>
</EntryModal>
