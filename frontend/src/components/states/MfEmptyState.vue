<script setup lang="ts">
withDefaults(
  defineProps<{
    title: string;
    text?: string;
    icon?: string;
    /** Data pastel index 0–7 of the category (design plan 9.2); 7 is the neutral pebble. */
    category?: number;
    headingTag?: 'h2' | 'h3';
  }>(),
  { text: '', icon: 'mdi-folder-open-outline', category: 7, headingTag: 'h3' }
);
</script>

<template>
  <div class="mf-empty-state" role="status">
    <span
      class="mf-empty-state__icon"
      :style="{ background: `var(--mf-avatar-${category}-bg)`, color: `var(--mf-avatar-${category}-fg)` }"
      aria-hidden="true"
    >
      <v-icon :icon="icon" size="24" />
    </span>
    <component :is="headingTag" class="mf-empty-state__title">{{ title }}</component>
    <p v-if="text" class="mf-empty-state__text">{{ text }}</p>
    <div v-if="$slots.default" class="mf-empty-state__actions"><slot /></div>
  </div>
</template>

<style scoped>
.mf-empty-state {
  display: grid;
  justify-items: center;
  gap: var(--mf-space-3);
  padding: var(--mf-space-10) var(--mf-space-6);
  text-align: center;
}
.mf-empty-state__icon {
  display: grid;
  place-items: center;
  width: 48px;
  height: 48px;
  border-radius: var(--mf-radius-full);
}
.mf-empty-state__title {
  font-size: var(--mf-text-lg);
  font-weight: var(--mf-weight-semibold);
  line-height: var(--mf-leading-snug);
}
.mf-empty-state__text {
  max-width: 480px;
  color: var(--mf-color-text-secondary);
  line-height: var(--mf-leading-normal);
}
.mf-empty-state__actions {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: var(--mf-space-3);
  margin-top: var(--mf-space-1);
}
</style>
