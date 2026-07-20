# Stack

## Framework
- sveltekit
- @sveltejs/adapter-node

## UI & Styling
- tailwindcss
- shadcn-svelte
- mode-watcher
- @lucide/svelte

## Data & Auth
- postgresql
- drizzle
- OpenID Connect Authorization Code flow with PKCE S256
- Custom server-side session (hashed token, HttpOnly cookie)

---

## Encryption

All user-created content is encrypted **client-side** before it is sent to the server.
The server stores only ciphertext and never has access to plaintext or the encryption key.
No additional dependencies are required — everything uses the browser-native Web Crypto API.

### Principles

Two keys are used, each with a distinct role:

- **KEK (Key Encryption Key)** — derived from the user's separate vault passphrase via PBKDF2. Used only to encrypt/decrypt the DEK. Never touches content directly.
- **DEK (Data Encryption Key)** — a random AES-256-GCM key generated once at vault creation. Used to encrypt all vault content. Stored in the database encrypted by the KEK.

```
Vault passphrase
      │
      ▼
PBKDF2  ◄── kek_salt (stored in DB, not secret)
      │
      ▼
KEK  ──── decrypt(encrypted_dek)
                  │
                  ▼
                 DEK  ──── lives in memory only
                  │
                  ├── encrypt(plaintext + random IV) ──► ciphertext + IV ──► DB
                  └── decrypt(ciphertext + IV)       ◄── ciphertext + IV ◄── DB
```

**Why two keys?** When the vault passphrase changes, only the DEK needs to be re-encrypted (a single row update). All encrypted records remain untouched. This makes passphrase changes O(1) regardless of how many entries exist and eliminates any risk of partial failure mid-update.

Additional principles:
- **Algorithm:** AES-256-GCM for all encryption
- **PBKDF2:** 310,000 iterations, SHA-256
- **Salt:** 16 random bytes, generated at vault creation, stored in plaintext (not secret)
- **IV:** 12 random bytes, generated fresh for every encrypt call, stored alongside ciphertext
- **Key lifetime:** DEK held in a memory-only Svelte store, never written to `localStorage`, `sessionStorage`, a cookie, or the DOM. Gone when the tab closes. As an optional convenience, a non-extractable copy of the DEK may be persisted to IndexedDB for "remember this device" functionality (see `src/lib/client/rememberedDevice.ts`).
- **SSR:** Decryption must always happen client-side (e.g. `onMount`), never during server-side rendering

### What to store in the database

Add these columns to the users table:

- `kek_salt` — text, non-null. 16 random bytes as base64. Generated at vault creation.
- `encrypted_dek` — text, non-null. The DEK encrypted by the KEK, as base64.
- `dek_iv` — text, non-null. The IV used when encrypting the DEK, as base64.

For any table that holds encrypted content, store two columns per encrypted field:

- `encrypted_[field]` — base64 ciphertext
- `[field]_iv` — base64 IV

Do not store plaintext versions of encrypted fields anywhere.

### Crypto utility

Create a shared module (e.g. `src/lib/crypto.ts`) that exports these functions. Copy them exactly — do not modify the algorithm parameters.

```ts
const PBKDF2_ITERATIONS = 310_000;

export async function deriveKEK(password: string, saltB64: string): Promise<CryptoKey> {
  const keyMaterial = await crypto.subtle.importKey(
    'raw', new TextEncoder().encode(password), 'PBKDF2', false, ['deriveKey']
  );
  return crypto.subtle.deriveKey(
    { name: 'PBKDF2', salt: base64ToBytes(saltB64), iterations: PBKDF2_ITERATIONS, hash: 'SHA-256' },
    keyMaterial,
    { name: 'AES-GCM', length: 256 },
    false,
    ['encrypt', 'decrypt']
  );
}

export async function generateDEK(): Promise<CryptoKey> {
  return crypto.subtle.generateKey({ name: 'AES-GCM', length: 256 }, true, ['encrypt', 'decrypt']);
}

export async function makeDEKNonExtractable(dek: CryptoKey): Promise<CryptoKey> {
  const raw = await crypto.subtle.exportKey('raw', dek);
  return crypto.subtle.importKey('raw', raw, { name: 'AES-GCM' }, false, ['encrypt', 'decrypt']);
}

export async function encryptDEK(kek: CryptoKey, dek: CryptoKey) {
  const iv = crypto.getRandomValues(new Uint8Array(12));
  const raw = await crypto.subtle.exportKey('raw', dek);
  const ciphertext = await crypto.subtle.encrypt({ name: 'AES-GCM', iv }, kek, raw);
  return {
    encryptedDEK: bytesToBase64(new Uint8Array(ciphertext)),
    dekIV: bytesToBase64(iv),
  };
}

export async function decryptDEK(kek: CryptoKey, encryptedDEK: string, dekIV: string): Promise<CryptoKey> {
  const raw = await crypto.subtle.decrypt(
    { name: 'AES-GCM', iv: base64ToBytes(dekIV) },
    kek,
    base64ToBytes(encryptedDEK)
  );
  return crypto.subtle.importKey('raw', raw, { name: 'AES-GCM' }, false, ['encrypt', 'decrypt']);
}

export async function encrypt(dek: CryptoKey, plaintext: string) {
  const iv = crypto.getRandomValues(new Uint8Array(12));
  const ciphertext = await crypto.subtle.encrypt(
    { name: 'AES-GCM', iv },
    dek,
    new TextEncoder().encode(plaintext)
  );
  return {
    ciphertext: bytesToBase64(new Uint8Array(ciphertext)),
    iv: bytesToBase64(iv),
  };
}

export async function decrypt(dek: CryptoKey, ciphertext: string, iv: string): Promise<string> {
  const plaintext = await crypto.subtle.decrypt(
    { name: 'AES-GCM', iv: base64ToBytes(iv) },
    dek,
    base64ToBytes(ciphertext)
  );
  return new TextDecoder().decode(plaintext);
}

function bytesToBase64(bytes: Uint8Array) {
  const chunkSize = 0x8000;
  let binary = '';
  for (let index = 0; index < bytes.length; index += chunkSize) {
    binary += String.fromCharCode(...bytes.subarray(index, index + chunkSize));
  }
  return btoa(binary);
}

const base64ToBytes = (s: string) => Uint8Array.from(atob(s), c => c.charCodeAt(0));
```

### Session key store

Create a Svelte store (e.g. `src/lib/stores/cryptoKey.ts`) to hold the DEK in memory.

```ts
import { writable } from 'svelte/store';
import { deriveKEK, decryptDEK } from '$lib/crypto';

export const sessionDEK = writable<CryptoKey | null>(null);

export async function unlockVault(password: string, kekSalt: string, encryptedDEK: string, dekIV: string) {
  const kek = await deriveKEK(password, kekSalt);
  const dek = await decryptDEK(kek, encryptedDEK, dekIV);
  sessionDEK.set(dek);
  return dek;
}

export function lockVault() {
  sessionDEK.set(null);
}
```

### Vault creation (client-side)

On a fresh database, OIDC authentication completes before vault setup is allowed. All key generation
happens in the browser. The server never sees the vault passphrase, plaintext DEK, or KEK — only the
encrypted DEK and salt arrive in the payload.

```
1. Generate kekSalt  →  crypto.getRandomValues(new Uint8Array(16))
2. Derive KEK        →  deriveKEK(vaultPassphrase, kekSalt)
3. Generate DEK      →  generateDEK()
4. Encrypt DEK       →  encryptDEK(kek, dek)  →  { encryptedDEK, dekIV }
5. POST to server:   { kekSalt, encryptedDEK, dekIV }
6. Server stores kek_salt, encrypted_dek, dek_iv and attaches the OIDC-authenticated setup session
```

### Login flow

Provider authentication and vault unlocking are two separate steps.

**Step 1 — OIDC login:**
The login page links to `/auth/login`. The server discovers the provider from `OIDC_ISSUER_URL`, starts
Authorization Code flow with state, nonce, and PKCE S256, then validates the response at
`/auth/callback`. The confidential client authenticates with `client_secret_basic`.

- Provider access policy is the sole admission control. The application has no identity or claim allowlist.
- On an existing installation, every admitted OIDC administrator maps to the existing singleton vault owner,
  preserving its ID, key metadata, remembered-device lookup key, and encrypted records.
- On a fresh installation, the validated OIDC identity creates a temporary local setup session. The browser
  performs Vault Creation, stores the DEK in `sessionDEK`, and attaches that session to the new singleton owner.
- A successful callback creates a database-backed local session with an HttpOnly cookie. It has a 24-hour
  sliding idle timeout and seven-day absolute lifetime. Only throttled same-origin signals from trusted
  pointer, keyboard, or click activity reset idle; navigation preloads and background requests do not.
- OIDC access and refresh tokens are not used as the application session. No refresh token is requested or
  stored. The ID token remains server-side only until it is used as `id_token_hint` for RP-Initiated Logout.
- User logout clears remembered and in-memory vault key material, then submits `POST /auth/logout`. The server
  deletes the local session before redirecting to the provider. Orphaned setup sessions are cleaned up locally
  by the login loader and do not invoke provider logout.

**Step 2 — Vault unlock:**
After OIDC login, the app renders `VaultUnlockGate` (`src/lib/components/VaultUnlockGate.svelte`).
This component gates all content behind the in-memory DEK:

1. On mount, try `loadRememberedDEK(email)` from IndexedDB. If found, restore it to `sessionDEK`.
2. If no remembered key, prompt for the vault passphrase.
3. User enters vault passphrase → `unlockVault(passphrase, user.kekSalt, user.encryptedDEK, user.dekIV)`
   → DEK stored in `sessionDEK`.
4. If "remember this device" is checked, `saveRememberedDEK(email, dek)` persists the raw DEK to IndexedDB.

The `SafeUser` type (returned by `toSafeUser` in `src/lib/server/auth.ts`) carries the singleton owner ID,
remembered-device key, `kekSalt`, `encryptedDEK`, and `dekIV`, so the existing vault remains available
without tying data access to an OIDC claim.

### Encrypting content (write path)

Before any content is sent to the server, call `encrypt(dek, plaintext)` and send only
the returned `ciphertext` and `iv`. Never include plaintext in a request body or URL.

If the DEK is missing from the store (tab was reopened, session expired), abort and
redirect to login before attempting to encrypt.

### Decrypting content (read path)

The server returns ciphertext and IV from the database. Decryption happens client-side
in `onMount` using `decrypt(dek, ciphertext, iv)`. If the DEK is missing, redirect to login.

Never pass ciphertext through a `load` function expecting to decrypt server-side.
Never render ciphertext directly into the page as a fallback.

### Vault passphrase change

Because all content is encrypted with the DEK (not the KEK), changing the vault passphrase only
requires re-encrypting the DEK — a single row update. All diary entries are untouched.

```
1. Derive oldKEK from currentVaultPassphrase + existing kekSalt
2. Decrypt DEK using oldKEK  (verify the vault passphrase is correct before proceeding)
3. Generate new kekSalt
4. Derive newKEK from newVaultPassphrase + newKekSalt
5. Re-encrypt the same DEK with newKEK  →  { newEncryptedDEK, newDekIV }
6. POST to server: { newKekSalt, newEncryptedDEK, newDekIV }
7. Server updates kek_salt + encrypted_dek + dek_iv in one row
8. sessionDEK is unchanged — the DEK itself never changed
```

### Backup and restore

Backups are downloaded as a single `.mvault` JSON envelope. The clear envelope contains
only format metadata and the encrypted DEK metadata (`kekSalt`, `encryptedDEK`, `dekIV`)
needed to recover the same vault key on a fresh server. The backup payload is encrypted
in the browser with the active DEK and contains encrypted record rows only:

```
{
  app: "memory-vault",
  version: 1,
  keyMaterial: { kekSalt, encryptedDEK, dekIV },
  payload: encrypt(DEK, { exportedAt, user, records })
}
```

Export flow:

1. Browser reads the in-memory DEK from `sessionDEK`.
2. Browser fetches encrypted records from `/api/records`.
3. Browser encrypts the backup payload with the DEK.
4. Browser downloads the `.mvault` file. No plaintext content or raw DEK is sent to the server.

Import flow:

1. Browser reads the `.mvault` file and derives the KEK from the entered backup vault passphrase.
2. Browser decrypts the backup DEK from the backup key metadata.
3. Browser decrypts and validates the backup payload locally.
4. After destructive confirmation, the browser sends only encrypted records and encrypted DEK metadata to `/api/backup/import`.
5. Server updates the current user's vault key metadata, deletes current encrypted records, and inserts backup encrypted records in one transaction.
6. Browser replaces `sessionDEK` with the imported DEK and clears the remembered-device key for the current account.

Import intentionally does not restore OIDC identity data, sessions, or cookies. Provider admission remains
unchanged. After restore, the vault passphrase is the passphrase that unlocked the imported backup.

### Constraints

- **Search** is not possible on encrypted fields. To filter entries, decrypt all records into memory client-side and use `Array.filter`.
- **Forgotten vault passphrase** means the DEK cannot be decrypted and all content is permanently unrecoverable. There is no server-side reset path.
- The **kek_salt** is not secret and can be returned in plaintext from `load`. Its only role is to make the derived KEK unique and prevent precomputed dictionary attacks.
