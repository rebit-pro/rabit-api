<script setup lang="ts">
import type { GalleryPhoto } from '../types';
import GalleryImage from './GalleryImage.vue';
defineProps<{ photos: GalleryPhoto[] }>();
defineEmits<{ open: [photo: GalleryPhoto] }>();
</script>

<template>
  <div class="gallery-grid">
    <article v-for="(photo, index) in photos" :key="photo.assignmentId ?? photo.id" class="photo-card" data-testid="photo-card">
      <div class="photo-card__image" @click="$emit('open', photo)">
        <GalleryImage :src="photo.thumbSrc" :alt="'Кадр ' + photo.code" :eager="index < 4" />
      </div>
      <button type="button" class="photo-card__open" :aria-label="'Открыть кадр ' + photo.code" @click="$emit('open', photo)">
        <span
          ><span class="photo-card__label">КАДР</span><strong>{{ photo.code }}</strong></span
        >
        <v-icon icon="mdi-arrow-top-right" size="22" />
      </button>
    </article>
  </div>
</template>

<style scoped>
.gallery-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 28px 20px;
}
.photo-card {
  min-width: 0;
  background: var(--mf-color-surface);
  border: 1px solid var(--mf-color-border);
  border-radius: 10px;
  overflow: hidden;
  transition: border-color 0.15s;
}
.photo-card:hover {
  border-color: var(--mf-color-primary);
}
.photo-card__image {
  aspect-ratio: 2/3;
  cursor: pointer;
}
.photo-card__open {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 15px 16px;
  text-align: left;
  color: var(--mf-color-link);
  min-height: 64px;
}
.photo-card__label {
  display: block;
  color: var(--mf-color-text-secondary);
  font-size: 10px;
  letter-spacing: 0.12em;
  margin-bottom: 4px;
}
.photo-card__open strong {
  font-size: 14px;
  font-weight: 600;
  color: var(--mf-color-text);
}
.photo-card:focus-within {
  outline: 2px solid var(--mf-color-focus);
  outline-offset: 3px;
}
@media (max-width: 1000px) {
  .gallery-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}
@media (max-width: 767px) {
  .gallery-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px 12px;
  }
  .photo-card__open {
    padding: 12px;
  }
}
@media (prefers-reduced-motion: reduce) {
  .photo-card {
    transition: none;
  }
}
</style>
