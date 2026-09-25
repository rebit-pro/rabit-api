import { shallowRef, toValue, watch, type MaybeRefOrGetter, type ShallowRef } from 'vue';
import api from '@/api/http';

/** Loads an image that the browser cannot fetch by itself, or returns null when the address can be used as is. */
export type ProtectedImageLoader = (source: string, signal: AbortSignal) => Promise<Blob> | null;

/** Cabinet API images need the staff token, which an <img> request cannot carry. */
export const loadApiImage: ProtectedImageLoader = (source, signal) =>
  source.startsWith('/api/v1/')
    ? api.get<Blob>(source, { responseType: 'blob', signal, headers: { Accept: 'image/webp' } }).then((response) => response.data)
    : null;

/**
 * Turns a protected image address into an object URL for <img>. The URL is revoked when the address changes or the
 * component goes away; `failed` lets the caller fall back (initials, placeholder).
 */
export function useProtectedImage(
  src: MaybeRefOrGetter<string | null | undefined>,
  load: ProtectedImageLoader = loadApiImage
): { source: Readonly<ShallowRef<string | undefined>>; failed: Readonly<ShallowRef<boolean>> } {
  const source = shallowRef<string>();
  const failed = shallowRef(false);
  watch(
    () => toValue(src),
    async (value, _old, onCleanup) => {
      let alive = true;
      let objectUrl = '';
      const controller = new AbortController();
      onCleanup(() => {
        alive = false;
        controller.abort();
        if (objectUrl) URL.revokeObjectURL(objectUrl);
      });
      source.value = undefined;
      failed.value = false;
      if (!value) return;
      try {
        const pending = load(value, controller.signal);
        if (null === pending) {
          source.value = value;
          return;
        }
        const blob = await pending;
        if (!alive) return;
        objectUrl = URL.createObjectURL(blob);
        source.value = objectUrl;
      } catch {
        if (alive) failed.value = true;
      }
    },
    { immediate: true }
  );
  return { source, failed };
}
