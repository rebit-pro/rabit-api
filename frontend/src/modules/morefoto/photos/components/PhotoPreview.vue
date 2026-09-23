<script setup lang="ts">
import { computed, shallowRef, watch } from 'vue';
import GalleryImage from '../../gallery/components/GalleryImage.vue';
import type { ManagedPhoto } from '../types';
let returnFocus: HTMLElement | null = null;
function restoreFocus() {
  if (returnFocus?.isConnected) returnFocus.focus();
}
// The dialog gets the frames of one child at a time: the workspace keeps only one page of the group.
const props = defineProps<{
  open: boolean;
  codes: string[];
  child: string;
  photos: ManagedPhoto[];
  loading: boolean;
  error: string;
  groupName: string;
}>();
defineEmits<{ close: []; 'update:child': [code: string] }>();
const index = shallowRef(0);
function sequence(photo: ManagedPhoto): number {
  return photo.assignments.find((assignment) => assignment.childCode === props.child)?.sequence ?? 0;
}
const bundle = computed(() =>
  props.photos
    .filter((photo) => photo.assignments.some((assignment) => assignment.childCode === props.child))
    .sort((a, b) => sequence(a) - sequence(b))
);
const photo = computed(() => bundle.value[Math.min(index.value, bundle.value.length - 1)]);
const code = computed(() => photo.value?.assignments.find((assignment) => assignment.childCode === props.child)?.code ?? '');
watch(
  () => props.open,
  (value) => {
    if (value) {
      returnFocus = document.activeElement instanceof HTMLElement ? document.activeElement : null;
      index.value = 0;
    }
  }
);
watch(
  () => props.child,
  () => {
    index.value = 0;
  }
);
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
        :model-value="child"
        :items="codes.map((value) => ({ title: 'Ребёнок ' + value, value }))"
        label="Ребёнок"
        class="mt-5"
        data-testid="preview-child"
        @update:model-value="$emit('update:child', $event)"
      />
      <div v-if="loading" class="preview-state py-6" role="status">
        <v-progress-circular indeterminate size="28" width="3" color="primary" />
        <span>Загружаем кадры набора…</span>
      </div>
      <v-alert v-else-if="error" type="error" variant="tonal" role="alert" class="mt-4">{{ error }}</v-alert>
      <template v-else-if="photo">
        <GalleryImage
          :key="photo.id"
          :src="photo.previewSrc"
          :alt="'Кадр ' + code"
          :width="photo.width"
          :height="photo.height"
          eager
          class="preview-image mt-4"
        />
        <div class="preview-navigation mt-4">
          <v-btn variant="outlined" :disabled="index === 0" aria-label="Предыдущий кадр" @click="index--">←</v-btn>
          <p role="status" data-testid="preview-position">{{ code }} · {{ index + 1 }} из {{ bundle.length }}</p>
          <v-btn variant="outlined" :disabled="index >= bundle.length - 1" aria-label="Следующий кадр" @click="index++">→</v-btn>
        </div>
      </template>
      <p v-else class="py-6">Назначьте кадры ребёнку, чтобы просмотреть набор.</p>
    </v-card>
  </v-dialog>
</template>
<style scoped>
.preview-state {
  display: flex;
  align-items: center;
  gap: 12px;
}
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
