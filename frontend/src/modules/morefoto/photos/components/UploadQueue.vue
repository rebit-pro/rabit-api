<script setup lang="ts">
import { isMockApiEnabled } from '@/mocks/config';
import type { ManagedGroup } from '../../organization/types';
import type { QueueStatus, UploadJob } from '../types';
defineProps<{ jobs: UploadJob[]; busy: boolean; groups: ManagedGroup[] }>();
defineEmits<{ retry: [id: string]; remove: [id: string] }>();
const labels: Record<QueueStatus, string> = {
  queued: 'В очереди',
  uploading: 'Загружается оригинал',
  processing: isMockApiEnabled ? 'Подготовка' : 'Обрабатывается',
  done: 'Готово',
  duplicate: 'Повтор файла',
  error: 'Ошибка',
  interrupted: 'Нужен исходный файл'
};
</script>
<template>
  <ul class="upload-queue" aria-label="Очередь файлов">
    <li v-for="job in jobs" :key="job.id" v-memo="[job, busy, groups]" :data-upload-id="job.id" class="upload-row">
      <div>
        <strong>{{ job.filename }}</strong>
        <p class="mf-muted">
          {{ groups.find((item) => item.id === job.groupId)?.name ?? 'Группа недоступна' }} · {{ (job.bytes / 1024 / 1024).toFixed(2) }} МБ
        </p>
      </div>
      <div class="upload-result">
        <span :class="{ 'text-error': job.status === 'error' }">{{ labels[job.status] }}</span>
        <p>{{ job.message }}</p>
        <v-progress-linear
          v-if="job.status === 'uploading' || (isMockApiEnabled && job.status === 'processing')"
          :model-value="job.progress"
          :aria-label="'Отправка: ' + job.filename"
          color="primary"
          height="6"
          class="mt-2"
        />
        <v-progress-linear
          v-else-if="job.status === 'processing'"
          indeterminate
          :aria-label="'Обработка на сервере: ' + job.filename"
          color="primary"
          height="6"
          class="mt-2"
        />
      </div>
      <div class="mf-actions">
        <v-btn
          v-if="job.status === 'error' || job.status === 'interrupted'"
          variant="outlined"
          :disabled="busy"
          :aria-label="'Повторить файл ' + job.filename"
          @click="$emit('retry', job.id)"
          >Повторить</v-btn
        ><v-btn
          icon="mdi-close"
          variant="text"
          :disabled="busy"
          :aria-label="'Убрать из очереди ' + job.filename"
          @click="$emit('remove', job.id)"
        />
      </div>
    </li>
  </ul>
</template>
<style scoped>
.upload-queue {
  list-style: none;
  padding: 0;
  margin: 24px 0 0;
}
.upload-row {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1.4fr) auto;
  gap: 16px;
  align-items: center;
  padding: 16px 0;
  border-top: 1px solid var(--mf-color-border);
  overflow-wrap: anywhere;
}
.upload-result {
  font-size: var(--mf-text-small);
}
.upload-result p {
  margin-top: 4px;
}
@media (max-width: 767px) {
  .upload-row {
    grid-template-columns: minmax(0, 1fr);
  }
}
</style>
