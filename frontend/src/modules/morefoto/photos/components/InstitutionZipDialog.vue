<script setup lang="ts">
import { computed, onMounted, onScopeDispose, shallowRef } from 'vue';
import { structureApi, structureError } from '../../structure/api';
import type { Shoot } from '../../structure/model';
import { archiveIdentity, archiveRoots, inspectInstitutionArchive } from '../institution-archive';
import { readZipEntries } from '../zip-reader';
import type { ArchiveSource } from '../archive';
import InstitutionArchiveImport from './InstitutionArchiveImport.vue';

const props = defineProps<{ institutionId: string; institutionName: string }>();
const emit = defineEmits<{ close: []; createShoot: []; changed: [] }>();
const shoots = shallowRef<Shoot[]>([]);
const shootId = shallowRef('');
const fileInput = shallowRef<HTMLInputElement | null>(null);
const chosen = shallowRef<{ file: File; source: ArchiveSource } | null>(null);
const root = shallowRef('');
const loading = shallowRef(false);
const reading = shallowRef(false);
const locked = shallowRef(false);
const error = shallowRef('');
let read: AbortController | undefined;
let alive = true;
const shoot = computed(() => shoots.value.find((item) => item.id === shootId.value));
const roots = computed(() => archiveRoots(chosen.value?.source.entries ?? []));
const archive = computed(() => chosen.value && inspectInstitutionArchive(chosen.value.source.entries, chosen.value.source.key, root.value));
async function load() {
  loading.value = true;
  error.value = '';
  try {
    const items = await structureApi.shoots(props.institutionId);
    if (!alive) return;
    shoots.value = items;
    if (items.length === 1) shootId.value = items[0]!.id;
  } catch (cause) {
    error.value = structureError(cause);
  } finally {
    loading.value = false;
  }
}
async function select(event: Event) {
  const element = event.target as HTMLInputElement;
  const file = element.files?.[0];
  element.value = '';
  read?.abort();
  const controller = new AbortController();
  read = controller;
  chosen.value = null;
  reading.value = false;
  error.value = '';
  if (!file) return;
  reading.value = true;
  try {
    const entries = await readZipEntries(file);
    const key = await archiveIdentity(file, controller.signal);
    if (controller.signal.aborted) return;
    root.value = archiveRoots(entries).initial;
    chosen.value = { file, source: { name: file.name, entries, key } };
  } catch (cause) {
    if (!controller.signal.aborted) error.value = cause instanceof Error ? cause.message : 'Не удалось прочитать ZIP.';
  } finally {
    if (!controller.signal.aborted) reading.value = false;
  }
}
function close() {
  if (!locked.value || window.confirm('Остановить загрузку? Принятые кадры сохранятся.')) emit('close');
}
onMounted(load);
onScopeDispose(() => {
  alive = false;
  read?.abort();
});
</script>

<template>
  <v-dialog :model-value="true" max-width="900" persistent aria-labelledby="institution-zip-title" scrollable>
    <v-card class="pa-5">
      <h2 id="institution-zip-title">Загрузить ZIP по группам</h2>
      <p class="mf-muted">{{ institutionName }}</p>
      <v-card-text class="px-0">
        <v-progress-linear v-if="loading" indeterminate aria-label="Загрузка съёмок" />
        <v-alert v-if="error" type="error" variant="tonal" role="alert"
          >{{ error }} <v-btn v-if="!shoots.length" variant="text" @click="load">Повторить</v-btn></v-alert
        >
        <template v-if="!loading && shoots.length">
          <v-select
            v-model="shootId"
            label="Съёмка"
            aria-label="Съёмка"
            :disabled="locked"
            :items="
              shoots.map((s) => ({
                title: s.name + (s.date ? ' · ' + s.date.split('-').reverse().join('.') : ''),
                value: s.id
              }))
            "
          />
          <template v-if="shoot">
            <p id="institution-zip-format">ZIP с папками «Группа / Серия / Фото»</p>
            <div class="d-flex flex-wrap align-center ga-2 my-2">
              <v-btn variant="outlined" :disabled="locked" aria-describedby="institution-zip-format" @click="fileInput?.click()">
                {{ chosen ? 'Выбрать другой ZIP' : 'Выбрать ZIP' }}
              </v-btn>
              <input ref="fileInput" type="file" accept=".zip" aria-label="Выбрать ZIP" hidden :disabled="locked" @change="select" />
              <span v-if="chosen">{{ chosen.file.name }}</span>
            </div>
            <p v-if="reading" role="status">Читаем архив…</p>
            <v-select
              v-if="roots.wrapper"
              v-model="root"
              label="Где находятся папки групп"
              aria-label="Где находятся папки групп"
              :disabled="locked"
              :items="[
                { title: 'В корне ZIP', value: '' },
                { title: roots.wrapper, value: roots.wrapper }
              ]"
            />
            <InstitutionArchiveImport
              v-if="chosen && archive"
              :key="JSON.stringify([shoot.id, chosen.source.key, root])"
              :institution-id="institutionId"
              :shoot="shoot"
              :root="root"
              :archive="archive"
              :chosen="chosen"
              @working="locked = $event"
              @changed="$emit('changed')"
            />
          </template>
        </template>
        <div v-else-if="!loading && !error">
          <p>У учреждения пока нет съёмок.</p>
          <v-btn @click="$emit('createShoot')">Новая съёмка</v-btn>
        </div>
      </v-card-text>
      <v-card-actions><v-spacer /><v-btn @click="close">Закрыть</v-btn></v-card-actions>
    </v-card>
  </v-dialog>
</template>
