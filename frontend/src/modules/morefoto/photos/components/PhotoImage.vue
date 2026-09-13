<script setup lang="ts">
import { shallowRef, watch } from 'vue';
import { localPhotoKey, readPhotoBlob } from '../blobs';
const props = defineProps<{ src: string; alt: string }>();
const emit = defineEmits<{ load: [event: Event]; error: [event: Event] }>();
const source = shallowRef<string>();
watch(
  () => props.src,
  async (value, _old, onCleanup) => {
    let alive = true;
    let objectUrl = '';
    onCleanup(() => {
      alive = false;
      if (objectUrl) URL.revokeObjectURL(objectUrl);
    });
    source.value = undefined;
    if (!value.startsWith('morefoto-local:')) {
      source.value = value;
      return;
    }
    try {
      if (!localPhotoKey(value)) throw new Error('Invalid local preview');
      const blob = await readPhotoBlob(value);
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
<template><img :src="source" :alt="alt" :aria-busy="!source" @load="emit('load', $event)" @error="emit('error', $event)" /></template>
