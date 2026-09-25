<script setup lang="ts">
import { computed } from 'vue';
import { percent } from './measures';

const props = withDefaults(
  defineProps<{
    /** What is counted, for the label and the screen reader: «Обработано». */
    label: string;
    value: number;
    max: number;
    /** Forms of the counted noun: «кадр», «кадра», «кадров». */
    unitForms?: readonly [string, string, string] | null;
  }>(),
  { unitForms: null }
);
const share = computed(() => percent(props.value, props.max));
const text = computed(() => props.label + ' ' + props.value + ' из ' + props.max);
</script>

<template>
  <div class="mf-progress" data-testid="progress">
    <div class="mf-progress__caption">
      <span>{{ text }}</span>
      <strong>{{ share }} %</strong>
    </div>
    <div
      class="mf-progress__track"
      role="progressbar"
      :aria-label="label"
      :aria-valuenow="value"
      aria-valuemin="0"
      :aria-valuemax="max"
      :aria-valuetext="text + ' (' + share + ' %)'"
    >
      <span class="mf-progress__bar" :style="{ width: share + '%' }"></span>
    </div>
  </div>
</template>

<style scoped>
.mf-progress {
  display: grid;
  gap: var(--mf-space-2);
}
.mf-progress__caption {
  display: flex;
  justify-content: space-between;
  gap: var(--mf-space-3);
  font-size: var(--mf-text-sm);
}
.mf-progress__caption strong {
  font-variant-numeric: tabular-nums lining-nums;
}
.mf-progress__track {
  height: 8px;
  overflow: hidden;
  border-radius: var(--mf-radius-full);
  background: var(--mf-color-chart-track);
}
.mf-progress__bar {
  display: block;
  height: 100%;
  border-radius: inherit;
  background: var(--mf-color-chart-accent);
  transition: width var(--mf-duration-slow) ease;
}
@media (forced-colors: active) {
  .mf-progress__track {
    border: 1px solid CanvasText;
  }
  .mf-progress__bar {
    background: Highlight;
  }
}
</style>
