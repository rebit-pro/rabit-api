<script setup lang="ts">
import { computed } from 'vue';
import MfStatus from '@/components/status/MfStatus.vue';
import UiDataTable from '../../ui/components/UiDataTable.vue';
import type { UiTableColumn } from '../../ui/table-types';
import type { ChildSales } from '../api';
import { CHILD_STATUS, childOrdersText } from '../rules';
import { useLocalTable } from '../useLocalTable';
import ReportChildThumb from './ReportChildThumb.vue';

// E10-DEC-01: children by code and frame only — no names, contacts or order numbers of the buyers.
const props = defineProps<{ items: ChildSales[] }>();
const columns: UiTableColumn[] = [
  { key: 'code', label: 'Ребёнок', sortable: true, primary: true, width: '18%' },
  { key: 'status', label: 'Статус', sortable: true, mobile: true, width: '20%' },
  { key: 'paidOrders', label: 'Оплачено заказов', type: 'number', sortable: true, mobile: true },
  { key: 'prints', label: 'Печать', type: 'number', sortable: true },
  { key: 'files', label: 'Цифровые', type: 'number', sortable: true },
  { key: 'total', label: 'Сумма', type: 'money', sortable: true, mobile: true, width: '13%' }
];
const byId = computed(() => new Map(props.items.map((child) => [child.id, child])));
// Codes sort as the gallery shows them: A…Z, then AA.
const codeOrder = (code: string) => code.length.toString().padStart(2, '0') + code;
const table = useLocalTable(
  () =>
    props.items.map((child) => ({
      id: child.id,
      code: codeOrder(child.code),
      status: CHILD_STATUS[child.status].label,
      paidOrders: child.paidOrders,
      prints: child.prints,
      files: child.digitalFiles + child.bundles,
      total: child.paidTotal
    })),
  columns,
  { key: 'code', direction: 'asc' }
);
const child = (id: string) => byId.value.get(id)!;
</script>

<template>
  <UiDataTable
    title="Дети группы"
    label-key="code"
    density="compact"
    :columns="columns"
    :rows="table.visible.value"
    :total="table.total.value"
    :page="table.page.value"
    :page-size="table.pageSize.value"
    :sort="table.sort.value"
    :selected="[]"
    :selectable="false"
    :actions="false"
    auto-pager
    empty-title="В группе пока нет детей"
    empty-description="Дети появятся после разметки фотографий группы."
    data-testid="report-children"
    @sort="table.order"
    @page="table.page.value = $event"
    @page-size="table.resize"
  >
    <template #cell-code="{ row }">
      <ReportChildThumb :child-id="row.id" :code="child(row.id).code" :has-preview="child(row.id).hasPreview" />
    </template>
    <template #cell-status="{ row }">
      <MfStatus :tone="CHILD_STATUS[child(row.id).status].tone" data-testid="report-child-status">{{ row.status }}</MfStatus>
    </template>
    <template #cell-paidOrders="{ row }">{{ childOrdersText(child(row.id).paidOrders, child(row.id).orders) }}</template>
  </UiDataTable>
</template>
