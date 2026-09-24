<script setup lang="ts">
import { computed } from 'vue';
const props = defineProps<{ institutionId: string; shootId: string; current: 'groups' | 'photos' | 'conditions' }>();
const base = computed(
  () => '/cabinet/institutions/' + encodeURIComponent(props.institutionId) + '/shoots/' + encodeURIComponent(props.shootId)
);
const tabs = computed(() => [
  { key: 'groups', title: 'Группы', to: base.value },
  { key: 'photos', title: 'Фотографии', to: base.value + '/photos' },
  { key: 'conditions', title: 'Условия', to: base.value + '/conditions' }
]);
</script>

<template>
  <nav class="shoot-tabs" aria-label="Разделы съёмки">
    <RouterLink
      v-for="tab in tabs"
      :key="tab.key"
      :to="tab.to"
      class="shoot-tabs__tab"
      :class="{ 'shoot-tabs__tab--current': tab.key === current }"
      :aria-current="tab.key === current ? 'page' : undefined"
      >{{ tab.title }}</RouterLink
    >
  </nav>
</template>

<style scoped>
.shoot-tabs {
  display: flex;
  gap: var(--mf-space-1);
  margin-bottom: var(--mf-space-6);
  overflow-x: auto;
  border-bottom: 1px solid var(--mf-color-border);
}
.shoot-tabs__tab {
  display: inline-flex;
  align-items: center;
  min-height: 44px;
  padding: 0 var(--mf-space-4);
  border-bottom: 2px solid transparent;
  color: var(--mf-color-text-secondary);
  font-weight: var(--mf-weight-medium);
  text-decoration: none;
  white-space: nowrap;
}
.shoot-tabs__tab:hover {
  color: var(--mf-color-text);
  background: var(--mf-color-hover);
}
.shoot-tabs__tab--current {
  border-bottom-color: var(--mf-color-primary);
  color: var(--mf-color-text);
}
</style>
