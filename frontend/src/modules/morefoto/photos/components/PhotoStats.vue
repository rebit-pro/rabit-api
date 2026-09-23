<script setup lang="ts">
import { computed } from 'vue';
import MfProgress from '@/components/viz/MfProgress.vue';
import { processed } from '@/components/viz/measures';
import type { StatusTone } from '@/components/status/tones';
import type { ServerPhotoStats } from '../api';
const props = defineProps<{ stats: ServerPhotoStats }>();
const progress = computed(() => processed(props.stats.byStatus));
// INF-05: is the group's set ready to be published — what the server finished, what failed, what still lacks a child.
const tiles = computed<{ key: string; label: string; value: number; tone: StatusTone }[]>(() => [
  { key: 'ready', label: 'Готово', value: props.stats.byStatus.ready, tone: 'success' },
  { key: 'processing', label: 'Обрабатывается', value: props.stats.byStatus.processing, tone: 'info' },
  {
    key: 'failed',
    label: 'Ошибка обработки',
    value: props.stats.byStatus.failed,
    tone: props.stats.byStatus.failed ? 'danger' : 'neutral'
  },
  { key: 'duplicate', label: 'Повторы файлов', value: props.stats.byStatus.duplicate, tone: 'neutral' },
  { key: 'unassigned', label: 'Без ребёнка', value: props.stats.unassigned, tone: props.stats.unassigned ? 'warning' : 'neutral' }
]);
</script>

<template>
  <div class="photo-stats" data-testid="photo-stats">
    <MfProgress label="Обработано" :value="progress.done" :max="progress.total" />
    <dl class="photo-stats__tiles">
      <div v-for="tile in tiles" :key="tile.key" class="photo-stats__tile" :class="`photo-stats__tile--${tile.tone}`">
        <dt>{{ tile.label }}</dt>
        <dd>{{ tile.value }}</dd>
      </div>
    </dl>
  </div>
</template>

<style scoped>
.photo-stats {
  display: grid;
  gap: var(--mf-space-4);
}
.photo-stats__tiles {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: var(--mf-space-2);
  margin: 0;
}
.photo-stats__tile {
  display: grid;
  gap: var(--mf-space-1);
  padding: var(--mf-space-3);
  border: 1px solid var(--mf-tone-neutral-border);
  border-radius: var(--mf-radius-sm);
  background: var(--mf-color-surface);
}
.photo-stats__tile dt {
  color: var(--mf-color-text-secondary);
  font-size: var(--mf-text-sm);
}
.photo-stats__tile dd {
  margin: 0;
  font-size: var(--mf-text-lg);
  font-weight: var(--mf-weight-semibold);
  font-variant-numeric: tabular-nums lining-nums;
}
.photo-stats__tile--success {
  border-color: var(--mf-tone-success-border);
}
.photo-stats__tile--info {
  border-color: var(--mf-tone-info-border);
}
.photo-stats__tile--warning {
  border-color: var(--mf-tone-warning-border);
  background: var(--mf-tone-warning-bg);
}
.photo-stats__tile--danger {
  border-color: var(--mf-tone-danger-border);
  background: var(--mf-tone-danger-bg);
}
@media (max-width: 767px) {
  .photo-stats__tiles {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
</style>
