<script setup lang="ts">
import { computed, shallowRef } from 'vue';
import UiDataTable from '../../ui/components/UiDataTable.vue';
import type { UiTableColumn, UiTableRow, UiTableSort } from '../../ui/table-types';
import { sortTableRows } from '../../ui/table-values';
import MfStatus from '@/components/status/MfStatus.vue';
import type { Shoot } from '../model';
/** Shoots of every institution in the staff member's area as one table (UI-DEC-03); newest first by default. */
const props = defineProps<{
  shoots: Shoot[];
  total: number;
  page: number;
  pageSize: number;
  loading: boolean;
  error: string;
  /** The organizer opens the shoot itself and its photos; other roles open the institution. */
  canManage: boolean;
  emptyDescription: string;
}>();
const emit = defineEmits<{ page: [page: number]; pageSize: [size: number]; retry: [] }>();
const sort = shallowRef<UiTableSort>({ key: 'date', direction: 'desc' });
const columns: UiTableColumn[] = [
  { key: 'name', label: 'Съёмка', sortable: true, primary: true, width: '26%' },
  { key: 'institution', label: 'Учреждение', sortable: true, mobile: true, width: '30%' },
  { key: 'date', label: 'Дата', type: 'date', sortable: true, mobile: true, width: '13%' },
  { key: 'groups', label: 'Групп', type: 'number', sortable: true, mobile: true, width: '10%' },
  { key: 'open', label: 'Приём открыт', type: 'number', sortable: true, mobile: true }
];
const byId = computed(() => new Map(props.shoots.map((shoot) => [shoot.id, shoot])));
const rows = computed<UiTableRow[]>(() =>
  sortTableRows(
    props.shoots.map((shoot) => ({
      id: shoot.id,
      name: shoot.name,
      institution: shoot.institutionName ?? '',
      date: shoot.date,
      groups: shoot.groupCount ?? null,
      open: shoot.openGroupCount ?? 0
    })),
    columns,
    sort.value
  )
);
const shoot = (id: string) => byId.value.get(id)!;
const institutionPath = (id: string) => '/cabinet/institutions/' + encodeURIComponent(shoot(id).institutionId);
const shootPath = (id: string, tail = '') => institutionPath(id) + '/shoots/' + encodeURIComponent(id) + tail;
const date = new Intl.DateTimeFormat('ru-RU', { day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC' });
function shootDate(value: unknown): string {
  return typeof value === 'string' && value ? date.format(new Date(value + 'T00:00:00Z')) : 'Не назначена';
}
</script>
<template>
  <UiDataTable
    title="Съёмки"
    label-key="name"
    density="compact"
    actions-width="88px"
    :columns="columns"
    :rows="rows"
    :total="total"
    :page="page"
    :page-size="pageSize"
    :sort="sort"
    :selected="[]"
    :selectable="false"
    :actions="canManage"
    :loading="loading"
    :error="error"
    empty-title="Съёмок пока нет"
    :empty-description="emptyDescription"
    @sort="sort = $event"
    @page="emit('page', $event)"
    @page-size="emit('pageSize', $event)"
    @retry="emit('retry')"
  >
    <template #cell-name="{ row }">
      <RouterLink :to="canManage ? shootPath(row.id) : institutionPath(row.id)" class="shoot-link">{{ row.name }}</RouterLink>
    </template>
    <template #cell-institution="{ row }">
      <RouterLink :to="institutionPath(row.id)" class="shoot-institution" :title="String(row.institution)">{{
        row.institution
      }}</RouterLink>
    </template>
    <template #cell-date="{ row }">{{ shootDate(row.date) }}</template>
    <template #cell-open="{ row }">
      <MfStatus v-if="Number(row.open) > 0" tone="success">{{ row.open }}</MfStatus>
      <span v-else class="shoot-none">—</span>
    </template>
    <template #actions="{ row }">
      <v-btn
        icon="mdi-image-multiple-outline"
        variant="text"
        density="compact"
        :to="shootPath(row.id, '/photos')"
        :aria-label="'Фотографии съёмки «' + row.name + '»'"
      />
    </template>
  </UiDataTable>
</template>
<style scoped>
.shoot-link {
  color: var(--mf-color-primary);
  font-weight: 600;
  text-decoration: none;
}
.shoot-institution {
  display: block;
  overflow: hidden;
  color: inherit;
  text-decoration: none;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.shoot-link:hover,
.shoot-link:focus-visible,
.shoot-institution:hover,
.shoot-institution:focus-visible {
  text-decoration: underline;
}
.shoot-none {
  color: var(--mf-color-text-tertiary);
}
</style>
