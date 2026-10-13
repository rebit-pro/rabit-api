<script setup lang="ts">
import { computed, shallowRef } from 'vue';
import UiDataTable from '../../ui/components/UiDataTable.vue';
import type { UiTableColumn, UiTableRow, UiTableSort } from '../../ui/table-types';
import { sortTableRows } from '../../ui/table-values';
import MfStatus from '@/components/status/MfStatus.vue';
import type { Institution } from '../model';
/** One server page of institutions as a table (#92, UI-DEC-02); the order inside the page is local until the API sorts. */
const props = defineProps<{
  institutions: Institution[];
  total: number;
  page: number;
  pageSize: number;
  loading: boolean;
  error: string;
  canManage: boolean;
  disabled: boolean;
  emptyDescription: string;
}>();
const emit = defineEmits<{
  page: [page: number];
  pageSize: [size: number];
  edit: [institution: Institution];
  remove: [institution: Institution];
  retry: [];
}>();
const sort = shallowRef<UiTableSort>({ key: 'name', direction: 'asc' });
const columns: UiTableColumn[] = [
  { key: 'name', label: 'Учреждение', sortable: true, primary: true, width: '34%' },
  { key: 'shoots', label: 'Съёмок', type: 'number', sortable: true, mobile: true, width: '10%' },
  { key: 'groups', label: 'Групп', type: 'number', sortable: true, mobile: true, width: '10%' },
  { key: 'open', label: 'Приём открыт', type: 'number', sortable: true, mobile: true, width: '16%' },
  { key: 'curator', label: 'Куратор', sortable: true, mobile: true }
];
const byId = computed(() => new Map(props.institutions.map((item) => [item.id, item])));
const rows = computed<UiTableRow[]>(() =>
  sortTableRows(
    props.institutions.map((item) => ({
      id: item.id,
      name: item.name,
      shoots: item.shootCount ?? null,
      groups: item.groupCount ?? null,
      open: item.openGroupCount ?? 0,
      curator: item.curatorName
    })),
    columns,
    sort.value
  )
);
const institution = (id: string) => byId.value.get(id)!;
const path = (id: string) => '/cabinet/institutions/' + encodeURIComponent(id);
</script>
<template>
  <UiDataTable
    title="Учреждения"
    label-key="name"
    density="compact"
    actions-width="96px"
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
    empty-title="Учреждений пока нет"
    :empty-description="emptyDescription"
    @sort="sort = $event"
    @page="emit('page', $event)"
    @page-size="emit('pageSize', $event)"
    @retry="emit('retry')"
  >
    <template #cell-name="{ row }">
      <span class="institution-cell">
        <RouterLink :to="path(row.id)" class="institution-name">{{ row.name }}</RouterLink>
        <small>{{ institution(row.id).address || 'Адрес не указан' }}</small>
      </span>
    </template>
    <template #cell-open="{ row }">
      <MfStatus v-if="Number(row.open) > 0" tone="success">{{ row.open }}</MfStatus>
      <span v-else class="institution-none">—</span>
    </template>
    <template #cell-curator="{ row }">
      <span v-if="row.curator" class="institution-clip" :title="String(row.curator)">{{ row.curator }}</span>
      <span v-else class="institution-none">Не назначен</span>
    </template>
    <template #actions="{ row }">
      <div class="institution-actions">
        <v-btn
          icon="mdi-pencil-outline"
          variant="text"
          density="compact"
          :disabled="disabled"
          :aria-label="'Редактировать «' + row.name + '»'"
          @click="emit('edit', institution(row.id))"
        />
        <v-btn
          icon="mdi-delete-outline"
          variant="text"
          density="compact"
          color="error"
          :disabled="disabled"
          :aria-label="'Удалить «' + row.name + '»'"
          @click="emit('remove', institution(row.id))"
        />
      </div>
    </template>
  </UiDataTable>
</template>
<style scoped>
.institution-cell {
  display: grid;
  min-width: 0;
}
.institution-cell small {
  overflow: hidden;
  color: var(--mf-color-text-secondary);
  font-size: var(--mf-text-sm);
  font-weight: 400;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.institution-name {
  color: var(--mf-color-primary);
  font-weight: 600;
  text-decoration: none;
}
.institution-name:hover,
.institution-name:focus-visible {
  text-decoration: underline;
}
.institution-clip {
  display: block;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.institution-none {
  color: var(--mf-color-text-tertiary);
}
.institution-actions {
  display: flex;
  align-items: center;
  gap: var(--mf-space-1);
}
</style>
