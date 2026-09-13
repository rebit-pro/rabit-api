const databaseName = 'morefoto-demo-photos-v1';
const storeName = 'previews';
export function localPhotoSource(id: string, variant: 'thumb' | 'preview'): string {
  return 'morefoto-local:' + id + ':' + variant;
}
export function localPhotoKey(source: string): string | null {
  const match = /^morefoto-local:([a-f\d-]{36}):(thumb|preview)(?:\?retry=\d+)?$/.exec(source);
  return match ? match[1] + ':' + match[2] : null;
}
function openDatabase(): Promise<IDBDatabase> {
  return new Promise((resolve, reject) => {
    const request = indexedDB.open(databaseName, 1);
    request.onupgradeneeded = () => {
      request.result.createObjectStore(storeName);
    };
    request.onsuccess = () => resolve(request.result);
    request.onerror = () => reject(new Error('Хранилище браузера недоступно. Разрешите локальное хранение и повторите.'));
  });
}
export async function writePhotoBlobs(id: string, thumb: Blob, preview: Blob): Promise<void> {
  const db = await openDatabase();
  try {
    await new Promise<void>((resolve, reject) => {
      const tx = db.transaction(storeName, 'readwrite');
      tx.objectStore(storeName).put(thumb, id + ':thumb');
      tx.objectStore(storeName).put(preview, id + ':preview');
      tx.oncomplete = () => resolve();
      tx.onerror = tx.onabort = () => reject(new Error('Недостаточно места в браузере. Освободите место и повторите файл.'));
    });
  } finally {
    db.close();
  }
}
export async function readPhotoBlob(source: string): Promise<Blob> {
  const key = localPhotoKey(source);
  if (!key) throw new Error('Некорректная ссылка на локальное превью.');
  const db = await openDatabase();
  try {
    return await new Promise<Blob>((resolve, reject) => {
      const tx = db.transaction(storeName, 'readonly');
      const request = tx.objectStore(storeName).get(key);
      request.onsuccess = () =>
        request.result instanceof Blob
          ? resolve(request.result)
          : reject(new Error('Превью отсутствует в этом браузере. Повторите подготовку исходного файла.'));
      request.onerror = () => reject(new Error('Не удалось прочитать превью.'));
    });
  } finally {
    db.close();
  }
}
export async function hasPhotoBlobs(): Promise<boolean> {
  const db = await openDatabase();
  try {
    return await new Promise<boolean>((resolve, reject) => {
      const request = db.transaction(storeName, 'readonly').objectStore(storeName).count();
      request.onsuccess = () => resolve(request.result > 0);
      request.onerror = () => reject(new Error('Не удалось проверить локальные фотографии.'));
    });
  } finally {
    db.close();
  }
}
export async function clearPhotoBlobs(): Promise<void> {
  const db = await openDatabase();
  try {
    await new Promise<void>((resolve, reject) => {
      const tx = db.transaction(storeName, 'readwrite');
      tx.objectStore(storeName).clear();
      tx.oncomplete = () => resolve();
      tx.onerror = () => reject(tx.error);
    });
  } finally {
    db.close();
  }
}
