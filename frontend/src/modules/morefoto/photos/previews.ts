import { shallowRef } from 'vue';
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
let owner: string | null = null;

export { isPreviewAbort } from './preview-loader';
export function isManagedPreview(source: string): boolean {
  return managedPreview.test(source);
}
export function managedPreviewSource(photoId: string, variant: 'thumb' | 'preview'): string {
  return '/api/v1/photos/' + photoId + '/' + variant;
}
/** Resolves an object URL owned by the shared cache: callers must not revoke it. */
export function loadManagedPreview(source: string, signal: AbortSignal, urgent: boolean): Promise<string> {
  // Cached frames belong to the session that downloaded them.
  const token = useAuthStore().getAccessToken();
  if (token !== owner) {
    loader.clear();
    owner = token;
  }
  return loader.load(source, signal, urgent);
}

/** Frames on screen that show «Кадр не загрузился». */
export const failedPreviews = shallowRef(0);
export const previewRetryRound = shallowRef(0);
export function retryFailedPreviews(): void {
  previewRetryRound.value++;
}
