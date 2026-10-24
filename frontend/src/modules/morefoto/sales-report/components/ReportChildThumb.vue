<script setup lang="ts">
import { useProtectedImage } from '@/composables/useProtectedImage';
import { childPreviewSource } from '../api';

// E10-DEC-08: the first ready frame of the child; the server shows it only after the group got its gallery link.
const props = defineProps<{ childId: string; code: string; hasPreview: boolean }>();
const { source, failed } = useProtectedImage(() => (props.hasPreview ? childPreviewSource(props.childId) : null));
</script>

<template>
  <span class="report-child">
    <span class="report-child__thumb" aria-hidden="true">
      <img v-if="source && !failed" :src="source" alt="" />
      <v-icon v-else icon="mdi-image-outline" size="18" />
    </span>
    <span class="report-child__code">{{ code }}</span>
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
  width: 40px;
  height: 52px;
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
.report-child__code {
  font-family: var(--mf-font-display);
  font-size: var(--mf-text-lg);
  font-weight: var(--mf-weight-semibold);
}
</style>
