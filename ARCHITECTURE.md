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
- Custom session-based auth (token hash, httpOnly cookie)

---

## Encryption

All user-created content is encrypted **client-side** before it is sent to the server.
The server stores only ciphertext and never has access to plaintext or the encryption key.
No additional dependencies are required — everything uses the browser-native Web Crypto API.

### Principles

Two keys are used, each with a distinct role:

- **KEK (Key Encryption Key)** — derived from the user's password via PBKDF2. Used only to encrypt/decrypt the DEK. Never touches content directly.
- **DEK (Data Encryption Key)** — a random AES-256-GCM key generated once at account creation. Used to encrypt all diary content. Stored in the database encrypted by the KEK.

```
Login password
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

**Why two keys?** When the password changes, only the DEK needs to be re-encrypted (a single row update). All diary entries remain untouched. This makes password changes O(1) regardless of how many entries exist, and eliminates any risk of partial failure mid-update.

Additional principles:
- **Algorithm:** AES-256-GCM for all encryption
- **PBKDF2:** 310,000 iterations, SHA-256
- **Salt:** 16 random bytes, generated at account creation, stored in plaintext (not secret)
- **IV:** 12 random bytes, generated fresh for every encrypt call, stored alongside ciphertext
- **Key lifetime:** DEK held in a memory-only Svelte store, never written to `localStorage`, `sessionStorage`, a cookie, or the DOM. Gone when the tab closes. As an optional convenience, a non-extractable copy of the DEK may be persisted to IndexedDB for "remember this device" functionality (see `src/lib/client/rememberedDevice.ts`).
- **SSR:** Decryption must always happen client-side (e.g. `onMount`), never during server-side rendering

### What to store in the database

Add these columns to the users table:

- `kek_salt` — text, non-null. 16 random bytes as base64. Generated at registration.
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

const bytesToBase64 = (b: Uint8Array) => btoa(String.fromCharCode(...b));
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

### Account creation (client-side)

All key generation happens in the browser during registration. The server never sees
the plaintext DEK or KEK — only the encrypted DEK and the salt arrive in the payload.

```
1. Generate kekSalt  →  crypto.getRandomValues(new Uint8Array(16))
2. Derive KEK        →  deriveKEK(password, kekSalt)
3. Generate DEK      →  generateDEK()
4. Encrypt DEK       →  encryptDEK(kek, dek)  →  { encryptedDEK, dekIV }
5. POST to server:  { email, password, kekSalt, encryptedDEK, dekIV }
6. Server stores kek_salt, encrypted_dek, dek_iv — it never sees the plaintext DEK
```

### Login flow

Account authentication and vault unlocking are two separate steps.

**Step 1 — Account login:**
The login page (`src/routes/login/+page.server.ts`) only checks whether an admin user exists and
returns `{ hasAdmin }`. No crypto fields are exposed at this stage.

- If `hasAdmin` is true: the user submits email + account password → POST `/api/auth/login` →
  server validates credentials, creates a session cookie → redirect to `/`.
- If `hasAdmin` is false (first setup): the user fills in name, email, account password, and vault
  passphrase → client-side key generation (see Account Creation above) → POST `/api/auth/setup` →
  server creates the user and session → DEK stored in `sessionDEK` → redirect to `/`.

**Step 2 — Vault unlock:**
After account login, the app renders `VaultUnlockGate` (`src/lib/components/VaultUnlockGate.svelte`).
This component gates all content behind the in-memory DEK:

1. On mount, try `loadRememberedDEK(email)` from IndexedDB. If found, restore it to `sessionDEK`.
2. If no remembered key, prompt for the vault passphrase.
3. User enters vault passphrase → `unlockVault(passphrase, user.kekSalt, user.encryptedDEK, user.dekIV)`
   → DEK stored in `sessionDEK`.
4. If "remember this device" is checked, `saveRememberedDEK(email, dek)` persists the raw DEK to IndexedDB.

The `SafeUser` type (returned by `toSafeUser` in `src/lib/server/auth.ts`) carries `kekSalt`,
`encryptedDEK`, and `dekIV` alongside the user identity fields, so they are available everywhere
after authentication without additional API calls.

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

### Password change

Because all content is encrypted with the DEK (not the KEK), changing the password only
requires re-encrypting the DEK — a single row update. All diary entries are untouched.

```
1. Derive oldKEK from oldPassword + existing kekSalt
2. Decrypt DEK using oldKEK  (verify the password is correct before proceeding)
3. Generate new kekSalt
4. Derive newKEK from newPassword + newKekSalt
5. Re-encrypt the same DEK with newKEK  →  { newEncryptedDEK, newDekIV }
6. POST to server: { newPassword, newKekSalt, newEncryptedDEK, newDekIV }
7. Server updates password + kek_salt + encrypted_dek + dek_iv in a single transaction
8. sessionDEK is unchanged — the DEK itself never changed
```

### Constraints

- **Search** is not possible on encrypted fields. To filter entries, decrypt all records into memory client-side and use `Array.filter`.
- **Forgotten password** means the DEK cannot be decrypted and all content is permanently unrecoverable. There is no server-side reset path.
- The **kek_salt** is not secret and can be returned in plaintext from `load`. Its only role is to make the derived KEK unique and prevent precomputed dictionary attacks.