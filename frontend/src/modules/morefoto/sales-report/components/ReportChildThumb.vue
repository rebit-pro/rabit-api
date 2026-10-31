<script setup lang="ts">
import { shallowRef } from 'vue';
import { useProtectedImage } from '@/composables/useProtectedImage';
import { childPreviewSource } from '../api';
import PhotoZoomDialog from '../../gallery/components/PhotoZoomDialog.vue';

// E10-DEC-08: the first ready frame of the child; the server shows it only after the group got its gallery link.
const props = defineProps<{
  childId: string;
  code: string;
  hasPreview: boolean;
}>();
const { source, failed } = useProtectedImage(() => (props.hasPreview ? childPreviewSource(props.childId) : null));
const open = shallowRef(false);
const { source: enlarged, failed: enlargedFailed } = useProtectedImage(() =>
  open.value && props.hasPreview ? childPreviewSource(props.childId, 'preview') : null
);
</script>

<template>
  <span class="report-child">
    <button
      v-if="source && !failed"
      type="button"
      class="report-child__thumb report-child__button"
      :aria-label="'Увеличить фото ребёнка ' + code"
      @click="open = true"
    >
      <img :src="source" alt="" />
    </button>
    <span v-else class="report-child__thumb" aria-hidden="true"><v-icon icon="mdi-image-outline" size="18" /></span>
    <span class="report-child__code">{{ code }}</span>
    <PhotoZoomDialog :open="open" :src="enlarged ?? ''" :label="'Ребёнок ' + code" :error="enlargedFailed" @close="open = false" />
  </span>
</template>

<style scoped>
.report-child {
  display: inline-flex;
  align-items: center;
  gap: var(--mf-space-3);
  min-width: 0;
}
.report-child__thumb {
  display: inline-grid;
  flex: 0 0 auto;
  place-items: center;
  width: 44px;
  height: 56px;
  overflow: hidden;
  border-radius: var(--mf-radius-xs);
  background: var(--mf-color-surface-2);
  color: var(--mf-color-text-tertiary);
}
.report-child__thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.report-child__button {
  padding: 0;
  cursor: zoom-in;
}
.report-child__button:focus-visible {
  outline: 2px solid var(--mf-color-focus);
  outline-offset: 2px;
}
.report-child__code {
  font-family: var(--mf-font-display);
  font-size: var(--mf-text-lg);
  font-weight: var(--mf-weight-semibold);
}
</style>
