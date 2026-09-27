<script setup lang="ts">
import { computed, shallowRef, watch } from 'vue';
import MfProgress from '@/components/viz/MfProgress.vue';
import { isMockApiEnabled } from '@/mocks/config';
import { useAuthStore } from '@/stores/auth';
import { photoLimits } from '../rules';
import type { ArchivePlan as Plan } from '../archive';
import { sessionTooShort } from '../upload-retry';
import { useArchivePlan, type ChosenArchive } from '../composables/useArchivePlan';
import type { UploadJob } from '../types';
import type { ManagedGroup } from '../../organization/types';
import { writeDemo } from '../../mocks/storage';
import ArchivePlan from './ArchivePlan.vue';
import ArchiveProgress from './ArchiveProgress.vue';
import UploadQueue from './UploadQueue.vue';
const props = defineProps<{
  jobs: UploadJob[];
  groups: ManagedGroup[];
  busy: boolean;
  paused: boolean;
  disabled: boolean;
  queued: number;
  accepted: number;
  waiting: number;
  failed: number;
  error: string;
  groupName: string;
  /** Codes of the children already labelled in the group: group frames go to them too. */
  childCodes: string[];
  previous: number;
}>();
const emit = defineEmits<{
  files: [files: File[]];
  archive: [plan: Plan, chosen: ChosenArchive[]];
  start: [];
  pause: [];
  retry: [id: string];
  clear: [];
  remove: [id: string];
}>();
// The demo prepares frames in the browser without a server; archives need the server labels, so only live has them.
const mode = shallowRef<'photos' | 'archive'>('photos');
const archiveMode = computed(() => mode.value === 'archive');
const archives = useArchivePlan(() => props.childCodes);
const auth = useAuthStore();
// A conservative 1 MB/s uplink; the doubled margin lives in sessionTooShort.
const sessionShort = computed(() => {
  const plan = archives.plan.value;
  const expires = auth.expiresAt ? Date.parse(auth.expiresAt) : NaN;
  return plan !== null && sessionTooShort(Number.isNaN(expires) ? null : expires, Date.now(), plan.bytes, 1024 * 1024);
});
function chooseArchives(files: File | File[] | null) {
  const selected = Array.isArray(files) ? files : files ? [files] : [];
  if (selected.length) void archives.read(selected);
  input.value = [];
}
function startArchive() {
  const plan = archives.plan.value;
  if (!plan) return;
  emit('archive', plan, archives.chosen.value);
  archives.reset();
}
const input = shallowRef<File[]>([]);
function choose(files: File | File[] | null) {
  const selected = Array.isArray(files) ? files : files ? [files] : [];
  if (selected.length) emit('files', selected);
  input.value = [];
}
// Files dropped on the zone go to the same queue as the chosen ones; the queue checks their type and size.
const dragging = shallowRef(false);
function drop(event: DragEvent) {
  dragging.value = false;
  if (props.disabled || props.busy) return;
  const dropped = Array.from(event.dataTransfer?.files ?? []);
  if (archiveMode.value) chooseArchives(dropped);
  else choose(dropped);
}
const hasArchiveJobs = computed(() => props.jobs.some((job) => job.archive));
const finished = computed(() => props.jobs.filter((job) => job.status === 'done' || job.status === 'duplicate').length);
// The list folds away once every file is through, so a finished batch stops pushing the frames down; a failed or a
// waiting file keeps it open, because it needs an action.
const settled = computed(() => props.jobs.length > 0 && finished.value === props.jobs.length);
const listOpen = shallowRef(!settled.value);
watch(settled, (value) => {
  listOpen.value = !value;
});
</script>
<template>
  <section class="mf-panel" aria-labelledby="upload-heading">
    <h2 id="upload-heading">{{ isMockApiEnabled ? 'Подготовить фотографии' : 'Загрузить фотографии' }}</h2>
    <p class="mf-muted mt-2 mb-5">
      Файлы попадут в группу «{{ groupName }}». JPEG, PNG, WebP · до 25 МБ и 40 Мп · до {{ photoLimits.batch }} файлов за раз.
    </p>
    <v-btn-toggle
      v-if="!isMockApiEnabled"
      v-model="mode"
      mandatory
      divided
      variant="outlined"
      color="primary"
      class="mb-5 upload-mode"
      aria-label="Способ загрузки"
      :disabled="busy"
    >
      <v-btn value="photos" data-testid="upload-mode-photos">По фото</v-btn>
      <v-btn value="archive" data-testid="upload-mode-archive">ZIP по детям</v-btn>
    </v-btn-toggle>
    <p v-if="archiveMode" class="mb-5">
      Каждая папка в архиве — ребёнок: имя папки становится его кодом (A, B, …, AA). Папка с именем на GROUP (GROUP-1) — групповые кадры:
      они хранятся один раз и достаются всем детям группы. Можно выбрать сразу несколько архивов. Архив не отправляется целиком: браузер
      берёт из него по одному фото.
    </p>
    <v-alert v-if="isMockApiEnabled" type="info" variant="tonal" class="mb-5"
      >Демонстрация: создаём превью с водяным знаком в этом браузере. Файлы не отправляются на сервер; исходники сохраните у себя.</v-alert
    >
    <v-alert v-else type="info" variant="tonal" class="mb-5">
      Исходник отправляется в приватное хранилище. В интерфейсе публикуются только защищённые превью с водяным знаком.
    </v-alert>
    <div
      class="upload-drop"
      :class="{ 'upload-drop--active': dragging }"
      data-testid="upload-drop"
      @dragenter.prevent="dragging = !disabled && !busy"
      @dragover.prevent
      @dragleave.self="dragging = false"
      @drop.prevent="drop"
    >
      <template v-if="archiveMode">
        <v-icon icon="mdi-folder-zip-outline" size="32" class="upload-drop__icon" aria-hidden="true" />
        <p><strong>Перетащите ZIP-архивы сюда</strong> или выберите их</p>
        <v-file-input
          :model-value="input"
          multiple
          accept=".zip,application/zip"
          label="Выбрать архивы"
          aria-label="Выбрать ZIP-архивы"
          :disabled="disabled || busy || archives.reading.value"
          :loading="archives.reading.value"
          hide-details
          @update:model-value="chooseArchives"
        />
      </template>
      <template v-else>
        <v-icon icon="mdi-image-multiple-outline" size="32" class="upload-drop__icon" aria-hidden="true" />
        <p><strong>Перетащите фотографии сюда</strong> или выберите файлы</p>
        <v-file-input
          :model-value="input"
          multiple
          accept="image/jpeg,image/png,image/webp"
          label="Выбрать фотографии"
          aria-label="Выбрать фотографии"
          :disabled="disabled || busy"
          hide-details
          @update:model-value="choose"
        />
      </template>
    </div>
    <p v-if="archives.reading.value" class="mt-4" role="status">Читаем содержимое архивов…</p>
    <v-alert v-if="archiveMode && archives.error.value" type="error" variant="tonal" role="alert" class="mt-4">{{
      archives.error.value
    }}</v-alert>
    <ArchivePlan
      v-if="archiveMode && archives.plan.value"
      :plan="archives.plan.value"
      :archives="archives.chosen.value.map((item) => item.source.name)"
      :marks="archives.marks.value"
      :session-short="sessionShort"
      :disabled="disabled || busy"
      @toggle="archives.toggleGroup"
      @start="startArchive"
      @reset="archives.reset"
    />
    <div class="mf-actions mt-5">
      <v-btn
        v-if="!archiveMode || queued || busy"
        :disabled="!queued || busy || disabled"
        :loading="busy && !paused"
        @click="$emit('start')"
        >{{ isMockApiEnabled ? 'Начать подготовку' : paused && queued ? 'Продолжить загрузку' : 'Загрузить на сервер' }}</v-btn
      ><v-btn v-if="busy && !isMockApiEnabled" variant="outlined" :disabled="paused" @click="$emit('pause')">{{
        paused ? 'Остановим после текущих файлов' : 'Пауза'
      }}</v-btn
      ><v-btn variant="outlined" :disabled="busy || !jobs.some((job) => ['done', 'duplicate'].includes(job.status))" @click="$emit('clear')"
        >Убрать завершённые из очереди</v-btn
      >
    </div>
    <ArchiveProgress v-if="hasArchiveJobs" :jobs="jobs" :previous="previous" />
    <MfProgress v-else-if="jobs.length" class="mt-5" label="Загружено" :value="finished" :max="jobs.length" />
    <p class="mt-4" role="status" data-testid="upload-counts">
      Готово: {{ accepted }} · В работе: {{ waiting }} · Требуют внимания: {{ failed }} · В очереди: {{ queued }}
    </p>
    <v-alert v-if="error" type="error" variant="tonal" role="alert" class="mt-4">{{ error }}</v-alert>
    <v-btn
      v-if="jobs.length"
      variant="text"
      class="mt-2"
      :append-icon="listOpen ? 'mdi-chevron-up' : 'mdi-chevron-down'"
      :aria-expanded="listOpen"
      aria-controls="upload-queue-list"
      @click="listOpen = !listOpen"
      >{{ listOpen ? 'Скрыть список файлов' : 'Показать список файлов (' + jobs.length + ')' }}</v-btn
    >
    <UploadQueue
      v-show="listOpen"
      id="upload-queue-list"
      :jobs="jobs"
      :busy="busy"
      :groups="groups"
      @retry="$emit('retry', $event)"
      @remove="$emit('remove', $event)"
    />
    <details v-if="isMockApiEnabled" class="upload-demo">
      <summary>Проверка демонстрации</summary>
      <v-btn variant="text" :disabled="busy" @click="writeDemo('photos:fail-next', true)">Ошибка следующего файла</v-btn>
    </details>
  </section>
</template>
<style scoped>
.upload-drop {
  display: grid;
  justify-items: center;
  gap: var(--mf-space-3);
  padding: var(--mf-space-6) var(--mf-space-5) var(--mf-space-5);
  border: 2px dashed var(--mf-color-border-strong);
  border-radius: var(--mf-radius-md);
  background: var(--mf-color-surface-2);
  text-align: center;
  transition:
    border-color var(--mf-duration-fast) ease,
    background var(--mf-duration-fast) ease;
}
.upload-drop--active {
  border-color: var(--mf-color-primary);
  background: var(--mf-color-primary-soft);
}
.upload-drop__icon {
  color: var(--mf-color-text-secondary);
}
.upload-drop .v-input {
  width: 100%;
  max-width: 460px;
}
.upload-demo {
  margin-top: 24px;
}
.upload-mode {
  max-width: 100%;
}
.upload-demo summary {
  cursor: pointer;
  min-height: 44px;
  padding: 12px 0;
  color: rgb(var(--v-theme-primary));
}
</style>
