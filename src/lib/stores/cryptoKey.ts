import { decryptDEK, deriveKEK } from '$lib/crypto';
import { writable } from 'svelte/store';

export const sessionDEK = writable<CryptoKey | null>(null);

export async function unlockVault(password: string, kekSalt: string, encryptedDEK: string, dekIV: string) {
  const kek = await deriveKEK(password, kekSalt);
  const dek = await decryptDEK(kek, encryptedDEK, dekIV);
  sessionDEK.set(dek);
}

export function lockVault() {
  sessionDEK.set(null);
}
