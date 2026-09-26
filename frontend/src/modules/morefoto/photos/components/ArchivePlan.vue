<script setup lang="ts">
import { computed } from 'vue';
import { plural } from '@/components/viz/measures';
import { fileSize } from '../../orders/live/files-rules';
import type { ArchivePlan } from '../archive';

const props = defineProps<{
  plan: ArchivePlan;
  archives: string[];
  marks: ReadonlySet<string>;
  sessionShort: boolean;
  disabled: boolean;
}>();
defineEmits<{ toggle: [folder: string]; start: []; reset: [] }>();
const children = computed(() => props.plan.folders.filter((folder) => folder.kind === 'child' && !folder.problem));
const groupFiles = computed(() =>
  props.plan.folders.filter((folder) => folder.kind === 'group').reduce((sum, folder) => sum + folder.files.length, 0)
);
const groupFolders = computed(() => props.plan.folders.filter((folder) => folder.kind === 'group').length);
const startable = computed(() => !props.plan.problems.length && props.plan.files > 0);
const byName = (folder: string) => /^group/i.test(folder);
</script>

<template>
  <section class="archive-plan" aria-labelledby="archive-plan-heading" data-testid="archive-plan">
    <h3 id="archive-plan-heading">Проверьте архивы перед загрузкой</h3>
    <p class="mt-2" data-testid="archive-summary">
      {{ archives.length }} {{ plural(archives.length, ['архив', 'архива', 'архивов']) }} · {{ plan.files }} фото ·
      {{ fileSize(plan.bytes) }} · детей: {{ children.length
      }}<template v-if="groupFolders">
        · групповых папок: {{ groupFolders }} ({{ groupFiles }} фото, получат детей: {{ plan.groupCodes.length }})</template
      >
    </p>
    <p class="mf-muted mt-1">{{ archives.join(', ') }}</p>
    <v-alert v-if="plan.problems.length" type="error" variant="tonal" role="alert" class="mt-4">
      <p v-for="problem in plan.problems" :key="problem">{{ problem }}</p>
    </v-alert>
    <v-alert v-if="sessionShort" type="warning" variant="tonal" class="mt-4">
      Сессия закончится раньше, чем загрузка успеет завершиться. Выйдите и войдите снова перед стартом — продлить сессию во время загрузки
      нельзя.
    </v-alert>
    <div class="archive-plan__table mt-4">
      <table>
        <caption class="mf-sr-only">
          Папки архивов
        </caption>
        <thead>
          <tr>
            <th scope="col">Папка</th>
            <th scope="col">Кому</th>
            <th scope="col" class="archive-plan__number">Фото</th>
            <th scope="col">Групповые</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="folder in plan.folders" :key="folder.kind + folder.folder" :data-folder="folder.folder">
            <th scope="row">{{ folder.folder }}</th>
            <td>
              <span v-if="folder.problem" class="text-error">{{ folder.problem }}</span>
              <span v-else-if="folder.kind === 'group'">Всем детям группы</span>
              <span v-else
                >Ребёнок {{ folder.code }}<span v-if="folder.existing" class="mf-muted"> · уже есть в группе, фото добавятся</span></span
              >
            </td>
            <td class="archive-plan__number">{{ folder.files.length }}</td>
            <td>
              <v-switch
                :model-value="folder.kind === 'group'"
                :disabled="disabled || byName(folder.folder)"
                :aria-label="'Групповые кадры: папка ' + folder.folder"
                color="primary"
                density="compact"
                hide-details
                inset
                @update:model-value="$emit('toggle', folder.folder)"
              />
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <details v-if="plan.skipped.length" class="archive-plan__skipped mt-4">
      <summary>Не будут загружены: {{ plan.skipped.length }}</summary>
      <ul>
        <li v-for="item in plan.skipped" :key="item.archive + item.path">{{ item.path }} — {{ item.reason }}</li>
      </ul>
    </details>
    <div class="mf-actions mt-5">
      <v-btn color="primary" :disabled="disabled || !startable" data-testid="archive-start" @click="$emit('start')"
        >Загрузить {{ plan.files }} фото</v-btn
      ><v-btn variant="outlined" :disabled="disabled" @click="$emit('reset')">Выбрать другие архивы</v-btn>
    </div>
  </section>
</template>

<style scoped>
.archive-plan {
  margin-top: var(--mf-space-5);
}
.archive-plan__table {
  max-height: 420px;
  overflow: auto;
  border: 1px solid var(--mf-color-border);
  border-radius: var(--mf-radius-md);
}
.archive-plan table {
  width: 100%;
  border-collapse: collapse;
  font-size: var(--mf-text-small);
}
.archive-plan th,
.archive-plan td {
  padding: 8px 12px;
  border-top: 1px solid var(--mf-color-border);
  text-align: left;
  vertical-align: middle;
  overflow-wrap: anywhere;
}
.archive-plan thead th {
  position: sticky;
  top: 0;
  z-index: 1;
  border-top: 0;
  background: var(--mf-color-surface-2);
  white-space: nowrap;
  overflow-wrap: normal;
}
.archive-plan__number {
  text-align: right;
  white-space: nowrap;
}
@media (max-width: 599px) {
  .archive-plan th,
  .archive-plan td {
    padding: 8px 6px;
  }
}
.archive-plan__skipped summary {
  cursor: pointer;
  min-height: 44px;
  padding: 12px 0;
  color: rgb(var(--v-theme-primary));
}
.archive-plan__skipped ul {
  padding-left: 20px;
  font-size: var(--mf-text-small);
  overflow-wrap: anywhere;
}
</style>
