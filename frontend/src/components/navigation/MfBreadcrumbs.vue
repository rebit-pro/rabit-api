<script setup lang="ts">
export interface Crumb {
  title: string;
  /** Every crumb but the current page is a link. */
  to?: string;
}
defineProps<{ items: Crumb[] }>();
</script>

<template>
  <nav class="mf-breadcrumbs" aria-label="Хлебные крошки">
    <ol>
      <li v-for="(item, index) in items" :key="index" :class="{ 'mf-breadcrumbs__middle': 0 < index && index < items.length - 1 }">
        <RouterLink v-if="item.to && index < items.length - 1" :to="item.to" :title="item.title">{{ item.title }}</RouterLink>
        <span v-else aria-current="page">{{ item.title }}</span>
      </li>
    </ol>
  </nav>
</template>

<style scoped>
.mf-breadcrumbs {
  margin-bottom: var(--mf-space-5);
  font-size: var(--mf-text-sm);
}
.mf-breadcrumbs ol {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--mf-space-1) 0;
  margin: 0;
  padding: 0;
  list-style: none;
}
.mf-breadcrumbs li {
  display: inline-flex;
  align-items: center;
  min-width: 0;
  max-width: 100%;
}
.mf-breadcrumbs li + li::before {
  margin: 0 var(--mf-space-2);
  color: var(--mf-color-text-tertiary);
  content: '/' / '';
}
.mf-breadcrumbs a,
.mf-breadcrumbs span {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.mf-breadcrumbs a {
  display: inline-flex;
  align-items: center;
  min-height: 32px;
  color: var(--mf-color-link);
  text-underline-offset: 3px;
}
.mf-breadcrumbs span {
  color: var(--mf-color-text-secondary);
}
/* On a phone the middle crumbs shrink first; the current page keeps its name. */
@media (max-width: 600px) {
  .mf-breadcrumbs__middle a {
    max-width: 14ch;
  }
}
</style>
