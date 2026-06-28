<script lang="ts">
  import PageHeader from '$lib/components/PageHeader.svelte';
  import {
    forgetRememberedDEK,
    hasRememberedDEK,
    saveRememberedDEK
  } from '$lib/client/rememberedDevice';
  import {
    BACKUP_APP,
    BACKUP_EXTENSION,
    BACKUP_VERSION,
    parseBackupFile,
    parseBackupPayload,
    type BackupPayload,
    type VaultBackupFile
  } from '$lib/backup';
  import { decrypt, decryptDEK, deriveKEK, encrypt, encryptDEK } from '$lib/crypto';
  import { sessionDEK } from '$lib/stores/cryptoKey';
  import type { EncryptedRecord } from '$lib/types';
  import { invalidateAll } from '$app/navigation';
  import { get } from 'svelte/store';
  import { onMount } from 'svelte';
  import type { PageProps } from './$types';
  import { Download, KeyRound, Save, Shield, Trash2, Upload, X } from '@lucide/svelte';
  import * as AlertDialog from '$lib/components/ui/alert-dialog/index.js';
  import { Button } from '$lib/components/ui/button/index.js';
  import { Input } from '$lib/components/ui/input/index.js';
  import { Label } from '$lib/components/ui/label/index.js';

  let { data }: PageProps = $props();

  type KeyMaterial = NonNullable<PageProps['data']['keyMaterial']>;

  let updatedKeyMaterial = $state<KeyMaterial | null>(null);
  let keyMaterial = $derived(updatedKeyMaterial ?? data.keyMaterial);
  let dek: CryptoKey | null = null;

  let currentAccountPassword = $state('');
  let newAccountPassword = $state('');
  let confirmAccountPassword = $state('');
  let accountMessage = $state('');
  let accountSuccess = $state(false);
  let savingAccount = $state(false);

  let currentVaultPassphrase = $state('');
  let newVaultPassphrase = $state('');
  let confirmVaultPassphrase = $state('');
  let vaultMessage = $state('');
  let vaultSuccess = $state(false);
  let savingVault = $state(false);
  let rememberedDevice = $state(false);
  let savingRememberedDevice = $state(false);
  let deviceMessage = $state('');
  let deviceSuccess = $state(false);
  let exportingBackup = $state(false);
  let exportMessage = $state('');
  let exportSuccess = $state(false);
  let importFiles = $state<FileList>();
  let importFile = $derived(importFiles?.item(0) ?? null);
  let importFileInput = $state<HTMLInputElement | null>(null);
  let importVaultPassphrase = $state('');
  let importMessage = $state('');
  let importSuccess = $state(false);
  let preparingImport = $state(false);
  let restoringImport = $state(false);
  let confirmImportOpen = $state(false);
  let preparedImportPayload = $state<BackupPayload | null>(null);
  let preparedImportBackup: VaultBackupFile | null = null;
  let preparedImportDEK: CryptoKey | null = null;

  type RecordResponse = {
    records: EncryptedRecord[];
  };

  async function parseResponse<T>(response: Response): Promise<T> {
    if (!response.ok) {
      const message = await response.text();
      throw new Error(message || 'Request failed');
    }

    return response.json() as Promise<T>;
  }

  function randomBase64(bytes: number) {
    const values = crypto.getRandomValues(new Uint8Array(bytes));
    return btoa(String.fromCharCode(...values));
  }

  function activeDEK() {
    const active = get(sessionDEK);
    dek = active;
    return active;
  }

  function downloadBlob(blob: Blob, filename: string) {
    const url = URL.createObjectURL(blob);
    const anchor = document.createElement('a');
    anchor.href = url;
    anchor.download = filename;
    anchor.click();
    URL.revokeObjectURL(url);
  }

  function resetPreparedImport() {
    preparedImportPayload = null;
    preparedImportBackup = null;
    preparedImportDEK = null;
    confirmImportOpen = false;
  }

  function backupDateLabel(value: string) {
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return 'unknown date';
    return date.toLocaleString();
  }

  async function changeAccountPassword() {
    accountMessage = '';
    accountSuccess = false;

    if (newAccountPassword.length < 12) {
      accountMessage = 'Use at least 12 characters.';
      return;
    }
    if (newAccountPassword !== confirmAccountPassword) {
      accountMessage = 'Account passwords do not match.';
      return;
    }

    savingAccount = true;
    try {
      const response = await fetch('/api/auth/password', {
        method: 'POST',
        headers: { 'content-type': 'application/json' },
        body: JSON.stringify({
          currentPassword: currentAccountPassword,
          newPassword: newAccountPassword
        })
      });

      if (!response.ok) throw new Error(await response.text());
      currentAccountPassword = '';
      newAccountPassword = '';
      confirmAccountPassword = '';
      accountSuccess = true;
      accountMessage = 'Account password changed.';
    } catch {
      accountMessage = 'Account password change failed.';
    } finally {
      savingAccount = false;
    }
  }

  async function changeVaultPassphrase() {
    vaultMessage = '';
    vaultSuccess = false;

    const currentDEK = activeDEK();
    if (!currentDEK || !keyMaterial) {
      vaultMessage = 'Unlock the vault before changing its passphrase.';
      return;
    }
    if (newVaultPassphrase.length < 12) {
      vaultMessage = 'Use at least 12 characters.';
      return;
    }
    if (newVaultPassphrase !== confirmVaultPassphrase) {
      vaultMessage = 'Vault passphrases do not match.';
      return;
    }

    savingVault = true;
    try {
      const oldKEK = await deriveKEK(currentVaultPassphrase, keyMaterial.kekSalt);
      await decryptDEK(oldKEK, keyMaterial.encryptedDEK, keyMaterial.dekIV);

      const newKekSalt = randomBase64(16);
      const newKEK = await deriveKEK(newVaultPassphrase, newKekSalt);
      const { encryptedDEK: newEncryptedDEK, dekIV: newDekIV } = await encryptDEK(newKEK, currentDEK);

      const response = await fetch('/api/auth/vault-key', {
        method: 'POST',
        headers: { 'content-type': 'application/json' },
        body: JSON.stringify({
          newKekSalt,
          newEncryptedDEK,
          newDekIV
        })
      });

      if (!response.ok) throw new Error(await response.text());
      updatedKeyMaterial = {
        kekSalt: newKekSalt,
        encryptedDEK: newEncryptedDEK,
        dekIV: newDekIV
      };
      currentVaultPassphrase = '';
      newVaultPassphrase = '';
      confirmVaultPassphrase = '';
      vaultSuccess = true;
      vaultMessage = 'Vault passphrase changed.';
      await invalidateAll();
    } catch {
      vaultMessage = 'Vault passphrase change failed.';
    } finally {
      savingVault = false;
    }
  }

  onMount(() => {
    dek = get(sessionDEK);
    (async () => {
      if (!data.user) return;
      rememberedDevice = await hasRememberedDEK(data.user.email).catch(() => false);
    })();
  });

  async function rememberDevice() {
    deviceMessage = '';
    deviceSuccess = false;

    const currentDEK = activeDEK();
    if (!data.user || !currentDEK) {
      deviceMessage = 'Unlock the vault before remembering this device.';
      return;
    }

    savingRememberedDevice = true;
    try {
      await saveRememberedDEK(data.user.email, currentDEK);
      dek = currentDEK;
      rememberedDevice = true;
      deviceSuccess = true;
      deviceMessage = 'This device will unlock after reloads.';
    } catch {
      deviceMessage = 'This browser could not remember the vault key.';
    } finally {
      savingRememberedDevice = false;
    }
  }

  async function forgetDevice() {
    deviceMessage = '';
    deviceSuccess = false;
    if (!data.user) return;

    savingRememberedDevice = true;
    try {
      await forgetRememberedDEK(data.user.email);
      rememberedDevice = false;
      deviceSuccess = true;
      deviceMessage = 'This device has been forgotten.';
    } catch {
      deviceMessage = 'This browser could not forget the vault key.';
    } finally {
      savingRememberedDevice = false;
    }
  }

  async function exportBackup() {
    exportMessage = '';
    exportSuccess = false;

    const currentDEK = activeDEK();
    if (!data.user || !keyMaterial || !currentDEK) {
      exportMessage = 'Unlock the vault before exporting a backup.';
      return;
    }

    exportingBackup = true;
    try {
      const response = await fetch('/api/records');
      const { records } = await parseResponse<RecordResponse>(response);
      const payload: BackupPayload = {
        exportedAt: new Date().toISOString(),
        user: {
          email: data.user.email,
          name: data.user.name
        },
        records
      };
      const encryptedPayload = await encrypt(currentDEK, JSON.stringify(payload));
      const backup: VaultBackupFile = {
        app: BACKUP_APP,
        version: BACKUP_VERSION,
        keyMaterial,
        payload: encryptedPayload
      };
      const filename = `memory-vault-${new Date().toISOString().slice(0, 10)}.${BACKUP_EXTENSION}`;

      downloadBlob(new Blob([JSON.stringify(backup)], { type: 'application/json' }), filename);
      exportSuccess = true;
      exportMessage = `Backup exported with ${records.length} entries.`;
    } catch {
      exportMessage = 'Backup export failed.';
    } finally {
      exportingBackup = false;
    }
  }

  function handleBackupFileChange() {
    importMessage = '';
    importSuccess = false;
    resetPreparedImport();
  }

  function chooseBackupFile() {
    importFileInput?.click();
  }

  function clearBackupFile() {
    importFiles = undefined;
    if (importFileInput) importFileInput.value = '';
    handleBackupFileChange();
  }

  async function prepareBackupImport() {
    importMessage = '';
    importSuccess = false;
    resetPreparedImport();

    if (!importFile) {
      importMessage = 'Choose a backup file.';
      return;
    }
    if (!importVaultPassphrase) {
      importMessage = 'Enter the backup vault passphrase.';
      return;
    }

    preparingImport = true;
    try {
      const backup = parseBackupFile(JSON.parse(await importFile.text()));
      const backupKEK = await deriveKEK(importVaultPassphrase, backup.keyMaterial.kekSalt);
      const backupDEK = await decryptDEK(backupKEK, backup.keyMaterial.encryptedDEK, backup.keyMaterial.dekIV);
      const payload = parseBackupPayload(JSON.parse(await decrypt(backupDEK, backup.payload.ciphertext, backup.payload.iv)));

      preparedImportBackup = backup;
      preparedImportDEK = backupDEK;
      preparedImportPayload = payload;
      confirmImportOpen = true;
    } catch {
      importMessage = 'Backup could not be decrypted. Check the file and backup vault passphrase.';
    } finally {
      preparingImport = false;
    }
  }

  async function restorePreparedBackup() {
    importMessage = '';
    importSuccess = false;

    if (!preparedImportBackup || !preparedImportDEK || !preparedImportPayload) {
      importMessage = 'Prepare a backup import first.';
      return;
    }

    restoringImport = true;
    try {
      const response = await fetch('/api/backup/import', {
        method: 'POST',
        headers: { 'content-type': 'application/json' },
        body: JSON.stringify({
          keyMaterial: preparedImportBackup.keyMaterial,
          records: preparedImportPayload.records
        })
      });

      await parseResponse<{ ok: true; count: number }>(response);
      sessionDEK.set(preparedImportDEK);
      dek = preparedImportDEK;
      updatedKeyMaterial = preparedImportBackup.keyMaterial;
      if (data.user) {
        await forgetRememberedDEK(data.user.email).catch(() => undefined);
        rememberedDevice = false;
      }
      importFiles = undefined;
      if (importFileInput) importFileInput.value = '';
      importVaultPassphrase = '';
      importSuccess = true;
      importMessage = `Backup imported with ${preparedImportPayload.records.length} entries.`;
      resetPreparedImport();
      await invalidateAll();
    } catch {
      importMessage = 'Backup import failed. Current entries were not changed.';
    } finally {
      restoringImport = false;
    }
  }
</script>

<PageHeader title="Settings" description="Account security and vault controls." />

<div class="max-w-3xl">
  <section class="border-border grid gap-6 border-b py-8 first:pt-0 last:border-b-0 last:pb-0">
    <div class="grid gap-3">
      <div class="bg-muted text-foreground flex size-10 items-center justify-center rounded-lg">
        <Shield class="size-4.5" />
      </div>
      <div class="grid gap-2">
        <h2 class="text-xl font-semibold tracking-tight">Account password</h2>
        <p class="text-muted-foreground max-w-2xl text-sm leading-6">
          This password signs in to the server and creates the session cookie.
        </p>
      </div>
    </div>

    <form
      class="grid max-w-xl gap-4"
      onsubmit={(event) => {
        event.preventDefault();
        changeAccountPassword();
      }}
    >
      <div class="grid gap-2">
        <Label for="current-account-password">Current account password</Label>
        <Input id="current-account-password" type="password" bind:value={currentAccountPassword} required />
      </div>
      <div class="grid gap-2">
        <Label for="new-account-password">New account password</Label>
        <Input id="new-account-password" type="password" bind:value={newAccountPassword} required />
      </div>
      <div class="grid gap-2">
        <Label for="confirm-account-password">Confirm new account password</Label>
        <Input id="confirm-account-password" type="password" bind:value={confirmAccountPassword} required />
      </div>
      {#if accountMessage}
        <p class="text-sm font-medium {accountSuccess ? 'text-green-600 dark:text-green-500' : 'text-destructive'}">
          {accountMessage}
        </p>
      {/if}
      <Button type="submit" disabled={savingAccount} class="justify-self-start">
        <Save class="size-4" />
        {savingAccount ? 'Saving…' : 'Save account password'}
      </Button>
    </form>
  </section>

  <section class="border-border grid gap-6 border-b py-8">
    <div class="grid gap-3">
      <div class="bg-muted text-foreground flex size-10 items-center justify-center rounded-lg">
        <KeyRound class="size-4.5" />
      </div>
      <div class="grid gap-2">
        <h2 class="text-xl font-semibold tracking-tight">Vault passphrase</h2>
        <p class="text-muted-foreground max-w-2xl text-sm leading-6">
          This passphrase never gets sent to the server. It re-encrypts only the vault key.
        </p>
      </div>
    </div>

    <form
      class="grid max-w-xl gap-4"
      onsubmit={(event) => {
        event.preventDefault();
        changeVaultPassphrase();
      }}
    >
      <div class="grid gap-2">
        <Label for="current-vault-passphrase">Current vault passphrase</Label>
        <Input id="current-vault-passphrase" type="password" bind:value={currentVaultPassphrase} required />
      </div>
      <div class="grid gap-2">
        <Label for="new-vault-passphrase">New vault passphrase</Label>
        <Input id="new-vault-passphrase" type="password" bind:value={newVaultPassphrase} required />
      </div>
      <div class="grid gap-2">
        <Label for="confirm-vault-passphrase">Confirm new vault passphrase</Label>
        <Input id="confirm-vault-passphrase" type="password" bind:value={confirmVaultPassphrase} required />
      </div>
      {#if vaultMessage}
        <p class="text-sm font-medium {vaultSuccess ? 'text-green-600 dark:text-green-500' : 'text-destructive'}">
          {vaultMessage}
        </p>
      {/if}
      <Button type="submit" disabled={savingVault} class="justify-self-start">
        <Save class="size-4" />
        {savingVault ? 'Saving…' : 'Save vault passphrase'}
      </Button>
    </form>
  </section>

  <section class="border-border grid gap-6 border-b py-8">
    <div class="grid gap-3">
      <div class="bg-muted text-foreground flex size-10 items-center justify-center rounded-lg">
        <Download class="size-4.5" />
      </div>
      <div class="grid gap-2">
        <h2 class="text-xl font-semibold tracking-tight">Vault backup</h2>
        <p class="text-muted-foreground max-w-2xl text-sm leading-6">
          Export an encrypted backup file or restore one by replacing all current entries.
        </p>
      </div>
    </div>

    <div class="grid max-w-xl gap-6">
      <div class="grid gap-3">
        <div class="grid gap-1">
          <h3 class="text-sm font-semibold">Export</h3>
          <p class="text-muted-foreground text-sm leading-6">
            Downloads one encrypted file that can be restored with the vault passphrase from export time.
          </p>
        </div>
        {#if exportMessage}
          <p class="text-sm font-medium {exportSuccess ? 'text-green-600 dark:text-green-500' : 'text-destructive'}">
            {exportMessage}
          </p>
        {/if}
        <Button disabled={exportingBackup} onclick={exportBackup} class="justify-self-start">
          <Download class="size-4" />
          {exportingBackup ? 'Exporting…' : 'Download backup'}
        </Button>
      </div>

      <div class="border-border grid gap-4 border-t pt-6">
        <div class="grid gap-1">
          <h3 class="text-sm font-semibold">Import</h3>
          <p class="text-muted-foreground text-sm leading-6">
            Decrypts the backup in this browser first. Importing destroys all current entries on this server.
          </p>
        </div>
        <div class="grid gap-2">
          <Label id="backup-file-label" for="backup-file">Backup file</Label>
          <input
            id="backup-file"
            class="sr-only"
            type="file"
            accept=".mvault,application/json"
            aria-labelledby="backup-file-label"
            bind:this={importFileInput}
            bind:files={importFiles}
            onchange={handleBackupFileChange}
          />
          <div class="grid gap-2 sm:grid-cols-[auto_minmax(0,1fr)_auto] sm:items-center">
            <Button
              variant="outline"
              disabled={preparingImport || restoringImport}
              onclick={chooseBackupFile}
              class="justify-self-start"
            >
              <Upload class="size-4" />
              Select backup
            </Button>
            <div
              class="border-border bg-muted/40 flex h-9 min-w-0 items-center rounded-2xl border px-3 text-sm"
            >
              <span class={['truncate', importFile ? 'text-foreground' : 'text-muted-foreground']}>
                {importFile?.name ?? 'No backup selected'}
              </span>
            </div>
            {#if importFile}
              <Button
                variant="ghost"
                size="icon"
                disabled={preparingImport || restoringImport}
                onclick={clearBackupFile}
                aria-label="Clear selected backup file"
              >
                <X class="size-4" />
                <span class="sr-only">Clear selected backup file</span>
              </Button>
            {/if}
          </div>
        </div>
        <div class="grid gap-2">
          <Label for="backup-vault-passphrase">Backup vault passphrase</Label>
          <Input
            id="backup-vault-passphrase"
            type="password"
            bind:value={importVaultPassphrase}
            autocomplete="current-password"
          />
        </div>
        {#if importMessage}
          <p class="text-sm font-medium {importSuccess ? 'text-green-600 dark:text-green-500' : 'text-destructive'}">
            {importMessage}
          </p>
        {/if}
        <Button
          variant="destructive"
          disabled={preparingImport || restoringImport}
          onclick={prepareBackupImport}
          class="justify-self-start"
        >
          <Upload class="size-4" />
          {preparingImport ? 'Checking…' : 'Review import'}
        </Button>
      </div>
    </div>
  </section>

  <section class="grid gap-6 py-8">
    <div class="grid gap-3">
      <div class="bg-muted text-foreground flex size-10 items-center justify-center rounded-lg">
        <Shield class="size-4.5" />
      </div>
      <div class="grid gap-2">
        <h2 class="text-xl font-semibold tracking-tight">Remembered device</h2>
        <p class="text-muted-foreground max-w-2xl text-sm leading-6">
          Store a browser-local vault key so reloads unlock automatically on this device.
        </p>
      </div>
    </div>

    <div class="grid max-w-xl gap-4">
      <p class="text-sm font-medium">Status: {rememberedDevice ? 'Enabled' : 'Disabled'}</p>
      {#if deviceMessage}
        <p class="text-sm font-medium {deviceSuccess ? 'text-green-600 dark:text-green-500' : 'text-destructive'}">
          {deviceMessage}
        </p>
      {/if}
      {#if rememberedDevice}
        <Button variant="outline" disabled={savingRememberedDevice} onclick={forgetDevice} class="justify-self-start">
          {savingRememberedDevice ? 'Working…' : 'Forget this device'}
        </Button>
      {:else}
        <Button disabled={savingRememberedDevice} onclick={rememberDevice} class="justify-self-start">
          <Save class="size-4" />
          {savingRememberedDevice ? 'Working…' : 'Remember this device'}
        </Button>
      {/if}
    </div>
  </section>
</div>

<AlertDialog.Root bind:open={confirmImportOpen}>
  <AlertDialog.Content>
    <AlertDialog.Header>
      <AlertDialog.Media>
        <Trash2 class="text-destructive size-8" />
      </AlertDialog.Media>
      <AlertDialog.Title>Destroy current entries and import backup?</AlertDialog.Title>
      <AlertDialog.Description>
        This will permanently delete all current entries on this server and replace them with
        {preparedImportPayload?.records.length ?? 0} entries from the backup exported on
        {preparedImportPayload ? backupDateLabel(preparedImportPayload.exportedAt) : 'unknown date'}.
      </AlertDialog.Description>
    </AlertDialog.Header>
    <AlertDialog.Footer>
      <AlertDialog.Cancel disabled={restoringImport}>Cancel</AlertDialog.Cancel>
      <AlertDialog.Action
        variant="destructive"
        disabled={restoringImport}
        onclick={(event) => {
          event.preventDefault();
          restorePreparedBackup();
        }}
      >
        {restoringImport ? 'Importing…' : 'Destroy current entries and import'}
      </AlertDialog.Action>
    </AlertDialog.Footer>
  </AlertDialog.Content>
</AlertDialog.Root>
