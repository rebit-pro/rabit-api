<script setup lang="ts">
import { computed } from 'vue';
import type { RouteLocationRaw } from 'vue-router';
import MfStatus from '../status/MfStatus.vue';
import type { StatusTone } from '../status/tones';

export interface QueueItem {
  id: string;
  title: string;
  subtitle?: string;
  status: { tone: StatusTone; label: string };
  note?: { tone: StatusTone; label: string };
  to?: RouteLocationRaw;
}

const props = withDefaults(
  defineProps<{
    title: string;
    items: QueueItem[];
    /** Everything waiting, not only the rows shown. */
    total: number;
    allTo?: RouteLocationRaw | null;
    emptyText?: string;
  }>(),
  { allTo: null, emptyText: 'Здесь пока пусто.' }
);

const shown = computed(() => props.items.slice(0, 5));
</script>

<template>
  <section class="mf-queue" :aria-label="title" data-testid="queue">
    <header class="mf-queue__header">
      <h2>{{ title }}</h2>
      <router-link v-if="allTo && total > 0" :to="allTo" class="mf-queue__all">Все {{ total }}</router-link>
    </header>
    <p v-if="!shown.length" class="mf-muted">{{ emptyText }}</p>
    <ul v-else class="mf-queue__list">
      <li v-for="item in shown" :key="item.id">
        <component :is="item.to ? 'router-link' : 'div'" :to="item.to" class="mf-queue__row">
          <span class="mf-queue__text">
            <strong>{{ item.title }}</strong>
            <small v-if="item.subtitle">{{ item.subtitle }}</small>
          </span>
          <MfStatus :tone="item.status.tone">{{ item.status.label }}</MfStatus>
          <span v-if="item.note" class="mf-queue__note" :class="`mf-queue__note--${item.note.tone}`">{{ item.note.label }}</span>
        </component>
      </li>
    </ul>
  </section>
</template>

<style scoped>
.mf-queue {
  display: grid;
  gap: var(--mf-space-3);
}
.mf-queue__header {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: var(--mf-space-3);
}
.mf-queue__header h2 {
  font-family: var(--mf-font-display);
  font-size: var(--mf-text-lg);
  font-weight: var(--mf-weight-semibold);
}
.mf-queue__all {
  color: var(--mf-color-link);
  font-size: var(--mf-text-md);
  font-weight: var(--mf-weight-medium);
}
.mf-queue__list {
  margin: 0;
  padding: 0;
  list-style: none;
  border-top: 1px solid var(--mf-color-border);
}
.mf-queue__row {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto auto;
  align-items: center;
  gap: var(--mf-space-3);
  min-height: var(--mf-touch-size);
  padding: var(--mf-space-2) 0;
  border-bottom: 1px solid var(--mf-color-border);
  color: inherit;
  text-decoration: none;
}
a.mf-queue__row:hover .mf-queue__text strong,
a.mf-queue__row:focus-visible .mf-queue__text strong {
  color: var(--mf-color-link);
}
.mf-queue__text {
  display: grid;
  gap: 2px;
  min-width: 0;
}
.mf-queue__text strong {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.mf-queue__text small {
  overflow: hidden;
  color: var(--mf-color-text-secondary);
  font-size: var(--mf-text-sm);
  text-overflow: ellipsis;
  white-space: nowrap;
}
.mf-queue__note {
  font-size: var(--mf-text-sm);
  font-weight: var(--mf-weight-semibold);
}
.mf-queue__note--neutral {
  color: var(--mf-color-text-secondary);
}
.mf-queue__note--info {
  color: var(--mf-tone-info-fg);
}
.mf-queue__note--warning {
  color: var(--mf-tone-warning-fg);
}
.mf-queue__note--danger {
  color: var(--mf-tone-danger-fg);
}
@media (max-width: 599px) {
  .mf-queue__row {
    grid-template-columns: minmax(0, 1fr) auto;
  }
  .mf-queue__note {
    grid-column: 1 / -1;
  }
}
</style>
