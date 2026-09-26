<script setup lang="ts">
import type { GalleryStep, GalleryStepKey } from '../galleryPath';
defineProps<{
  steps: GalleryStep[];
  /** Where a step with a problem is fixed; null — nowhere to go from this screen. */
  fix?: (key: GalleryStepKey) => { to: string; label: string } | null;
}>();
</script>
<template>
  <ol class="gallery-path" aria-label="Путь к галерее" data-testid="gallery-path">
    <li
      v-for="(step, index) in steps"
      :key="step.key"
      :class="'gallery-path__step gallery-path__step--' + step.state"
      :aria-current="step.state === 'current' ? 'step' : undefined"
      :data-step="step.key"
    >
      <span class="gallery-path__mark" aria-hidden="true">
        <v-icon v-if="step.state === 'done'" icon="mdi-check" size="14" />
        <template v-else>{{ index + 1 }}</template>
      </span>
      <div class="gallery-path__body">
        <strong>{{ step.title }}</strong>
        <span v-if="step.state === 'done'" class="mf-sr-only"> — выполнено</span>
        <span v-else-if="step.state === 'current'" class="mf-sr-only"> — текущий шаг</span>
        <p v-if="step.hint">
          {{ step.hint }}
          <RouterLink v-if="fix?.(step.key)" :to="fix(step.key)!.to">{{ fix(step.key)!.label }}</RouterLink>
        </p>
      </div>
    </li>
  </ol>
</template>
<style scoped>
.gallery-path {
  display: grid;
  gap: 10px;
  margin: 0 0 16px;
  padding: 0;
  list-style: none;
}
.gallery-path__step {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  color: var(--mf-color-text-secondary);
}
.gallery-path__mark {
  display: inline-flex;
  flex: 0 0 22px;
  align-items: center;
  justify-content: center;
  width: 22px;
  height: 22px;
  border: 1px solid var(--mf-color-border);
  border-radius: 50%;
  font-size: 12px;
  font-weight: 600;
}
.gallery-path__step--done .gallery-path__mark {
  border-color: var(--mf-tone-success-fg);
  color: var(--mf-tone-success-fg);
}
.gallery-path__step--current {
  color: var(--mf-color-text);
}
.gallery-path__step--current .gallery-path__mark {
  border-color: var(--mf-color-primary);
  background: var(--mf-color-primary);
  color: var(--mf-color-on-primary);
}
.gallery-path__body {
  min-width: 0;
  overflow-wrap: anywhere;
}
.gallery-path__body p {
  margin-top: 2px;
  font-size: var(--mf-text-sm);
}
.gallery-path__step--todo .gallery-path__body p {
  color: var(--mf-tone-warning-fg);
}
.gallery-path__body a {
  color: var(--mf-color-link);
}
</style>
