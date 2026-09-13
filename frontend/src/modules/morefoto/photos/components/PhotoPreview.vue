<script setup lang="ts">
import { computed, shallowRef, watch } from 'vue';
import GalleryImage from '../../gallery/components/GalleryImage.vue';
import type { ManagedPhoto } from '../types';
let returnFocus: HTMLElement | null = null;
function restoreFocus() {
  if (returnFocus?.isConnected) returnFocus.focus();
}
const props = defineProps<{ open: boolean; photos: ManagedPhoto[]; initialChild: string; groupName: string }>();
defineEmits<{ close: [] }>();
const child = shallowRef('');
const index = shallowRef(0);
const codes = computed(() => [...new Set(props.photos.map((photo) => photo.childCode).filter((code): code is string => !!code))].sort());
const bundle = computed(() =>
  props.photos.filter((photo) => photo.childCode === child.value).sort((a, b) => (a.sequence ?? 0) - (b.sequence ?? 0))
);
const photo = computed(() => bundle.value[Math.min(index.value, bundle.value.length - 1)]);
watch(
  () => props.open,
  (value) => {
    if (value) {
      returnFocus = document.activeElement instanceof HTMLElement ? document.activeElement : null;
      child.value = props.initialChild || codes.value[0] || '';
      index.value = 0;
    }
  }
);
watch(child, () => {
  index.value = 0;
});
</script>
<template>
  <v-dialog
    :model-value="open"
    @after-leave="restoreFocus"
    max-width="940"
    aria-labelledby="preview-heading"
    @update:model-value="!$event && $emit('close')"
  >
    <v-card class="morefoto-app pa-5 preview-card">
      <div class="preview-heading">
        <div>
          <h2 id="preview-heading">Предпросмотр набора</h2>
          <p class="mf-muted">{{ groupName }} · Превью с водяным знаком</p>
        </div>
        <v-btn variant="outlined" @click="$emit('close')">Закрыть просмотр</v-btn>
      </div>
      <v-select
        v-model="child"
        :items="codes.map((value) => ({ title: 'Ребёнок ' + value, value }))"
        label="Ребёнок"
        class="mt-5"
        data-testid="preview-child"
      />
      <template v-if="photo">
        <GalleryImage
          :key="photo.id"
          :src="photo.previewSrc"
          :alt="'Кадр ' + photo.code"
          :width="photo.width"
          :height="photo.height"
          class="preview-image mt-4"
        />
        <div class="preview-navigation mt-4">
          <v-btn variant="outlined" :disabled="index === 0" aria-label="Предыдущий кадр" @click="index--">←</v-btn>
          <p role="status" data-testid="preview-position">{{ photo.code }} · {{ index + 1 }} из {{ bundle.length }}</p>
          <v-btn variant="outlined" :disabled="index >= bundle.length - 1" aria-label="Следующий кадр" @click="index++">→</v-btn>
        </div>
      </template>
      <p v-else class="py-6">Назначьте кадры ребёнку, чтобы просмотреть набор.</p>
    </v-card>
  </v-dialog>
</template>
<style scoped>
.preview-heading > div {
  min-width: 0;
}
.preview-heading h2 {
  font-size: 1.3125rem;
  line-height: 1.35;
  overflow-wrap: anywhere;
}
.preview-card {
  overflow-y: auto;
}
.preview-heading,
.preview-navigation {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
}
.preview-image {
  height: min(54vh, 600px);
}
.preview-image :deep(img) {
  object-fit: contain;
}
.preview-navigation .v-btn {
  min-width: 44px;
  min-height: 44px;
}
.preview-navigation p {
  overflow-wrap: anywhere;
}
</style>
