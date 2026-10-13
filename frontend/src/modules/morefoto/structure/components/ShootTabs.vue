<script setup lang="ts">
import { computed } from 'vue';
const props = defineProps<{ institutionId: string; shootId: string; current: 'groups' | 'photos' | 'conditions' }>();
const base = computed(
  () => '/cabinet/institutions/' + encodeURIComponent(props.institutionId) + '/shoots/' + encodeURIComponent(props.shootId)
);
const tabs = computed(() => [
  { key: 'groups', title: 'Группы', icon: 'mdi-account-group-outline', to: base.value },
  { key: 'photos', title: 'Фотографии', icon: 'mdi-image-multiple-outline', to: base.value + '/photos' },
  { key: 'conditions', title: 'Условия', icon: 'mdi-tag-outline', to: base.value + '/conditions' }
]);
</script>

<template>
  <!-- FB-2: the sections of a shoot read as tabs at a glance — a segmented bar with icons, the current one raised. -->
  <nav class="shoot-tabs" aria-label="Разделы съёмки">
    <RouterLink
      v-for="tab in tabs"
      :key="tab.key"
      :to="tab.to"
      class="shoot-tabs__tab"
      :class="{ 'shoot-tabs__tab--current': tab.key === current }"
      :aria-current="tab.key === current ? 'page' : undefined"
      ><v-icon :icon="tab.icon" size="20" aria-hidden="true" />{{ tab.title }}</RouterLink
    >
  </nav>
</template>

<style scoped>
.shoot-tabs {
  display: inline-flex;
  max-width: 100%;
  gap: var(--mf-space-1);
  margin-bottom: var(--mf-space-6);
  padding: var(--mf-space-1);
  overflow-x: auto;
  border: 1px solid var(--mf-color-workspace-border);
  border-radius: var(--mf-radius-md);
  background: var(--mf-color-workspace);
}
.shoot-tabs__tab {
  display: inline-flex;
  align-items: center;
  gap: var(--mf-space-2);
  min-height: 44px;
  padding: 0 var(--mf-space-5);
  border-radius: var(--mf-radius-sm);
  color: var(--mf-color-text-secondary);
  font-weight: var(--mf-weight-semibold);
  text-decoration: none;
  white-space: nowrap;
  transition:
    background-color var(--mf-duration-fast) var(--mf-ease-standard),
    color var(--mf-duration-fast) var(--mf-ease-standard);
}
.shoot-tabs__tab:hover {
  color: var(--mf-color-primary);
  background: var(--mf-color-workspace-row-hover);
}
.shoot-tabs__tab--current,
.shoot-tabs__tab--current:hover {
  background: var(--mf-color-surface);
  color: var(--mf-color-primary);
  box-shadow: var(--mf-shadow-xs);
}
@media (max-width: 599px) {
  .shoot-tabs {
    display: flex;
  }
  .shoot-tabs__tab {
    flex: 1 0 auto;
    justify-content: center;
    padding: 0 var(--mf-space-3);
  }
}
</style>
