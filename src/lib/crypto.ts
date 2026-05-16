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
