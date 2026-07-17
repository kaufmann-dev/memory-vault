<script lang="ts">
  import EmptyState from '$lib/components/EmptyState.svelte';
  import EntryModal from '$lib/components/EntryModal.svelte';
  import LoadingState from '$lib/components/LoadingState.svelte';
  import PageHeader from '$lib/components/PageHeader.svelte';
  import CollectionNav from '$lib/components/CollectionNav.svelte';
  import {
    createEncryptedRecord,
    decryptRecords,
    deleteEncryptedRecord,
    fetchEncryptedRecords,
    updateEncryptedRecord
  } from '$lib/client/records';
  import { sessionDEK } from '$lib/stores/cryptoKey';
  import type { EncryptedRecord, SecretCategory, SecretPayload } from '$lib/types';
  import { get } from 'svelte/store';
  import { onMount } from 'svelte';
  import type { Component } from 'svelte';
  import { toast } from 'svelte-sonner';
  import {
    Braces,
    Copy,
    Eye,
    EyeOff,
    FileKey,
    Fingerprint,
    KeyRound,
    Landmark,
    Plus,
    Search,
    Server,
    ShieldCheck,
    Trash2,
    Wifi,
    X
  } from '@lucide/svelte';
  import * as Card from '$lib/components/ui/card/index.js';
  import * as Select from '$lib/components/ui/select/index.js';
  import { Button } from '$lib/components/ui/button/index.js';
  import { Input } from '$lib/components/ui/input/index.js';
  import { Label } from '$lib/components/ui/label/index.js';
  import { Textarea } from '$lib/components/ui/textarea/index.js';

  type SecretItem = {
    record: EncryptedRecord;
    payload: SecretPayload;
  };

  type RawSecretPayload = Partial<SecretPayload>;

  type SecretFilter = 'all' | SecretCategory;
  type SortOrder = 'newest' | 'oldest';
  type SecretCategoryMeta = {
    id: SecretCategory;
    label: string;
    singular: string;
    icon: Component;
    secretLabel: string;
    usernameLabel: string;
  };

  const vpnProtocols = [
    'OpenVPN',
    'WireGuard',
    'IKEv2/IPsec',
    'L2TP/IPsec',
    'IPsec',
    'SSTP',
    'PPTP',
    'SSL VPN',
    'Other'
  ] as const;

  const defaultVpnProtocol = vpnProtocols[0];
  const remoteProtocols = ['RDP', 'SSH', 'VNC', 'SPICE', 'SFTP'] as const;
  const defaultRemoteProtocol = remoteProtocols[0];
  const copyableGenericUsernameCategories = new Set<SecretCategory>(['password', 'api_key', 'wifi']);

  const secretCategories: SecretCategoryMeta[] = [
    {
      id: 'password',
      label: 'Passwords',
      singular: 'Password',
      icon: KeyRound,
      secretLabel: 'Password or passphrase',
      usernameLabel: 'Username'
    },
    {
      id: 'api_key',
      label: 'API Keys',
      singular: 'API Key',
      icon: Braces,
      secretLabel: 'API key',
      usernameLabel: 'Account or service user'
    },
    {
      id: 'encryption_key',
      label: 'Encryption Keys',
      singular: 'Encryption Key',
      icon: FileKey,
      secretLabel: 'Encryption key',
      usernameLabel: 'Owner or context'
    },
    {
      id: 'wifi',
      label: 'WiFi',
      singular: 'WiFi',
      icon: Wifi,
      secretLabel: 'WiFi password',
      usernameLabel: 'Network user'
    },
    {
      id: 'pgp_key',
      label: 'PGP Keys',
      singular: 'PGP Key',
      icon: Fingerprint,
      secretLabel: 'PGP key',
      usernameLabel: 'Identity'
    },
    {
      id: 'bank_account',
      label: 'Bank accounts',
      singular: 'Bank account',
      icon: Landmark,
      secretLabel: 'IBAN',
      usernameLabel: 'Account holder'
    },
    {
      id: 'vpn',
      label: 'VPN',
      singular: 'VPN',
      icon: ShieldCheck,
      secretLabel: 'Password',
      usernameLabel: 'Username'
    },
    {
      id: 'remote_connection',
      label: 'Remote Connections',
      singular: 'Remote Connection',
      icon: Server,
      secretLabel: 'Password',
      usernameLabel: 'Username'
    }
  ];

  const nowIso = () => new Date().toISOString();
  const categoryById = new Map(secretCategories.map((category) => [category.id, category]));
  const fallbackCategory = secretCategories[0];

  const emptySecret = (category: SecretCategory = 'password'): SecretPayload => ({
    title: '',
    category,
    username: '',
    secret: '',
    publicKey: '',
    fingerprint: '',
    passphrase: '',
    iban: '',
    accountHolder: '',
    bank: '',
    bic: '',
    vpnProtocol: category === 'vpn' ? defaultVpnProtocol : '',
    vpnGateway: '',
    vpnNtDomain: '',
    remoteProtocol: category === 'remote_connection' ? defaultRemoteProtocol : '',
    remoteServer: '',
    remoteDomain: '',
    notes: '',
    createdAt: nowIso(),
    updatedAt: nowIso()
  });

  let dek: CryptoKey | null = null;
  let loading = $state(true);
  let loadError = $state('');
  let saving = $state(false);
  let secrets: SecretItem[] = $state([]);
  let form = $state(emptySecret());
  let editingSecretId: string | null = $state(null);
  let secretFormOpen = $state(false);
  let secretVisible = $state(false);
  let passphraseVisible = $state(false);
  let query = $state('');
  let activeCategory = $state<SecretFilter>('all');
  let sortOrder = $state<SortOrder>('newest');

  let filteredSecrets = $derived.by(() => {
    const normalizedQuery = query.trim().toLowerCase();

    return secrets
      .filter((item) => {
        if (activeCategory !== 'all' && item.payload.category !== activeCategory) return false;
        if (!normalizedQuery) return true;

        const category = categoryById.get(item.payload.category) ?? fallbackCategory;
        return `${item.payload.title} ${item.payload.username} ${item.payload.publicKey} ${item.payload.fingerprint} ${item.payload.iban} ${item.payload.accountHolder} ${item.payload.bank} ${item.payload.bic} ${item.payload.vpnProtocol} ${item.payload.vpnGateway} ${item.payload.vpnNtDomain} ${item.payload.remoteProtocol} ${item.payload.remoteServer} ${item.payload.remoteDomain} ${item.payload.notes} ${category.label}`
          .toLowerCase()
          .includes(normalizedQuery);
      })
      .sort((a, b) => {
        const direction = sortOrder === 'newest' ? -1 : 1;
        return a.payload.updatedAt.localeCompare(b.payload.updatedAt) * direction;
      });
  });

  let hasFilters = $derived(Boolean(query.trim() || activeCategory !== 'all' || sortOrder !== 'newest'));

  let navItems = $derived([
    { id: 'all' as SecretFilter, label: 'All secrets', icon: KeyRound, count: secrets.length },
    ...secretCategories.map((category) => ({
      id: category.id as SecretFilter,
      label: category.label,
      icon: category.icon,
      count: categoryCount(category.id)
    }))
  ]);

  let formCategory = $derived(categoryById.get(form.category) ?? fallbackCategory);
  let formIsBankAccount = $derived(form.category === 'bank_account');
  let formIsPgpKey = $derived(form.category === 'pgp_key');
  let formIsVpn = $derived(form.category === 'vpn');
  let formIsRemoteConnection = $derived(form.category === 'remote_connection');
  let canSaveSecret = $derived(
    formIsBankAccount
      ? Boolean(form.title.trim() && form.iban.trim())
      : formIsPgpKey
        ? Boolean(form.title.trim() && (form.secret.trim() || form.publicKey.trim()))
        : formIsVpn
          ? Boolean(form.title.trim() && form.vpnGateway.trim())
          : formIsRemoteConnection
            ? Boolean(form.title.trim() && form.remoteServer.trim())
          : Boolean(form.title.trim() && form.secret.trim())
  );

  function categoryCount(category: SecretCategory) {
    return secrets.filter((secret) => secret.payload.category === category).length;
  }

  function formatDate(value: string) {
    return new Intl.DateTimeFormat(undefined, {
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    }).format(new Date(value));
  }

  function clearFilters() {
    query = '';
    activeCategory = 'all';
    sortOrder = 'newest';
  }

  function createCategory() {
    return activeCategory === 'all' ? 'password' : activeCategory;
  }

  function normalizeVpnProtocol(value: string | undefined) {
    if (value && vpnProtocols.includes(value as (typeof vpnProtocols)[number])) return value;
    return defaultVpnProtocol;
  }

  function normalizeRemoteProtocol(value: string | undefined) {
    if (value && remoteProtocols.includes(value as (typeof remoteProtocols)[number])) return value;
    return defaultRemoteProtocol;
  }

  function normalizeSecretPayload(payload: RawSecretPayload): SecretPayload {
    const category =
      payload.category && categoryById.has(payload.category) ? payload.category : fallbackCategory.id;

    return {
      title: payload.title ?? '',
      category,
      username: payload.username ?? '',
      secret: payload.secret ?? '',
      publicKey: payload.publicKey ?? '',
      fingerprint: payload.fingerprint ?? '',
      passphrase: payload.passphrase ?? '',
      iban: payload.iban ?? '',
      accountHolder: payload.accountHolder ?? '',
      bank: payload.bank ?? '',
      bic: payload.bic ?? '',
      vpnProtocol: category === 'vpn' ? normalizeVpnProtocol(payload.vpnProtocol) : '',
      vpnGateway: category === 'vpn' ? (payload.vpnGateway ?? '') : '',
      vpnNtDomain: category === 'vpn' ? (payload.vpnNtDomain ?? '') : '',
      remoteProtocol: category === 'remote_connection' ? normalizeRemoteProtocol(payload.remoteProtocol) : '',
      remoteServer: category === 'remote_connection' ? (payload.remoteServer ?? '') : '',
      remoteDomain: category === 'remote_connection' ? (payload.remoteDomain ?? '') : '',
      notes: payload.notes ?? '',
      createdAt: payload.createdAt ?? nowIso(),
      updatedAt: payload.updatedAt ?? payload.createdAt ?? nowIso()
    };
  }

  function openCreateSecret() {
    editingSecretId = null;
    form = emptySecret(createCategory());
    secretVisible = true;
    passphraseVisible = false;
    secretFormOpen = true;
  }

  function openSecret(item: SecretItem) {
    editingSecretId = item.record.id;
    form = { ...item.payload };
    secretVisible = false;
    passphraseVisible = false;
    secretFormOpen = true;
  }

  function closeSecretForm() {
    secretFormOpen = false;
    editingSecretId = null;
    form = emptySecret();
    secretVisible = false;
    passphraseVisible = false;
  }

  function maskSecret(value: string) {
    if (!value) return '';
    return '*'.repeat(Math.min(Math.max(value.length, 8), 48));
  }

  async function loadSecrets() {
    if (!dek) return;
    loading = true;
    loadError = '';
    try {
      const records = await fetchEncryptedRecords('secret');
      secrets = (await decryptRecords<RawSecretPayload>(records, dek)).map((item) => ({
        record: item.record,
        payload: normalizeSecretPayload(item.payload)
      }));
    } catch {
      loadError = 'Could not decrypt secrets.';
      toast.error('Could not decrypt secrets.');
    } finally {
      loading = false;
    }
  }

  async function saveSecret() {
    if (!dek || saving || !canSaveSecret) return;
    saving = true;
    try {
      const timestamp = nowIso();
      const isBankAccount = form.category === 'bank_account';
      const isPgpKey = form.category === 'pgp_key';
      const isVpn = form.category === 'vpn';
      const isRemoteConnection = form.category === 'remote_connection';
      const payload: SecretPayload = {
        title: form.title.trim(),
        category: form.category,
        username: isBankAccount ? '' : form.username.trim(),
        secret: isBankAccount ? '' : form.secret,
        publicKey: isPgpKey ? form.publicKey : '',
        fingerprint: isPgpKey ? form.fingerprint.trim() : '',
        passphrase: isPgpKey ? form.passphrase : '',
        iban: isBankAccount ? form.iban.trim() : '',
        accountHolder: isBankAccount ? form.accountHolder.trim() : '',
        bank: isBankAccount ? form.bank.trim() : '',
        bic: isBankAccount ? form.bic.trim() : '',
        vpnProtocol: isVpn ? normalizeVpnProtocol(form.vpnProtocol) : '',
        vpnGateway: isVpn ? form.vpnGateway.trim() : '',
        vpnNtDomain: isVpn ? form.vpnNtDomain.trim() : '',
        remoteProtocol: isRemoteConnection ? normalizeRemoteProtocol(form.remoteProtocol) : '',
        remoteServer: isRemoteConnection ? form.remoteServer.trim() : '',
        remoteDomain: isRemoteConnection ? form.remoteDomain.trim() : '',
        notes: form.notes.trim(),
        createdAt: editingSecretId ? form.createdAt : timestamp,
        updatedAt: timestamp
      };

      if (editingSecretId) {
        const id = editingSecretId;
        const record = await updateEncryptedRecord(id, 'secret', payload, dek);
        secrets = secrets.map((secret) => (secret.record.id === id ? { record, payload } : secret));
      } else {
        const record = await createEncryptedRecord('secret', payload, dek);
        secrets = [...secrets, { record, payload }];
      }

      closeSecretForm();
    } catch {
      toast.error('Could not save this secret.');
    } finally {
      saving = false;
    }
  }

  async function removeCurrentSecret() {
    if (!editingSecretId || saving || !confirm('Delete this secret?')) return;
    const id = editingSecretId;
    saving = true;
    try {
      await deleteEncryptedRecord(id);
      secrets = secrets.filter((secret) => secret.record.id !== id);
      closeSecretForm();
    } catch {
      toast.error('Could not delete this secret.');
    } finally {
      saving = false;
    }
  }

  async function copyValue(value: string, label = 'Secret') {
    if (!value) return;
    try {
      await navigator.clipboard.writeText(value);
      toast.success(`${label} copied.`);
    } catch {
      toast.error(`Could not copy this ${label.toLowerCase()}.`);
    }
  }

  onMount(async () => {
    dek = get(sessionDEK);
    if (!dek) {
      loading = false;
      return;
    }
    await loadSecrets();
  });
</script>

{#snippet copyableFieldLabel(forId: string, label: string, value: string)}
  <div class="flex flex-wrap items-center justify-between gap-2">
    <Label for={forId}>{label}</Label>
    <Button type="button" variant="ghost" size="sm" onclick={() => copyValue(value, label)} disabled={!value}>
      <Copy class="size-4" /> Copy
    </Button>
  </div>
{/snippet}

<PageHeader title="Secrets" description="Encrypted passwords, keys, accounts, connections, and more.">
  <Button onclick={openCreateSecret}>
    <Plus class="size-4" />
    New
  </Button>
</PageHeader>

{#snippet categoryRow(item: (typeof navItems)[number])}
  {@const Icon = item.icon}
  <Icon class="text-muted-foreground size-4 shrink-0" />
  <span class="min-w-0 flex-1 truncate text-left">{item.label}</span>
  <span class="text-muted-foreground ml-auto shrink-0 text-xs font-semibold">{item.count}</span>
{/snippet}

<div class="grid gap-6 md:grid-cols-[16rem_minmax(0,1fr)]">
  <aside class="min-w-0" aria-label="Secret categories">
    <CollectionNav
      items={navItems}
      selected={activeCategory}
      onSelect={(id) => (activeCategory = id as SecretFilter)}
      row={categoryRow}
      title="Categories"
      searchPlaceholder="Search categories"
      emptyText="No categories found."
      ariaLabel="Select category"
    />
  </aside>

  <section class="min-w-0">
    <div class="bg-background flex flex-wrap items-center gap-3 border-y py-3">
      <div class="relative min-w-0 flex-1">
        <Search class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
        <Input bind:value={query} placeholder="Search secrets" class="pl-9" aria-label="Search secrets" />
      </div>
      <Select.Root type="single" value={sortOrder} onValueChange={(value) => (sortOrder = value as SortOrder)}>
        <Select.Trigger class="w-44">{sortOrder === 'newest' ? 'Newest updated' : 'Oldest updated'}</Select.Trigger>
        <Select.Content>
          <Select.Item value="newest" label="Newest updated">Newest updated</Select.Item>
          <Select.Item value="oldest" label="Oldest updated">Oldest updated</Select.Item>
        </Select.Content>
      </Select.Root>
      {#if hasFilters}
        <Button variant="ghost" size="sm" onclick={clearFilters}>
          <X class="size-4" /> Clear
        </Button>
      {/if}
    </div>

    <div class="mt-4">
      {#if loading}
        <LoadingState message="Decrypting secrets..." />
      {:else if loadError}
        <EmptyState title="Could not load secrets" description={loadError} />
      {:else if secrets.length === 0}
        <EmptyState title="No secrets yet" description="Create a secret to keep sensitive material encrypted in this vault." />
      {:else if filteredSecrets.length === 0}
        <EmptyState title="No matching secrets" description="Clear filters or search for a different secret." />
      {:else}
        <div class="grid gap-3 xl:grid-cols-2">
          {#each filteredSecrets as item (item.record.id)}
            {@const category = categoryById.get(item.payload.category) ?? fallbackCategory}
            {@const Icon = category.icon}
            <Card.Root class="gap-0 py-0">
              <button
                type="button"
                onclick={() => openSecret(item)}
                aria-label={`Open secret: ${item.payload.title}`}
                class="hover:bg-muted/40 grid w-full gap-3 rounded-xl p-4 text-left transition-colors"
              >
                <span class="flex min-w-0 items-start gap-3">
                  <span class="bg-muted text-foreground grid size-9 shrink-0 place-items-center rounded-lg">
                    <Icon class="size-4" />
                  </span>
                  <span class="grid min-w-0 flex-1 gap-1">
                    <span class="truncate font-semibold">{item.payload.title}</span>
                    <span class="text-muted-foreground flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1 text-xs">
                      <span>{category.singular}</span>
                      {#if item.payload.category === 'bank_account' && (item.payload.bank || item.payload.accountHolder)}
                        <span class="text-border">/</span>
                        <span class="min-w-0 truncate">{item.payload.bank || item.payload.accountHolder}</span>
                      {:else if item.payload.category === 'vpn' && (item.payload.vpnGateway || item.payload.username)}
                        <span class="text-border">/</span>
                        <span class="min-w-0 truncate">{item.payload.vpnGateway || item.payload.username}</span>
                      {:else if item.payload.category === 'remote_connection' && (item.payload.remoteServer || item.payload.username)}
                        <span class="text-border">/</span>
                        <span class="min-w-0 truncate">{item.payload.remoteServer || item.payload.username}</span>
                      {:else if item.payload.username}
                        <span class="text-border">/</span>
                        <span class="min-w-0 truncate">{item.payload.username}</span>
                      {/if}
                    </span>
                  </span>
                </span>
                {#if item.payload.notes}
                  <span class="text-muted-foreground line-clamp-2 text-sm leading-relaxed break-words">
                    {item.payload.notes}
                  </span>
                {/if}
                <span class="text-muted-foreground text-xs">
                  <time datetime={item.payload.updatedAt}>{formatDate(item.payload.updatedAt)}</time>
                </span>
              </button>
            </Card.Root>
          {/each}
        </div>
      {/if}
    </div>
  </section>
</div>

<EntryModal
  open={secretFormOpen}
  title={editingSecretId ? 'Secret details' : 'New secret'}
  description={editingSecretId ? 'View, update, copy, or delete this encrypted secret.' : 'Secrets are encrypted before they leave this browser.'}
  onClose={closeSecretForm}
>
  <form
    class="grid gap-4"
    autocomplete="off"
    onsubmit={(event) => {
      event.preventDefault();
      saveSecret();
    }}
  >
    <div class="grid gap-2">
      <Label for="secret-category">Category</Label>
      <Select.Root
        type="single"
        value={form.category}
        onValueChange={(value) => {
          form.category = value as SecretCategory;
          if (form.category === 'vpn' && !form.vpnProtocol) form.vpnProtocol = defaultVpnProtocol;
          if (form.category === 'remote_connection' && !form.remoteProtocol) form.remoteProtocol = defaultRemoteProtocol;
        }}
      >
        <Select.Trigger id="secret-category" class="w-full">{formCategory.label}</Select.Trigger>
        <Select.Content>
          {#each secretCategories as category (category.id)}
            <Select.Item value={category.id} label={category.label}>{category.label}</Select.Item>
          {/each}
        </Select.Content>
      </Select.Root>
    </div>

    <div class="grid gap-2">
      <Label for="secret-title">Title</Label>
      <Input id="secret-title" bind:value={form.title} maxlength={160} required />
    </div>

    {#if formIsBankAccount}
      <div class="grid gap-2">
        {@render copyableFieldLabel('secret-iban', 'IBAN', form.iban)}
        <Input id="secret-iban" bind:value={form.iban} maxlength={80} required autocomplete="off" spellcheck="false" />
      </div>

      <div class="grid gap-2">
        {@render copyableFieldLabel('secret-account-holder', 'Account holder', form.accountHolder)}
        <Input id="secret-account-holder" bind:value={form.accountHolder} maxlength={240} />
      </div>

      <div class="grid gap-2">
        <Label for="secret-bank">Bank</Label>
        <Input id="secret-bank" bind:value={form.bank} maxlength={240} />
      </div>

      <div class="grid gap-2">
        {@render copyableFieldLabel('secret-bic', 'BIC', form.bic)}
        <Input id="secret-bic" bind:value={form.bic} maxlength={80} autocomplete="off" spellcheck="false" />
      </div>
    {:else if formIsVpn}
      <div class="grid gap-2">
        <Label for="secret-vpn-protocol">Protocol</Label>
        <Select.Root
          type="single"
          value={normalizeVpnProtocol(form.vpnProtocol)}
          onValueChange={(value) => {
            form.vpnProtocol = normalizeVpnProtocol(value);
          }}
        >
          <Select.Trigger id="secret-vpn-protocol" class="w-full">{normalizeVpnProtocol(form.vpnProtocol)}</Select.Trigger>
          <Select.Content>
            {#each vpnProtocols as protocol (protocol)}
              <Select.Item value={protocol} label={protocol}>{protocol}</Select.Item>
            {/each}
          </Select.Content>
        </Select.Root>
      </div>

      <div class="grid gap-2">
        {@render copyableFieldLabel('secret-vpn-gateway', 'Gateway', form.vpnGateway)}
        <Input id="secret-vpn-gateway" bind:value={form.vpnGateway} maxlength={240} required autocomplete="off" spellcheck="false" />
      </div>

      <div class="grid gap-2">
        {@render copyableFieldLabel('secret-username', 'Username', form.username)}
        <Input id="secret-username" bind:value={form.username} maxlength={240} autocomplete="off" spellcheck="false" />
      </div>

      <div class="grid gap-2">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <Label for="secret-value">Password</Label>
          <div class="flex gap-1">
            <Button
              type="button"
              variant="ghost"
              size="sm"
              onclick={() => (secretVisible = !secretVisible)}
              aria-pressed={secretVisible}
            >
              {#if secretVisible}
                <EyeOff class="size-4" /> Hide
              {:else}
                <Eye class="size-4" /> Reveal
              {/if}
            </Button>
            <Button type="button" variant="ghost" size="sm" onclick={() => copyValue(form.secret, 'Password')} disabled={!form.secret}>
              <Copy class="size-4" /> Copy
            </Button>
          </div>
        </div>
        <Input
          id="secret-value"
          bind:value={form.secret}
          type="text"
          class={secretVisible ? undefined : 'masked-text-input'}
          autocomplete="off"
          autocapitalize="none"
          spellcheck="false"
        />
      </div>

      <div class="grid gap-2">
        {@render copyableFieldLabel('secret-vpn-nt-domain', 'NT Domain', form.vpnNtDomain)}
        <Input id="secret-vpn-nt-domain" bind:value={form.vpnNtDomain} maxlength={240} autocomplete="off" spellcheck="false" />
      </div>
    {:else if formIsRemoteConnection}
      <div class="grid gap-2">
        <Label for="secret-remote-protocol">Protocol</Label>
        <Select.Root
          type="single"
          value={normalizeRemoteProtocol(form.remoteProtocol)}
          onValueChange={(value) => {
            form.remoteProtocol = normalizeRemoteProtocol(value);
          }}
        >
          <Select.Trigger id="secret-remote-protocol" class="w-full">{normalizeRemoteProtocol(form.remoteProtocol)}</Select.Trigger>
          <Select.Content>
            {#each remoteProtocols as protocol (protocol)}
              <Select.Item value={protocol} label={protocol}>{protocol}</Select.Item>
            {/each}
          </Select.Content>
        </Select.Root>
      </div>

      <div class="grid gap-2">
        {@render copyableFieldLabel('secret-remote-server', 'Server', form.remoteServer)}
        <Input id="secret-remote-server" bind:value={form.remoteServer} maxlength={240} required autocomplete="off" spellcheck="false" />
      </div>

      <div class="grid gap-2">
        {@render copyableFieldLabel('secret-username', 'Username', form.username)}
        <Input id="secret-username" bind:value={form.username} maxlength={240} autocomplete="off" spellcheck="false" />
      </div>

      <div class="grid gap-2">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <Label for="secret-value">Password</Label>
          <div class="flex gap-1">
            <Button
              type="button"
              variant="ghost"
              size="sm"
              onclick={() => (secretVisible = !secretVisible)}
              aria-pressed={secretVisible}
            >
              {#if secretVisible}
                <EyeOff class="size-4" /> Hide
              {:else}
                <Eye class="size-4" /> Reveal
              {/if}
            </Button>
            <Button type="button" variant="ghost" size="sm" onclick={() => copyValue(form.secret, 'Password')} disabled={!form.secret}>
              <Copy class="size-4" /> Copy
            </Button>
          </div>
        </div>
        <Input
          id="secret-value"
          bind:value={form.secret}
          type="text"
          class={secretVisible ? undefined : 'masked-text-input'}
          autocomplete="off"
          autocapitalize="none"
          spellcheck="false"
        />
      </div>

      <div class="grid gap-2">
        {@render copyableFieldLabel('secret-remote-domain', 'Domain', form.remoteDomain)}
        <Input id="secret-remote-domain" bind:value={form.remoteDomain} maxlength={240} autocomplete="off" spellcheck="false" />
      </div>
    {:else}
      <div class="grid gap-2">
        {#if copyableGenericUsernameCategories.has(form.category)}
          {@render copyableFieldLabel('secret-username', formCategory.usernameLabel, form.username)}
        {:else}
          <Label for="secret-username">{formCategory.usernameLabel}</Label>
        {/if}
        <Input id="secret-username" bind:value={form.username} maxlength={240} />
      </div>

      {#if formIsPgpKey}
        <div class="grid gap-2">
          {@render copyableFieldLabel('secret-fingerprint', 'Fingerprint', form.fingerprint)}
          <Input id="secret-fingerprint" bind:value={form.fingerprint} maxlength={240} autocomplete="off" spellcheck="false" />
        </div>

        <div class="grid gap-2">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <Label for="secret-passphrase">Passphrase</Label>
            <div class="flex gap-1">
              <Button
                type="button"
                variant="ghost"
                size="sm"
                onclick={() => (passphraseVisible = !passphraseVisible)}
                aria-pressed={passphraseVisible}
              >
                {#if passphraseVisible}
                  <EyeOff class="size-4" /> Hide
                {:else}
                  <Eye class="size-4" /> Reveal
                {/if}
              </Button>
              <Button type="button" variant="ghost" size="sm" onclick={() => copyValue(form.passphrase, 'Passphrase')} disabled={!form.passphrase}>
                <Copy class="size-4" /> Copy
              </Button>
            </div>
          </div>
          <Input
            id="secret-passphrase"
            bind:value={form.passphrase}
            type="text"
            class={passphraseVisible ? undefined : 'masked-text-input'}
            autocomplete="off"
            autocapitalize="none"
            spellcheck="false"
          />
        </div>

        <div class="grid gap-2">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <Label for="secret-private-key">Private key</Label>
            <div class="flex gap-1">
              <Button
                type="button"
                variant="ghost"
                size="sm"
                onclick={() => (secretVisible = !secretVisible)}
                aria-pressed={secretVisible}
              >
                {#if secretVisible}
                  <EyeOff class="size-4" /> Hide
                {:else}
                  <Eye class="size-4" /> Reveal
                {/if}
              </Button>
              <Button type="button" variant="ghost" size="sm" onclick={() => copyValue(form.secret, 'Private key')} disabled={!form.secret}>
                <Copy class="size-4" /> Copy
              </Button>
            </div>
          </div>
          {#if secretVisible}
            <Textarea id="secret-private-key" bind:value={form.secret} class="min-h-48 font-mono text-xs" spellcheck="false" />
          {:else}
            <Textarea
              id="secret-private-key"
              value={maskSecret(form.secret)}
              readonly
              class="masked-text-input min-h-24 font-mono text-xs"
              aria-label="Hidden private key"
            />
          {/if}
        </div>

        <div class="grid gap-2">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <Label for="secret-public-key">Public key</Label>
            <Button type="button" variant="ghost" size="sm" onclick={() => copyValue(form.publicKey, 'Public key')} disabled={!form.publicKey}>
              <Copy class="size-4" /> Copy
            </Button>
          </div>
          <Textarea id="secret-public-key" bind:value={form.publicKey} class="min-h-40 font-mono text-xs" spellcheck="false" />
        </div>
      {:else}
        <div class="grid gap-2">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <Label for="secret-value">{formCategory.secretLabel}</Label>
            <div class="flex gap-1">
              <Button
                type="button"
                variant="ghost"
                size="sm"
                onclick={() => (secretVisible = !secretVisible)}
                aria-pressed={secretVisible}
              >
                {#if secretVisible}
                  <EyeOff class="size-4" /> Hide
                {:else}
                  <Eye class="size-4" /> Reveal
                {/if}
              </Button>
              <Button type="button" variant="ghost" size="sm" onclick={() => copyValue(form.secret)} disabled={!form.secret}>
                <Copy class="size-4" /> Copy
              </Button>
            </div>
          </div>
          <Input
            id="secret-value"
            bind:value={form.secret}
            type="text"
            class={secretVisible ? undefined : 'masked-text-input'}
            required
            autocomplete="off"
            autocapitalize="none"
            spellcheck="false"
          />
        </div>
      {/if}
    {/if}

    <div class="grid gap-2">
      <Label for="secret-notes">Notes</Label>
      <Textarea id="secret-notes" bind:value={form.notes} maxlength={20000} class="min-h-28" />
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3">
      <span class="text-muted-foreground text-sm">{form.notes.length}/20000</span>
      <div class="flex gap-2">
        {#if editingSecretId}
          <Button
            variant="outline"
            type="button"
            class="text-destructive hover:text-destructive"
            onclick={removeCurrentSecret}
            disabled={saving}
          >
            <Trash2 class="size-4" /> Delete
          </Button>
        {/if}
        <Button type="submit" disabled={saving || !canSaveSecret}>
          {saving ? 'Saving...' : editingSecretId ? 'Save changes' : 'Create'}
        </Button>
      </div>
    </div>
  </form>
</EntryModal>
