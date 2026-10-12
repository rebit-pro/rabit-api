<script setup lang="ts">
import { computed } from 'vue';
import UiDataTable from '../../ui/components/UiDataTable.vue';
import type { UiTableColumn, UiTableRow, UiTableSort } from '../../ui/table-types';
import type { HandoffWorkspace, StaffRequest } from '../types';
import { requestStatus } from '../display';
import { tableMoment } from '../../ui/table-values';
import MfStatus from '@/components/status/MfStatus.vue';
import { toneOf } from '@/components/status/tones';
import { staffRequestTone } from '../../ui/statusTone';
/** Staff lists of one server page; sorting asks the server, a transferred list is never removed (#92 DEC-01). */
const props = defineProps<{
  data: HandoffWorkspace;
  requests: StaffRequest[];
  total: number;
  page: number;
  pageSize: number;
  sort: UiTableSort;
  loading: boolean;
  actorId: number | null;
}>();
const emit = defineEmits<{
  sort: [sort: UiTableSort];
  page: [page: number];
  pageSize: [size: number];
  edit: [request: StaffRequest];
  remove: [requests: StaffRequest[]];
}>();
const selected = defineModel<string[]>('selected', { default: () => [] });
const columns: UiTableColumn[] = [
  { key: 'place', label: 'Съёмка и учреждение', primary: true, width: '26%' },
  { key: 'createdByName', label: 'Автор', sortable: true, mobile: true, width: '17%' },
  { key: 'children', label: 'Детей', type: 'number', width: '8%' },
  { key: 'status', label: 'Статус', sortable: true, mobile: true, width: '16%' },
  { key: 'createdAt', label: 'Передан', sortable: true, width: '16%' },
  { key: 'updatedAt', label: 'Изменён', sortable: true, mobile: true, width: '16%' }
];
const byId = computed(() => new Map(props.requests.map((request) => [request.id, request])));
const institutions = computed(() => new Map(props.data.scope.institutions.map((item) => [item.id, item.name])));
const shoots = computed(() => new Map(props.data.scope.shoots.map((item) => [item.id, item.name])));
const rows = computed<UiTableRow[]>(() =>
  props.requests.map((request) => ({
    id: request.id,
    place: shoots.value.get(request.shootId) ?? '',
    createdByName: request.createdByName ?? 'Сотрудник №' + request.createdBy,
    children: request.rows.length,
    status: request.status,
    createdAt: request.createdAt,
    updatedAt: request.history[request.history.length - 1]?.at ?? request.createdAt
  }))
);
const request = (id: string) => byId.value.get(id)!;
const own = (id: string) => request(id).createdBy === props.actorId;
/** The author or the organizer removes a list until its sets are moved; the server repeats the check. */
function removable(id: string): boolean {
  return request(id).status !== 'transferred' && (own(id) || props.data.role === 'organizer');
}
const writable = computed(() => props.data.role !== 'head');
</script>
<template>
  <UiDataTable
    title="Списки сотрудников"
    label-key="place"
    density="compact"
    actions-width="120px"
    :columns="columns"
    :rows="rows"
    :total="total"
    :page="page"
    :page-size="pageSize"
    :sort="sort"
    :selected="selected"
    :selectable="writable"
    :loading="loading"
    empty-title="Списков пока нет"
    empty-description="Здесь появятся списки детей сотрудников."
    @sort="emit('sort', $event)"
    @page="emit('page', $event)"
    @page-size="emit('pageSize', $event)"
    @select="selected = $event"
  >
    <template #selection>
      <div class="request-bulk" data-testid="request-bulk">
        <p>Выбрано: {{ selected.length }}</p>
        <v-btn
          color="error"
          variant="outlined"
          density="compact"
          prepend-icon="mdi-delete-outline"
          @click="emit('remove', selected.filter((id) => byId.has(id)).map(request))"
          >Удалить</v-btn
        >
        <v-btn variant="text" density="compact" @click="selected = []">Снять выбор</v-btn>
      </div>
    </template>
    <template #cell-place="{ row }">
      <span class="request-cell">
        <RouterLink :to="'/cabinet/staff-requests/' + row.id" class="request-link request-clip">{{ row.place }}</RouterLink>
        <small class="request-clip">{{ institutions.get(request(row.id).institutionId) }}</small>
      </span>
    </template>
    <template #cell-status="{ row }">
      <MfStatus :tone="toneOf(staffRequestTone, String(row.status))">{{ requestStatus[request(row.id).status] }}</MfStatus>
    </template>
    <template #cell-createdAt="{ row }"
      ><span class="request-moment">{{ tableMoment(String(row.createdAt)) }}</span></template
    >
    <template #cell-updatedAt="{ row }"
      ><span class="request-moment">{{ tableMoment(String(row.updatedAt)) }}</span></template
    >
    <template #actions="{ row }">
      <div class="request-actions">
        <v-btn
          icon="mdi-open-in-new"
          variant="text"
          density="compact"
          :to="'/cabinet/staff-requests/' + row.id"
          :aria-label="'Открыть список ' + row.place"
        />
        <v-btn
          v-if="writable"
          icon="mdi-pencil-outline"
          variant="text"
          density="compact"
          :disabled="!own(row.id) || request(row.id).status === 'transferred'"
          :aria-label="'Изменить список ' + row.place"
          @click="emit('edit', request(row.id))"
        />
        <v-btn
          v-if="writable"
          icon="mdi-delete-outline"
          variant="text"
          density="compact"
          color="error"
          :disabled="!removable(row.id)"
          :aria-label="removable(row.id) ? 'Удалить список ' + row.place : 'Перенесённый список ' + row.place + ' не удаляется'"
          @click="emit('remove', [request(row.id)])"
        />
      </div>
    </template>
  </UiDataTable>
</template>
<style scoped>
.request-cell {
  display: grid;
  min-width: 0;
}
.request-cell small {
  color: var(--mf-color-text-secondary);
  font-size: var(--mf-text-sm);
  font-weight: 400;
}
.request-link {
  color: var(--mf-color-primary);
  font-weight: 600;
  text-decoration: none;
}
.request-link:hover,
.request-link:focus-visible {
  text-decoration: underline;
}
.request-moment {
  white-space: nowrap;
}
.request-clip {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.request-actions,
.request-bulk {
  display: flex;
  align-items: center;
  gap: var(--mf-space-1);
}
.request-bulk {
  flex-wrap: wrap;
  gap: var(--mf-space-2);
}
</style>
