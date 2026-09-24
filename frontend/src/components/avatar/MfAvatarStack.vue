<script setup lang="ts">
import { computed } from 'vue';
import MfAvatar from './MfAvatar.vue';

const props = withDefaults(
  defineProps<{
    people: ReadonlyArray<{ seed: string; name?: string | null; email?: string | null }>;
    max?: number;
  }>(),
  { max: 4 }
);
const shown = computed(() => props.people.slice(0, props.max));
const rest = computed(() => Math.max(0, props.people.length - props.max));
const label = computed(() => props.people.map((person) => person.name?.trim() || person.email?.trim() || 'Сотрудник').join(', '));
</script>

<template>
  <span class="mf-avatar-stack" role="img" :aria-label="label">
    <MfAvatar v-for="person in shown" :key="person.seed" v-bind="person" :size="32" decorative class="mf-avatar-stack__item" />
    <span v-if="rest > 0" class="mf-avatar-stack__more" aria-hidden="true">+{{ rest }}</span>
  </span>
</template>

<style scoped>
.mf-avatar-stack {
  display: inline-flex;
  align-items: center;
}
.mf-avatar-stack__item,
.mf-avatar-stack__more {
  box-shadow: var(--mf-shadow-ring-surface);
}
.mf-avatar-stack__item + .mf-avatar-stack__item,
.mf-avatar-stack__more {
  margin-left: -8px;
}
.mf-avatar-stack__more {
  display: inline-grid;
  place-items: center;
  min-width: 32px;
  height: 32px;
  padding: 0 var(--mf-space-2);
  border-radius: var(--mf-radius-full);
  background: var(--mf-color-surface-2);
  color: var(--mf-color-text-secondary);
  font-size: var(--mf-text-xs);
  font-weight: var(--mf-weight-semibold);
  font-variant-numeric: tabular-nums;
}
</style>
