import { onScopeDispose, shallowRef, watch, type Ref } from 'vue';
import { apiProblem } from '../live/api';
import { liveFilesApi } from '../live/files-api';
import { ARCHIVE_WAIT_MS, contentHref, downloadBody, filesError, keepsRequestKey, pollDelay } from '../live/files-rules';
import type { FileDownload, OrderFile, OrderFiles } from '../live/files-types';
import { newRequestId } from '../live/rules';
import { phoneSaveMode, shareOutcome } from '../live/share-files';

/** A settled download that gave no link: the server failed or the link expired before it was used. */
class NoContent extends Error {
  constructor(readonly code: 'DOWNLOAD_FAILED' | 'DOWNLOAD_EXPIRED') {
    super(code);
  }
}

/**
 * Purchased originals of the buyer's order: the server decides the right and the list; a single file opens at once,
 * an archive is built in the background and polled. A request without an answer is repeated with the same key.
 * #192: on a phone a photo is also handed to the share sheet, which saves it into the phone's gallery.
 */
export function useLiveFiles(orderKey: Ref<string>) {
  const files = shallowRef<OrderFiles | null>(null);
  const loading = shallowRef(false);
  const busy = shallowRef('');
  const preparing = shallowRef(false);
  const error = shallowRef('');
  /** Photo whose file is ready but whose share sheet needs one more tap (the first tap expired while it loaded). */
  const sharePending = shallowRef('');
  const saveMode = phoneSaveMode(
    globalThis.navigator,
    typeof matchMedia === 'function' && matchMedia('(pointer: coarse)').matches,
    globalThis.navigator?.userAgent ?? ''
  );
  const canSave = saveMode === 'share';
  const keys = new Map<string, string>();
  const prepared = new Map<string, File>();
  let disposed = false;
  onScopeDispose(() => {
    disposed = true;
  });

  async function load(): Promise<void> {
    if (!orderKey.value) return;
    loading.value = true;
    error.value = '';
    try {
      files.value = await liveFilesApi.files(orderKey.value);
    } catch (cause) {
      error.value = filesError(apiProblem(cause));
    } finally {
      loading.value = false;
    }
  }

  /** One server download at a time; a request without an answer keeps its key and is repeated with it. */
  async function run(target: string, action: () => Promise<void>): Promise<void> {
    if (busy.value) return;
    busy.value = target;
    error.value = '';
    try {
      await action();
    } catch (cause) {
      const problem = cause instanceof NoContent ? { status: 409, code: cause.code, network: false } : apiProblem(cause);
      if (!keepsRequestKey(problem)) keys.delete(target);
      error.value = filesError(problem);
      if (problem.code === 'FILES_UNAVAILABLE' || problem.code === 'COMPOSITION_CHANGED') await load();
    } finally {
      busy.value = '';
      preparing.value = false;
    }
  }

  async function contentFor(photoId: string | undefined, target: string): Promise<string | null> {
    const key = keys.get(target) ?? newRequestId();
    keys.set(target, key);
    let current = await liveFilesApi.request(orderKey.value, downloadBody(photoId), key);
    keys.delete(target);
    current = await untilSettled(current);
    if (disposed) return null;
    const href = contentHref(current, import.meta.env.VITE_API_URL);
    if (!href) throw new NoContent(current.status === 'failed' ? 'DOWNLOAD_FAILED' : 'DOWNLOAD_EXPIRED');
    return href;
  }

  function download(photoId?: string): Promise<void> {
    const target = photoId ?? 'archive';
    return run(target, async () => {
      const href = await contentFor(photoId, target);
      if (href) window.location.assign(href);
    });
  }

  /** The prepared file goes to the sheet right in the tap that asked for it; loading it first may cost one more tap. */
  async function share(file: OrderFile, photo: File): Promise<void> {
    try {
      await navigator.share({ files: [photo] });
      prepared.delete(file.photoId);
      sharePending.value = '';
    } catch (cause) {
      const outcome = shareOutcome(cause);
      sharePending.value = 'retry' === outcome ? file.photoId : '';
      if ('failed' === outcome) error.value = 'Телефон не открыл меню сохранения. Скачайте фото кнопкой «Скачать».';
    }
  }

  function save(file: OrderFile): Promise<void> {
    const ready = prepared.get(file.photoId);
    if (ready) return share(file, ready);
    return run('save:' + file.photoId, async () => {
      const href = await contentFor(file.photoId, 'save:' + file.photoId);
      if (!href) return;
      const response = await fetch(href);
      if (!response.ok) throw new NoContent(410 === response.status || 404 === response.status ? 'DOWNLOAD_EXPIRED' : 'DOWNLOAD_FAILED');
      const photo = new File([await response.blob()], file.filename, { type: file.mimeType });
      prepared.set(file.photoId, photo);
      await share(file, photo);
    });
  }

  async function untilSettled(download: FileDownload): Promise<FileDownload> {
    const started = Date.now();
    let current = download;
    preparing.value = current.status === 'pending';
    while (current.status === 'pending' && !disposed) {
      const elapsed = Date.now() - started;
      if (elapsed > ARCHIVE_WAIT_MS) throw new Error('Archive wait timed out');
      await new Promise((resolve) => setTimeout(resolve, pollDelay(elapsed)));
      current = await liveFilesApi.download(orderKey.value, current.id);
    }
    return current;
  }

  watch(orderKey, load, { immediate: true });
  return { files, loading, busy, preparing, error, load, download, canSave, saveMode, save, sharePending };
}
