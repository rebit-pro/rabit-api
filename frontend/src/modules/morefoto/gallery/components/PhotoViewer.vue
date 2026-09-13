<script setup lang="ts">
import { watch } from 'vue';
import type { GalleryPhoto, GallerySnapshot } from '../types';
import ProductSelector from '../../commerce/components/ProductSelector.vue';
import GalleryImage from './GalleryImage.vue';
const props = defineProps<{
  photo: GalleryPhoto | null;
  index: number;
  total: number;
  context: string;
  gallery: GallerySnapshot;
  token: string;
}>();
const emit = defineEmits<{ close: []; step: [direction: number] }>();
watch(
  () => !!props.photo,
  (open, _, onCleanup) => {
    if (!open) return;
    const navigate = (event: KeyboardEvent) => {
      const target = event.target as HTMLElement | null;
      if (target?.closest('input, textarea, select, [role="combobox"], [contenteditable="true"]')) return;
      if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') return;
      event.preventDefault();
      emit('step', event.key === 'ArrowLeft' ? -1 : 1);
    };
    window.addEventListener('keydown', navigate);
    onCleanup(() => window.removeEventListener('keydown', navigate));
  },
  { immediate: true }
);
</script>

<template>
  <v-dialog :model-value="!!photo" max-width="1040" aria-labelledby="gallery-photo-title" @update:model-value="!$event && $emit('close')">
    <v-card v-if="photo" class="photo-viewer morefoto-app">
      <header class="photo-viewer__header">
        <div>
          <h2 id="gallery-photo-title">Кадр {{ photo.code }}</h2>
          <p class="mf-muted">{{ context }}</p>
        </div>
        <v-btn icon="mdi-close" variant="text" aria-label="Закрыть просмотр" @click="$emit('close')" />
      </header>
      <div class="photo-viewer__body" :class="{ 'photo-viewer__body--shopping': gallery.state === 'open' }">
        <div class="photo-viewer__media">
          <div class="photo-viewer__stage">
            <GalleryImage :key="photo.id" :src="photo.previewSrc" :alt="'Крупный кадр ' + photo.code" eager />
            <v-btn
              class="photo-viewer__nav photo-viewer__nav--previous"
              icon="mdi-chevron-left"
              variant="outlined"
              color="primary"
              aria-label="Предыдущий кадр"
              :disabled="index <= 0"
              @click="$emit('step', -1)"
            />
            <v-btn
              class="photo-viewer__nav photo-viewer__nav--next"
              icon="mdi-chevron-right"
              variant="outlined"
              color="primary"
              aria-label="Следующий кадр"
              :disabled="index >= total - 1"
              @click="$emit('step', 1)"
            />
          </div>
          <footer class="photo-viewer__footer">
            <div class="photo-viewer__counter" aria-live="polite">
              <strong>{{ index + 1 }} / {{ total }}</strong>
              <span>Превью с водяным знаком</span>
            </div>
          </footer>
        </div>
        <ProductSelector v-if="gallery.state === 'open'" :gallery="gallery" :photo="photo" :token="token" />
      </div>
    </v-card>
  </v-dialog>
</template>

<style scoped>
.photo-viewer {
  border-radius: 14px !important;
  overflow: hidden;
}
.photo-viewer__header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  padding: 16px 24px;
}
.photo-viewer__header h2 {
  font-size: 20px;
  line-height: 1.3;
}
.photo-viewer__header p {
  font-size: 12px;
  margin-top: 4px;
}
.photo-viewer__body {
  min-height: 0;
  overflow: auto;
}
.photo-viewer__body--shopping {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 340px;
}
.photo-viewer__media {
  min-width: 0;
}
.photo-viewer__stage {
  position: relative;
  height: min(64svh, 760px);
  min-height: 180px;
  background: #e9eef2;
}
.photo-viewer .photo-viewer__nav {
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  z-index: 1;
  background: rgb(var(--v-theme-surface));
}
.photo-viewer__nav--previous {
  left: var(--mf-space-3);
}
.photo-viewer__nav--next {
  right: var(--mf-space-3);
}
.photo-viewer__footer {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: var(--mf-space-4);
  padding: var(--mf-space-3) var(--mf-space-6);
}
.photo-viewer__counter {
  display: grid;
  gap: 4px;
  text-align: center;
  font-size: 15px;
}
.photo-viewer__counter span {
  color: #5e6872;
  font-size: 12px;
}
@media (max-width: 767px) {
  .photo-viewer__body--shopping {
    grid-template-columns: minmax(0, 1fr);
  }
  .photo-viewer__header,
  .photo-viewer__footer {
    flex-shrink: 0;
  }
  .photo-viewer__header {
    padding: 12px 16px;
  }
  .photo-viewer__header h2 {
    font-size: 18px;
  }
  .photo-viewer__footer {
    padding: 12px 16px;
    gap: 20px;
  }
  .photo-viewer__stage {
    height: 58svh;
  }
}
</style>
