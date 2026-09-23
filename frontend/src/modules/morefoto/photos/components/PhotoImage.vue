<script setup lang="ts">
import { watch } from 'vue';
import { loadApiImage, useProtectedImage, type ProtectedImageLoader } from '@/composables/useProtectedImage';
import { localPhotoKey, readPhotoBlob } from '../blobs';
const props = defineProps<{ src: string; alt: string }>();
const emit = defineEmits<{ load: [event: Event]; error: [event: Event] }>();
const managedPreview = /^\/api\/v1\/photos\/[a-f0-9-]{36}\/(thumb|preview)$/;
// Managed previews need the staff token, demo photos live in IndexedDB; any other address is a plain URL.
const loadPhoto: ProtectedImageLoader = (value, signal) => {
  if (managedPreview.test(value)) return loadApiImage(value, signal);
  if (!value.startsWith('morefoto-local:')) return null;
  return localPhotoKey(value) ? readPhotoBlob(value) : Promise.reject(new Error('Invalid local preview'));
};
const { source, failed } = useProtectedImage(() => props.src, loadPhoto);
watch(failed, (value) => {
  if (value) emit('error', new Event('error'));
});
</script>
<template>
  <img :src="source" :alt="alt" :aria-busy="!source" @load="emit('load', $event)" @error="emit('error', $event)" />
</template>
