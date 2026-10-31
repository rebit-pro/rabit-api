<script setup lang="ts">
import { watch } from 'vue';
import GalleryImage from './GalleryImage.vue';
import PhotoZoomStage from './PhotoZoomStage.vue';

const props = defineProps<{ open: boolean; src: string; label: string; error?: boolean }>();
defineEmits<{ close: [] }>();
let opener: HTMLElement | null = null;
watch(
  () => props.open,
  (open) => {
    if (open && typeof document !== 'undefined') opener = document.activeElement instanceof HTMLElement ? document.activeElement : null;
  },
  { immediate: true }
);
function restoreFocus(): void {
  if (props.open) return;
  if (opener?.isConnected) opener.focus({ preventScroll: true });
  opener = null;
}
</script>

<template>
  <v-dialog
    :model-value="open"
    max-width="1000"
    aria-labelledby="photo-zoom-title"
    @update:model-value="!$event && $emit('close')"
    @after-leave="restoreFocus"
  >
    <v-card class="morefoto-app photo-zoom-dialog">
      <header class="photo-zoom-dialog__header">
        <h2 id="photo-zoom-title">{{ label }}</h2>
        <v-btn icon="mdi-close" variant="text" aria-label="Закрыть фото" @click="$emit('close')" />
      </header>
      <PhotoZoomStage v-if="open" :key="src" class="photo-zoom-dialog__image">
        <GalleryImage v-if="src" :src="src" :alt="label" eager />
        <v-alert v-else-if="error" type="error" role="alert">Не удалось загрузить кадр.</v-alert>
        <div v-else class="photo-zoom-dialog__loading" role="status"><v-progress-circular indeterminate size="32" />Загружаем кадр…</div>
      </PhotoZoomStage>
    </v-card>
  </v-dialog>
</template>

<style scoped>
.photo-zoom-dialog {
  overflow: hidden;
}
.photo-zoom-dialog__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--mf-space-3);
  padding: var(--mf-space-3) var(--mf-space-4);
}
.photo-zoom-dialog__header h2 {
  font-size: var(--mf-text-lg);
}
.photo-zoom-dialog__image {
  height: min(75svh, 760px);
}
.photo-zoom-dialog__loading {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: var(--mf-space-3);
  height: 100%;
}
</style>
