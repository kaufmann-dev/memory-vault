<script lang="ts">
  import EntryModal from '#lib/components/EntryModal.svelte';
  import {
    createEncryptedRecord,
    updateEncryptedRecord,
    wordCount
  } from '#lib/client/records.js';
  import { upsertDiaryEntry, type DiaryItem } from '#lib/stores/diary.js';
  import type { DiaryPayload } from '#lib/types.js';
  import { Save } from '@lucide/svelte';
  import * as Select from '#lib/components/ui/select/index.js';
  import { Button } from '#lib/components/ui/button/index.js';
  import { Input } from '#lib/components/ui/input/index.js';
  import { Textarea } from '#lib/components/ui/textarea/index.js';
  import { Label } from '#lib/components/ui/label/index.js';

  let {
    open,
    entry,
    dek,
    onClose,
    onSaved
  }: {
    open: boolean;
    entry: DiaryItem | null;
    dek: CryptoKey;
    onClose: () => void;
    onSaved: (item: DiaryItem, isNew: boolean) => void;
  } = $props();

  const emptyForm = (): DiaryPayload => ({
    title: '',
    body: '',
    occurredAt: new Date().toISOString().slice(0, 10),
    language: 'English',
    tags: [],
    wordCount: 0
  });

  let saving = $state(false);
  let form = $state(emptyForm());
  let tagInput = $state('');

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
  });

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
</script>

<EntryModal
  {open}
  title={entry ? 'Edit entry' : 'New entry'}
  description="Write privately. The content is encrypted before it leaves this browser."
  {onClose}
>
  <form
    class="grid gap-4"
    onsubmit={(event) => {
      event.preventDefault();
      saveEntry();
    }}
  >
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
            <Select.Item value="English" label="English">English</Select.Item>
            <Select.Item value="German" label="German">German</Select.Item>
            <Select.Item value="Other" label="Other">Other</Select.Item>
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
  </form>
</EntryModal>
