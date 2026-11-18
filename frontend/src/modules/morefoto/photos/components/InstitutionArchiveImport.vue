<script setup lang="ts">
import { computed, onScopeDispose, reactive, shallowRef, watch } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { archiveImportKey, planClass, archivePortions, type ArchiveClassChoice, type InstitutionArchive } from '../institution-archive';
import type { ArchivePlan } from '../archive';
import type { ChosenArchive } from '../composables/useArchivePlan';
import { usePhotoQueue } from '../composables/usePhotoQueue';
import { confirmImportGroups, importGroupsUnavailable } from '../institution-import-api';
import { ImportAttempt } from '../import-attempt';
import { browserStorage } from '../archive-progress';
import type { Shoot } from '../../structure/model';
import { structureError } from '../../structure/api';

const props = defineProps<{
  institutionId: string;
  shoot: Shoot;
  root: string;
  archive: InstitutionArchive;
  chosen: ChosenArchive;
}>();
const emit = defineEmits<{
  working: [value: boolean];
  changed: [];
}>();
const auth = useAuthStore();
const scope = ':institution:' + props.institutionId;
const sending = shallowRef(false);
const started = shallowRef(false);
const finished = shallowRef(false);
const unavailable = shallowRef(false);
const q = usePhotoQueue(props.shoot.id, {
  importScope: scope,
  pendingImport: () => sending.value || (started.value && !finished.value)
});
const storageKey =
  'morefoto:import:' + JSON.stringify([auth.user?.id, props.institutionId, props.shoot.id, props.chosen.source.key, props.root]);
const attempt = new ImportAttempt(browserStorage(), storageKey + ':attempt');
function readChoices(): Map<string, ArchiveClassChoice> {
  try {
    const saved: ArchiveClassChoice[] = JSON.parse(localStorage.getItem(storageKey) ?? '[]');
    return new Map(saved.filter((item) => typeof item.name === 'string' && Array.isArray(item.codes)).map((item) => [item.name, item]));
  } catch {
    return new Map();
  }
}
const saved = readChoices();
const openSeries = reactive(new Set<string>());
function showFiles(path: string, event: Event) {
  if ((event.currentTarget as HTMLDetailsElement).open) openSeries.add(path);
  else openSeries.delete(path);
}
const overrides = reactive(new Map<string, Map<string, string>>());
const controller = new AbortController();
const problem = shallowRef('');
const records = reactive(new Map<string, { name: string; total: number; done: number; skipped: number }>());
let batches: Generator<{ id: string; plan: ArchivePlan }> | undefined;

const rows = computed(() =>
  props.archive.groups.map((folder) => ({
    folder,
    plan: planClass(folder, overrides.get(folder.name) ?? new Map(saved.get(folder.name)?.codes))
  }))
);
const ready = computed(() => rows.value.length > 0 && rows.value.every((row) => !row.plan.problems.length));
function code(name: string, folder: string, value: string) {
  const row = rows.value.find((r) => r.folder.name === name)!;
  const mappings = new Map(row.plan.folders.filter((f) => f.code !== null).map((f) => [f.folder, f.code!]));
  mappings.set(folder, value.trim().toUpperCase());
  overrides.set(name, mappings);
  unavailable.value = false;
  problem.value = '';
}
function* portions(plans: { id: string; plan: ArchivePlan }[]) {
  for (const group of plans) for (const plan of archivePortions(group.plan, 200)) yield { id: group.id, plan };
}
function advance() {
  if (!batches || q.busy.value || q.waiting.value || q.queued.value || q.paused.value) return;
  for (const job of q.jobs.value) {
    if (job.status === 'done' || job.status === 'duplicate') records.get(job.groupId)!.done++;
  }
  q.clear();
  let next = batches.next();
  while (!next.done) {
    const batch = next.value;
    const skipped = q.appendPortion(batch.plan, props.chosen.file, props.chosen.source, batch.id);
    records.get(batch.id)!.done += skipped;
    if (q.queued.value) {
      void q.start();
      return;
    }
    next = batches.next();
  }
  batches = undefined;
  finished.value = true;
  emit('working', false);
  emit('changed');
}
watch(
  [q.busy, q.waiting, q.queued, q.paused],
  () => {
    advance();
    if (started.value) emit('working', !finished.value || q.busy.value || !!q.waiting.value);
  },
  { flush: 'post' }
);
async function start(restart = false) {
  if (!ready.value || sending.value || started.value) return;
  if (restart && !unavailable.value) return;
  sending.value = true;
  emit('working', true);
  problem.value = '';
  try {
    const checked = rows.value;
    const choices: ArchiveClassChoice[] = checked.map(({ folder, plan }) => ({
      name: folder.name,
      codes: plan.folders.filter((series) => series.code !== null).map((series) => [series.folder, series.code!])
    }));
    try {
      localStorage.setItem(storageKey, JSON.stringify(choices));
    } catch {
      /* Привязку групп к импорту сохраняет сервер. */
    }
    const base = await archiveImportKey(props.shoot.id, props.chosen.source.key, props.root, choices);
    const key = restart ? await attempt.restart(base) : attempt.current(base);
    unavailable.value = false;
    const resolved = await confirmImportGroups(
      props.institutionId,
      props.shoot.id,
      choices.map((choice) => choice.name),
      key,
      controller.signal
    );
    if (controller.signal.aborted) return;
    const plans = checked.map((row, index) => {
      const id = resolved[index]!;
      records.set(id, {
        name: row.folder.name,
        total: row.plan.files,
        skipped: row.folder.skipped,
        done: 0
      });
      return { id, plan: row.plan };
    });
    started.value = true;
    emit('changed');
    batches = portions(plans);
    advance();
  } catch (cause) {
    if (!controller.signal.aborted) {
      unavailable.value = importGroupsUnavailable(cause);
      problem.value = unavailable.value
        ? 'Часть групп предыдущего импорта больше недоступна. Продолжить его нельзя.'
        : structureError(cause);
      emit('working', false);
    }
  } finally {
    sending.value = false;
  }
}
const summary = computed(() => {
  const result = new Map(Array.from(records, ([id, row]) => [id, { ...row, accepted: row.done, ready: row.done, errors: 0 }]));
  for (const job of q.jobs.value) {
    const row = result.get(job.groupId);
    if (!row) continue;
    if (['done', 'duplicate', 'processing'].includes(job.status)) row.accepted++;
    if (['done', 'duplicate'].includes(job.status)) row.ready++;
    if (['error', 'interrupted'].includes(job.status)) row.errors++;
  }
  return [...result.values()];
});
const total = computed(() =>
  summary.value.reduce(
    (sum, row) => ({
      files: sum.files + row.total,
      accepted: sum.accepted + row.accepted,
      errors: sum.errors + row.errors,
      ready: sum.ready + row.ready
    }),
    { files: 0, ready: 0, accepted: 0, errors: 0 }
  )
);
const failures = computed(() => q.jobs.value.filter((j) => j.status === 'error' || j.status === 'interrupted'));
onScopeDispose(() => controller.abort());
</script>

<template>
  <section aria-label="Сверка и загрузка архива">
    <details v-if="archive.skipped.length" class="my-3">
      <summary>Пропущено записей: {{ archive.skipped.length }}</summary>
      <ul>
        <li v-for="(item, index) in archive.skipped" :key="index">{{ item.path }} — {{ item.reason }}</li>
      </ul>
    </details>
    <template v-if="!started">
      <p v-if="!rows.length" role="status">В архиве нет фотографий в папках групп и серий.</p>
      <p v-if="rows.length" class="my-3">
        Каждая папка создаст новую группу в выбранной съёмке. Тот же архив с теми же кодами серий можно загрузить повторно, чтобы продолжить
        с места остановки.
      </p>
      <div v-for="row in rows" :key="row.folder.name" class="import-row">
        <strong>{{ row.folder.name }}</strong> · {{ row.plan.files }} фото<span v-if="row.folder.skipped">
          · пропущено {{ row.folder.skipped }}</span
        >
        <details>
          <summary>Серии и групповые кадры · {{ row.plan.folders.length }}</summary>
          <div v-for="series in row.plan.folders" :key="series.folder" class="series-row">
            <details @toggle="showFiles(row.folder.name + '/' + series.folder, $event)">
              <summary>{{ series.folder }} · {{ series.files.length }} фото</summary>
              <ul v-if="openSeries.has(row.folder.name + '/' + series.folder)" class="series-files">
                <li v-for="file in series.files" :key="file.path">{{ file.name }}</li>
              </ul>
            </details>
            <label v-if="series.kind === 'child'"
              >Код серии
              <input
                :value="series.code"
                :aria-label="'Код серии ' + series.folder + ' в ' + row.folder.name"
                maxlength="3"
                :disabled="sending"
                @input="code(row.folder.name, series.folder, ($event.target as HTMLInputElement).value)"
              />
            </label>
            <span v-else>Для всех серий группы</span>
          </div>
        </details>
        <p v-for="issue in new Set(row.plan.problems)" :key="issue" role="alert">
          {{ issue }}
        </p>
      </div>
      <template v-if="unavailable">
        <p class="my-3">
          Новый импорт создаст новые группы для всех папок. Существующие группы сохранятся; фотографии, которые уже есть в них, будут
          показаны как совпадения с другим классом.
        </p>
        <v-btn color="primary" :disabled="!ready" :loading="sending" @click="start(true)">Начать новый импорт</v-btn>
      </template>
      <v-btn v-else color="primary" :disabled="!ready" :loading="sending" @click="start()">Подтвердить и загрузить</v-btn>
    </template>
    <template v-else>
      <h3>
        {{ finished && !q.busy.value && !q.waiting.value ? 'Результат загрузки' : 'Загрузка групп' }}
      </h3>
      <p role="status">
        Превью готовы: {{ total.ready }} из {{ total.files }} · принято {{ total.accepted }}, требуют действия {{ total.errors }}, пропущено
        {{ archive.skipped.length }}
      </p>
      <v-progress-linear :model-value="(100 * total.ready) / Math.max(1, total.files)" aria-label="Готовность фотографий" />
      <p v-for="group in summary" :key="group.name" class="import-row">
        <strong>{{ group.name }}</strong> · принято {{ group.accepted }} из {{ group.total }}, готовы {{ group.ready }}, требуют действия
        {{ group.errors }}, пропущено {{ group.skipped }}
      </p>
      <v-btn v-if="q.paused.value" @click="q.start">Продолжить</v-btn>
      <v-btn v-else-if="q.busy.value" @click="q.pause">Приостановить</v-btn>
      <ul v-if="failures.length">
        <li v-for="job in failures" :key="job.id">
          {{ job.entry }} — {{ job.message }}
          <v-btn size="small" :disabled="q.busy.value" @click="q.retry(job.id)">Повторить</v-btn>
        </li>
      </ul>
      <p v-if="q.error.value" role="alert">{{ q.error.value }}</p>
    </template>
    <v-alert v-if="problem" type="error" variant="tonal">{{ problem }}</v-alert>
  </section>
</template>

<style scoped>
.import-row {
  border-top: 1px solid var(--mf-color-border);
  padding-block: 12px;
  overflow-wrap: anywhere;
}
.series-row {
  display: flex;
  align-items: baseline;
  flex-wrap: wrap;
  gap: 12px;
  padding: 6px 0;
}
.series-files {
  max-height: 12rem;
  overflow: auto;
  padding-inline-start: 1.5rem;
}
.series-row input {
  border: 1px solid currentcolor;
  border-radius: 4px;
  width: 5em;
  padding-inline: 6px;
}
</style>
