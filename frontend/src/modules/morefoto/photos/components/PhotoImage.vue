<script setup lang="ts">
import { shallowRef, watch } from 'vue';
import api from '@/api/http';
import { localPhotoKey, readPhotoBlob } from '../blobs';
const props = defineProps<{ src: string; alt: string }>();
const emit = defineEmits<{ load: [event: Event]; error: [event: Event] }>();
const source = shallowRef<string>();
watch(
  () => props.src,
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
    const managedPreview = /^\/api\/v1\/photos\/[a-f0-9-]{36}\/(thumb|preview)$/.test(value);
    if (!managedPreview && !value.startsWith('morefoto-local:')) {
      source.value = value;
      return;
    }
    try {
      if (!managedPreview && !localPhotoKey(value)) throw new Error('Invalid local preview');
      const blob = managedPreview
        ? (
            await api.get<Blob>(value, {
              responseType: 'blob',
              signal: controller.signal,
              headers: { Accept: 'image/webp' }
            })
          ).data
        : await readPhotoBlob(value);
      if (!alive) return;
      objectUrl = URL.createObjectURL(blob);
      source.value = objectUrl;
    } catch {
      if (alive) emit('error', new Event('error'));
    }
  },
  { immediate: true }
);
</script>
<template>
  <img :src="source" :alt="alt" :aria-busy="!source" @load="emit('load', $event)" @error="emit('error', $event)" />
</template>
