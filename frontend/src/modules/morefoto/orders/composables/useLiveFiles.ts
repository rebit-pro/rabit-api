import { onScopeDispose, shallowRef, watch, type Ref } from 'vue';
import { apiProblem } from '../live/api';
import { liveFilesApi } from '../live/files-api';
import { ARCHIVE_WAIT_MS, contentHref, downloadBody, filesError, keepsRequestKey, pollDelay } from '../live/files-rules';
import type { FileDownload, OrderFiles } from '../live/files-types';
import { newRequestId } from '../live/rules';

/**
 * Purchased originals of the buyer's order: the server decides the right and the list; a single file opens at once,
 * an archive is built in the background and polled. A request without an answer is repeated with the same key.
 */
export function useLiveFiles(orderKey: Ref<string>) {
  const files = shallowRef<OrderFiles | null>(null);
  const loading = shallowRef(false);
  const busy = shallowRef('');
  const preparing = shallowRef(false);
  const error = shallowRef('');
  const keys = new Map<string, string>();
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

  async function download(photoId?: string): Promise<void> {
    if (busy.value) return;
    const target = photoId ?? 'archive';
    const key = keys.get(target) ?? newRequestId();
    keys.set(target, key);
    busy.value = target;
    error.value = '';
    try {
      let current = await liveFilesApi.request(orderKey.value, downloadBody(photoId), key);
      keys.delete(target);
      current = await untilSettled(current);
      if (disposed) return;
      const href = contentHref(current, import.meta.env.VITE_API_URL);
      if (href) {
        window.location.assign(href);
      } else {
        error.value = filesError({
          status: 409,
          code: current.status === 'failed' ? 'DOWNLOAD_FAILED' : 'DOWNLOAD_EXPIRED',
          network: false
        });
      }
    } catch (cause) {
      const problem = apiProblem(cause);
      if (!keepsRequestKey(problem)) keys.delete(target);
      error.value = filesError(problem);
      if (problem.code === 'FILES_UNAVAILABLE' || problem.code === 'COMPOSITION_CHANGED') await load();
    } finally {
      busy.value = '';
      preparing.value = false;
    }
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
  return { files, loading, busy, preparing, error, load, download };
}
