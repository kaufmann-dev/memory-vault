const DB_NAME = 'memory-vault-device-keys';
const DB_VERSION = 1;
const STORE_NAME = 'remembered-deks';

type RememberedDEK = {
  email: string;
  dek: CryptoKey;
  savedAt: string;
};

function isIndexedDBAvailable() {
  return typeof indexedDB !== 'undefined';
}

function requestResult<T>(request: IDBRequest<T>) {
  return new Promise<T>((resolve, reject) => {
    request.onsuccess = () => resolve(request.result);
    request.onerror = () => reject(request.error ?? new Error('IndexedDB request failed'));
  });
}

function openDatabase() {
  return new Promise<IDBDatabase>((resolve, reject) => {
    if (!isIndexedDBAvailable()) {
      reject(new Error('Remembered device storage is not available'));
      return;
    }

    const request = indexedDB.open(DB_NAME, DB_VERSION);

    request.onupgradeneeded = () => {
      request.result.createObjectStore(STORE_NAME, { keyPath: 'email' });
    };
    request.onsuccess = () => resolve(request.result);
    request.onerror = () => reject(request.error ?? new Error('Could not open remembered device storage'));
  });
}

async function withStore<T>(mode: IDBTransactionMode, action: (store: IDBObjectStore) => IDBRequest<T>) {
  const db = await openDatabase();

  try {
    const transaction = db.transaction(STORE_NAME, mode);
    return await requestResult(action(transaction.objectStore(STORE_NAME)));
  } finally {
    db.close();
  }
}

export async function saveRememberedDEK(email: string, dek: CryptoKey) {
  await withStore('readwrite', (store) =>
    store.put({
      email,
      dek,
      savedAt: new Date().toISOString()
    } satisfies RememberedDEK)
  );
}

export async function loadRememberedDEK(email: string) {
  const remembered = await withStore<RememberedDEK | undefined>('readonly', (store) => store.get(email));
  return remembered?.dek ?? null;
}

export async function hasRememberedDEK(email: string) {
  return (await loadRememberedDEK(email)) !== null;
}

export async function forgetRememberedDEK(email: string) {
  await withStore('readwrite', (store) => store.delete(email));
}
