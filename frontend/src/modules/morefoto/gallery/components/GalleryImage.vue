<script setup lang="ts">
import { computed, shallowRef, watch } from 'vue';
import PhotoImage from '../../photos/components/PhotoImage.vue';
const props = withDefaults(defineProps<{ src: string; alt: string; eager?: boolean }>(), { eager: false });
const loaded = shallowRef(false);
const failed = shallowRef(false);
const attempt = shallowRef(0);
const source = computed(() => props.src + (attempt.value ? '?retry=' + attempt.value : ''));
watch(
  () => props.src,
  () => {
    loaded.value = false;
    failed.value = false;
    attempt.value = 0;
  }
);
function retry() {
  loaded.value = false;
  failed.value = false;
  attempt.value++;
}
</script>

<template>
  <div class="gallery-image" :aria-busy="!loaded && !failed">
    <PhotoImage
      v-if="!failed"
      :key="source"
      :src="source"
      :alt="alt"
      :loading="eager ? 'eager' : 'lazy'"
      decoding="async"
      draggable="false"
      :class="{ 'gallery-image--loading': !loaded }"
      @load="loaded = true"
      @error="failed = true"
    />
    <div v-if="failed" class="gallery-image__error" role="status">
      <v-icon icon="mdi-image-off-outline" size="30" color="secondary" />
      <p>Кадр не загрузился</p>
      <v-btn variant="outlined" color="primary" size="small" :aria-label="'Повторить загрузку: ' + alt" @click.stop="retry"
        >Повторить</v-btn
      >
    </div>
    <div v-else-if="!loaded" class="gallery-image__loading" aria-label="Загрузка фотографии">
      <v-progress-circular indeterminate size="24" width="2" color="primary" />
    </div>
  </div>
</template>

<style scoped>
.gallery-image {
  position: relative;
  width: 100%;
  height: 100%;
  background: #e9eef2;
  min-height: 100px;
}
.gallery-image img {
  display: block;
  width: 100%;
  height: 100%;
  object-fit: contain;
  transition: opacity 0.15s;
}
.gallery-image--loading {
  opacity: 0;
}
.gallery-image__error,
.gallery-image__loading {
  position: absolute;
  inset: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  color: #5e6872;
  text-align: center;
  font-size: 13px;
  padding: 12px;
}
@media (prefers-reduced-motion: reduce) {
  .gallery-image img {
    transition: none;
  }
}
</style>
