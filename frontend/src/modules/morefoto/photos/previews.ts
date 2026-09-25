import { shallowRef, watch } from 'vue';
import { isAxiosError, isCancel } from 'axios';
import api from '@/api/http';
import { useAuthStore } from '@/stores/auth';
import { createPreviewLoader } from './preview-loader';

const managedPreview = /^\/api\/v1\/photos\/[a-f0-9-]{36}\/(thumb|preview)$/;
// Six parallel frames keep a page responsive without flooding the PHP workers; 30 s per attempt replaces the
// shared 15 s API limit, which also counted the time a request spent waiting behind the others.
const loader = createPreviewLoader({
  concurrency: 6,
  retryDelayMs: 1000,
  maxBytes: 64 * 1024 * 1024,
  fetch: async (url, signal) =>
    (await api.get<Blob>(url, { responseType: 'blob', signal, timeout: 30_000, headers: { Accept: 'image/webp' } })).data,
  retryable: (error) =>
    isAxiosError(error) &&
    !isCancel(error) &&
    (!error.response || error.response.status >= 500 || error.response.status === 408 || error.response.status === 429),
  createUrl: (blob) => URL.createObjectURL(blob),
  revokeUrl: (url) => URL.revokeObjectURL(url)
});
let followsSession = false;

export { isPreviewAbort } from './preview-loader';
export function isManagedPreview(source: string): boolean {
  return managedPreview.test(source);
}
export function managedPreviewSource(photoId: string, variant: 'thumb' | 'preview'): string {
  return '/api/v1/photos/' + photoId + '/' + variant;
}
/** Resolves an object URL owned by the shared cache: callers must not revoke it. */
export function loadManagedPreview(source: string, signal: AbortSignal, urgent: boolean): Promise<string> {
  if (!followsSession) {
    followsSession = true;
    const auth = useAuthStore();
    // A started request outlives its frame to fill the cache, so a new or ended session cancels it at once: its late
    // 401 would clear the session that replaced it. Cached frames of the old session are dropped with it.
    watch(
      () => auth.token,
      () => loader.clear(),
      { flush: 'sync' }
    );
  }
  return loader.load(source, signal, urgent);
}

/** Frames on screen that show «Кадр не загрузился». */
export const failedPreviews = shallowRef(0);
export const previewRetryRound = shallowRef(0);
export function retryFailedPreviews(): void {
  previewRetryRound.value++;
}
