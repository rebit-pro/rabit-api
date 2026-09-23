<script setup lang="ts">
import { onBeforeUnmount, onMounted, shallowRef, useTemplateRef, watch } from 'vue';
import { localPhotoKey, readPhotoBlob } from '../blobs';
import { isManagedPreview, isPreviewAbort, loadManagedPreview } from '../previews';
const props = defineProps<{ src: string; alt: string; loading?: 'eager' | 'lazy' }>();
const emit = defineEmits<{ load: [event: Event]; error: [event: Event] }>();
const image = useTemplateRef<HTMLImageElement>('image');
const source = shallowRef<string>();
// A protected frame is an XHR, so the browser's lazy loading cannot hold it back: the request waits until the
// frame is close to the viewport. Eager frames (the open preview) skip the wait and go first in the queue.
const near = shallowRef(props.loading === 'eager' || typeof IntersectionObserver === 'undefined');
let observer: IntersectionObserver | undefined;
let shown = '';
let localUrl = '';
let pending: AbortController | undefined;
function release() {
  pending?.abort();
  pending = undefined;
  if (localUrl) URL.revokeObjectURL(localUrl);
  localUrl = '';
  shown = '';
  source.value = undefined;
}
async function request(value: string) {
  if (shown === value || pending) return;
  const managed = isManagedPreview(value);
  if (!managed && !value.startsWith('morefoto-local:')) {
    shown = value;
    source.value = value;
    return;
  }
  if (managed && !near.value) return;
  const controller = new AbortController();
  pending = controller;
  try {
    let url: string;
    if (managed) {
      url = await loadManagedPreview(value, controller.signal, props.loading === 'eager');
    } else {
      if (!localPhotoKey(value)) throw new Error('Invalid local preview');
      const blob = await readPhotoBlob(value);
      if (controller.signal.aborted) return;
      url = localUrl = URL.createObjectURL(blob);
    }
    if (controller.signal.aborted) return;
    shown = value;
    source.value = url;
  } catch (cause) {
    if (!controller.signal.aborted && !isPreviewAbort(cause)) emit('error', new Event('error'));
  } finally {
    if (pending === controller) pending = undefined;
  }
}
watch(
  () => props.src,
  (value) => {
    release();
    void request(value);
  },
  { immediate: true }
);
watch(near, (visible) => {
  if (visible) void request(props.src);
  else if (!shown) release();
});
onMounted(() => {
  if (near.value || !image.value) return;
  observer = new IntersectionObserver(
    (entries) => {
      near.value = entries.some((entry) => entry.isIntersecting);
    },
    { rootMargin: '300px 0px' }
  );
  observer.observe(image.value);
});
onBeforeUnmount(() => {
  observer?.disconnect();
  release();
});
</script>
<template>
  <img
    ref="image"
    :src="source"
    :alt="alt"
    :loading="loading"
    :aria-busy="!source"
    @load="emit('load', $event)"
    @error="emit('error', $event)"
  />
</template>
