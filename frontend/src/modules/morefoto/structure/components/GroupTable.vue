<script setup lang="ts">
import { computed, shallowRef } from 'vue';
import UiDataTable from '../../ui/components/UiDataTable.vue';
import type { UiTableColumn, UiTableRow, UiTableSort } from '../../ui/table-types';
import { sortTableRows } from '../../ui/table-values';
import MfStatus from '@/components/status/MfStatus.vue';
import { toneOf } from '@/components/status/tones';
import { countdown } from '@/components/viz/measures';
import { groupStateTone } from '../../ui/statusTone';
import type { Group } from '../model';
/** Groups of one shoot as a table (#92, UI-DEC-02): state of ordering, the three deadlines and the responsible person. */
const props = defineProps<{
  groups: Group[];
  total: number;
  page: number;
  pageSize: number;
  loading: boolean;
  error: string;
  canManage: boolean;
  disabled: boolean;
  /** Moment the countdowns count from; the structure answer carries no server clock. */
  now: string;
  emptyDescription: string;
}>();
const emit = defineEmits<{ page: [page: number]; pageSize: [size: number]; edit: [group: Group]; retry: [] }>();
const sort = shallowRef<UiTableSort>({ key: 'name', direction: 'asc' });
const statuses = {
  preparing: { label: 'Готовится', tone: 'neutral' },
  open: { label: 'Приём открыт', tone: 'success' },
  digital: { label: 'Только электронные', tone: 'info' },
  closed: { label: 'Приём завершён', tone: 'neutral' }
} as const;
const columns: UiTableColumn[] = [
  { key: 'name', label: 'Группа', sortable: true, primary: true, width: '16%' },
  { key: 'status', label: 'Приём', type: 'status', statuses, sortable: true, mobile: true, width: '20%' },
  { key: 'closesAt', label: 'Печать до', type: 'date', sortable: true, mobile: true, width: '12%' },
  { key: 'digitalSalesUntil', label: 'Электронные до', type: 'date', sortable: true, width: '13%' },
  { key: 'deliveryDueAt', label: 'Доставка до', type: 'date', sortable: true, width: '12%' },
  { key: 'teacher', label: 'Ответственный', sortable: true }
];
const byId = computed(() => new Map(props.groups.map((group) => [group.id, group])));
const rows = computed<UiTableRow[]>(() =>
  sortTableRows(
    props.groups.map((group) => ({
      id: group.id,
      name: group.name,
      status: group.status,
      closesAt: group.closesAt,
      digitalSalesUntil: group.digitalSalesUntil,
      deliveryDueAt: group.deliveryDueAt,
      teacher: group.teacherId === null ? 'Не назначен' : 'Назначен'
    })),
    columns,
    sort.value
  )
);
const group = (id: string) => byId.value.get(id)!;
const deadline = new Intl.DateTimeFormat('ru-RU', {
  day: 'numeric',
  month: 'short',
  hour: '2-digit',
  minute: '2-digit',
  timeZone: 'Europe/Moscow'
});
function moment(value: unknown): string {
  return typeof value === 'string' && value ? deadline.format(new Date(value)) : '—';
}
/** Countdown of the window that is running now: print while open, digital after the print closes. */
function left(id: string): string {
  const item = group(id);
  if (item.status === 'open') return countdown(item.sentAt, item.closesAt, props.now).label;
  if (item.status === 'digital') return 'электронные: ' + countdown(item.sentAt, item.digitalSalesUntil, props.now).label;
  return '';
}
</script>
<template>
  <UiDataTable
    title="Группы"
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
    empty-title="Групп пока нет"
    :empty-description="emptyDescription"
    @sort="sort = $event"
    @page="emit('page', $event)"
    @page-size="emit('pageSize', $event)"
    @retry="emit('retry')"
  >
    <template #cell-name="{ row }">
      <span class="group-cell">
        <span class="group-name">{{ row.name }}</span>
        <small v-if="group(row.id).groupKind === 'staff'">Группа сотрудников</small>
      </span>
    </template>
    <template #cell-status="{ row }">
      <span class="group-cell">
        <MfStatus :tone="toneOf(groupStateTone, String(row.status))">{{ statuses[group(row.id).status].label }}</MfStatus>
        <small v-if="left(row.id)">{{ left(row.id) }}</small>
      </span>
    </template>
    <template #cell-closesAt="{ row }">{{ moment(row.closesAt) }}</template>
    <template #cell-digitalSalesUntil="{ row }">{{ moment(row.digitalSalesUntil) }}</template>
    <template #cell-deliveryDueAt="{ row }">{{ moment(row.deliveryDueAt) }}</template>
    <template #cell-teacher="{ row }">
      <span :class="group(row.id).teacherId === null ? 'group-missing' : 'group-assigned'">
        <v-icon :icon="group(row.id).teacherId === null ? 'mdi-account-alert-outline' : 'mdi-account-check-outline'" size="18" />
        {{ row.teacher }}
      </span>
    </template>
    <template #actions="{ row }">
      <v-btn
        icon="mdi-pencil-outline"
        variant="text"
        density="compact"
        :disabled="disabled"
        :aria-label="'Редактировать «' + row.name + '»'"
        @click="emit('edit', group(row.id))"
      />
    </template>
  </UiDataTable>
</template>
<style scoped>
.group-cell {
  display: grid;
  justify-items: start;
  gap: 2px;
  min-width: 0;
}
.group-cell small {
  color: var(--mf-color-text-secondary);
  font-size: var(--mf-text-sm);
  font-weight: 400;
}
.group-name {
  overflow-wrap: anywhere;
}
.group-assigned,
.group-missing {
  display: inline-flex;
  align-items: center;
  gap: var(--mf-space-1);
  white-space: nowrap;
}
.group-missing {
  color: var(--mf-tone-warning-fg);
}
.group-assigned {
  color: var(--mf-color-text-secondary);
}
</style>
