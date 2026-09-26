<script setup lang="ts">
import { computed } from 'vue';
import MfProgress from '@/components/viz/MfProgress.vue';
import { compareCodes } from '../archive';
import type { UploadJob } from '../types';

const props = defineProps<{ jobs: UploadJob[]; previous: number }>();
interface FolderRow {
  folder: string;
  shared: boolean;
  total: number;
  sent: number;
  ready: number;
  attention: number;
}
const sentStatuses = ['processing', 'done', 'duplicate'];
const readyStatuses = ['done', 'duplicate'];
const archiveJobs = computed(() => props.jobs.filter((job) => job.archive));
const total = computed(() => archiveJobs.value.length + props.previous);
const sent = computed(() => archiveJobs.value.filter((job) => sentStatuses.includes(job.status)).length + props.previous);
const ready = computed(() => archiveJobs.value.filter((job) => readyStatuses.includes(job.status)).length + props.previous);
const retrying = computed(() => archiveJobs.value.filter((job) => job.status === 'queued' && job.retryAt).length);
// One pass groups the queue by folder: children in code order, group frames last, as they are sent.
const rows = computed(() => {
  const folders = new Map<string, FolderRow>();
  for (const job of archiveJobs.value) {
    const key = job.folder ?? '';
    let row = folders.get(key);
    if (!row) {
      row = { folder: key, shared: job.shared === true, total: 0, sent: 0, ready: 0, attention: 0 };
      folders.set(key, row);
    }
    row.total++;
    if (sentStatuses.includes(job.status)) row.sent++;
    if (readyStatuses.includes(job.status)) row.ready++;
    if (job.status === 'error' || job.status === 'interrupted') row.attention++;
  }
  return [...folders.values()].sort((left, right) => Number(left.shared) - Number(right.shared) || compareCodes(left.folder, right.folder));
});
</script>

<template>
  <section class="archive-progress" aria-labelledby="archive-progress-heading" data-testid="archive-progress">
    <h3 id="archive-progress-heading">Загрузка архивов</h3>
    <MfProgress class="mt-3" label="Отправлено" :value="sent" :max="total" />
    <MfProgress class="mt-3" label="Превью готовы" :value="ready" :max="total" />
    <p v-if="previous" class="mf-muted mt-2">Отправлено ранее и пропущено: {{ previous }}.</p>
    <p v-if="retrying" class="mt-2" role="status">Ждут повтора после сбоя связи: {{ retrying }}. Повтор идёт сам.</p>
    <details class="archive-progress__folders mt-3">
      <summary>По папкам: {{ rows.length }}</summary>
      <ul>
        <li v-for="row in rows" :key="row.folder" :data-progress-folder="row.folder">
          <strong>{{ row.shared ? row.folder + ' · групповые' : 'Ребёнок ' + row.folder }}</strong>
          <span>отправлено {{ row.sent }} из {{ row.total }} · превью {{ row.ready }}</span>
          <span v-if="row.attention" class="text-error">требуют внимания: {{ row.attention }}</span>
        </li>
      </ul>
    </details>
  </section>
</template>

<style scoped>
.archive-progress {
  margin-top: var(--mf-space-5);
}
.archive-progress__folders summary {
  cursor: pointer;
  min-height: 44px;
  padding: 12px 0;
  color: rgb(var(--v-theme-primary));
}
.archive-progress__folders ul {
  list-style: none;
  padding: 0;
  margin: 0;
  max-height: 320px;
  overflow: auto;
}
.archive-progress__folders li {
  display: flex;
  flex-wrap: wrap;
  gap: 4px 12px;
  padding: 8px 0;
  border-top: 1px solid var(--mf-color-border);
  font-size: var(--mf-text-small);
}
</style>
