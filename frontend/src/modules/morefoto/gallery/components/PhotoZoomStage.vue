<script setup lang="ts">
import { computed, shallowRef, useTemplateRef } from 'vue';

const stage = useTemplateRef<HTMLElement>('stage');
const scale = shallowRef(1);
const x = shallowRef(0);
const y = shallowRef(0);
let drag: {
  pointerId: number;
  x: number;
  y: number;
  left: number;
  top: number;
} | null = null;

const canvasStyle = computed(() => ({
  transform: `translate(${x.value}px, ${y.value}px) scale(${scale.value})`
}));

function clamp(value: number, size: number): number {
  const edge = ((scale.value - 1) * size) / 2;
  return Math.max(-edge, Math.min(edge, value));
}

function zoom(value: number): void {
  scale.value = Math.max(1, Math.min(4, value));
  x.value = clamp(x.value, stage.value?.clientWidth ?? 0);
  y.value = clamp(y.value, stage.value?.clientHeight ?? 0);
}

function reset(): void {
  drag = null;
  scale.value = 1;
  x.value = 0;
  y.value = 0;
}

function pointerDown(event: PointerEvent): void {
  if (scale.value === 1 || event.button !== 0) return;
  const target = event.currentTarget as HTMLElement;
  drag = {
    pointerId: event.pointerId,
    x: event.clientX,
    y: event.clientY,
    left: x.value,
    top: y.value
  };
  target.setPointerCapture(event.pointerId);
  event.preventDefault();
}

function pointerMove(event: PointerEvent): void {
  if (drag?.pointerId !== event.pointerId) return;
  x.value = clamp(drag.left + event.clientX - drag.x, stage.value?.clientWidth ?? 0);
  y.value = clamp(drag.top + event.clientY - drag.y, stage.value?.clientHeight ?? 0);
}

function pointerUp(event: PointerEvent): void {
  if (drag?.pointerId !== event.pointerId) return;
  drag = null;
  const target = event.currentTarget as HTMLElement;
  if (target.hasPointerCapture(event.pointerId)) target.releasePointerCapture(event.pointerId);
}
</script>

<template>
  <div class="photo-zoom">
    <div
      ref="stage"
      class="photo-zoom__stage"
      :class="{ 'photo-zoom__stage--enlarged': scale > 1 }"
      data-testid="photo-zoom-stage"
      @pointerdown="pointerDown"
      @pointermove="pointerMove"
      @pointerup="pointerUp"
      @pointercancel="pointerUp"
      @dblclick="zoom(scale === 1 ? 2 : 1)"
    >
      <div class="photo-zoom__canvas" :style="canvasStyle"><slot /></div>
    </div>
    <div class="photo-zoom__controls" role="group" aria-label="Масштаб фотографии">
      <v-btn
        icon="mdi-minus"
        variant="outlined"
        density="comfortable"
        aria-label="Уменьшить фото"
        :disabled="scale === 1"
        @click="zoom(scale - 0.5)"
      />
      <span aria-live="polite" data-testid="photo-zoom-level">{{ Math.round(scale * 100) }}%</span>
      <v-btn
        icon="mdi-plus"
        variant="outlined"
        density="comfortable"
        aria-label="Увеличить фото"
        :disabled="scale === 4"
        @click="zoom(scale + 0.5)"
      />
      <v-btn variant="text" density="comfortable" :disabled="scale === 1" @click="reset">Сбросить</v-btn>
    </div>
  </div>
</template>

<style scoped>
.photo-zoom {
  display: grid;
  min-width: 0;
  min-height: 0;
  height: 100%;
  grid-template-rows: minmax(0, 1fr) auto;
  background: var(--mf-color-surface-2);
}
.photo-zoom__stage {
  min-height: 0;
  overflow: hidden;
  touch-action: auto;
}
.photo-zoom__stage--enlarged {
  cursor: grab;
  touch-action: none;
}
.photo-zoom__stage--enlarged:active {
  cursor: grabbing;
}
.photo-zoom__canvas {
  width: 100%;
  height: 100%;
  transform-origin: center;
}
.photo-zoom__canvas :deep(img) {
  user-select: none;
  -webkit-user-drag: none;
}
.photo-zoom__controls {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: var(--mf-space-2);
  min-height: 50px;
  padding: var(--mf-space-1);
  background: var(--mf-color-surface);
}
.photo-zoom__controls span {
  min-width: 48px;
  text-align: center;
  font-variant-numeric: tabular-nums;
}
.photo-zoom__controls :deep(.v-btn) {
  min-width: 44px;
  min-height: 44px;
}
@media (max-width: 479px) {
  .photo-zoom__controls {
    gap: 4px;
  }
}
</style>
