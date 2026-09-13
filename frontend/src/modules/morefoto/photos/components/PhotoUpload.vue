<script setup lang="ts">
import { shallowRef } from 'vue';
import type { UploadJob } from '../types';
import type { ManagedGroup } from '../../organization/types';
import { writeDemo } from '../../mocks/storage';
import UploadQueue from './UploadQueue.vue';
defineProps<{
  jobs: UploadJob[];
  groups: ManagedGroup[];
  busy: boolean;
  disabled: boolean;
  queued: number;
  accepted: number;
  failed: number;
  error: string;
  groupName: string;
}>();
const emit = defineEmits<{ files: [files: File[]]; start: []; retry: [id: string]; clear: []; remove: [id: string] }>();
const input = shallowRef<File[]>([]);
function choose(files: File | File[] | null) {
  const selected = Array.isArray(files) ? files : files ? [files] : [];
  if (selected.length) emit('files', selected);
  input.value = [];
}
</script>
<template>
  <section class="mf-panel" aria-labelledby="upload-heading">
    <h2 id="upload-heading">Подготовить фотографии</h2>
    <p class="mf-muted mt-2 mb-5">Файлы попадут в группу «{{ groupName }}». JPEG, PNG, WebP · до 25 МБ и 40 Мп · до 50 файлов за раз.</p>
    <v-alert type="info" variant="tonal" class="mb-5"
      >Демонстрация: создаём превью с водяным знаком в этом браузере. Файлы не отправляются на сервер; исходники сохраните у себя.</v-alert
    >
    <v-file-input
      :model-value="input"
      multiple
      accept="image/jpeg,image/png,image/webp"
      label="Выбрать фотографии"
      aria-label="Выбрать фотографии"
      :disabled="disabled || busy"
      @update:model-value="choose"
    />
    <div class="mf-actions mt-5">
      <v-btn :disabled="!queued || busy || disabled" :loading="busy" @click="$emit('start')">Начать подготовку</v-btn
      ><v-btn variant="outlined" :disabled="busy || !jobs.some((job) => ['done', 'duplicate'].includes(job.status))" @click="$emit('clear')"
        >Убрать завершённые из очереди</v-btn
      >
    </div>
    <p class="mt-4" role="status" data-testid="upload-counts">
      Подготовлено: {{ accepted }} · Требуют внимания: {{ failed }} · В очереди: {{ queued }}
    </p>
    <v-alert v-if="error" type="error" variant="tonal" role="alert" class="mt-4">{{ error }}</v-alert>
    <UploadQueue :jobs="jobs" :busy="busy" :groups="groups" @retry="$emit('retry', $event)" @remove="$emit('remove', $event)" />
    <details class="upload-demo">
      <summary>Проверка демонстрации</summary>
      <v-btn variant="text" :disabled="busy" @click="writeDemo('photos:fail-next', true)">Ошибка следующего файла</v-btn>
    </details>
  </section>
</template>
<style scoped>
.upload-demo {
  margin-top: 24px;
}
.upload-demo summary {
  cursor: pointer;
  min-height: 44px;
  padding: 12px 0;
  color: rgb(var(--v-theme-primary));
}
</style>
