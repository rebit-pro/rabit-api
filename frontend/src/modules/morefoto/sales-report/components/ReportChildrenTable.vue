<script setup lang="ts">
import { computed } from 'vue';
import MfStatus from '@/components/status/MfStatus.vue';
import UiDataTable from '../../ui/components/UiDataTable.vue';
import type { UiTableColumn } from '../../ui/table-types';
import type { ChildSales } from '../api';
import { CHILD_STATUS, childOrdersText } from '../rules';
import { useLocalTable } from '../useLocalTable';

// Child rows use codes; a name belongs to the separate order list and is never guessed from the code.
const props = defineProps<{ items: ChildSales[]; emptyTitle: string; emptyDescription: string }>();
const columns: UiTableColumn[] = [
  { key: 'code', label: 'Код ребёнка', sortable: true, primary: true },
  { key: 'status', label: 'Статус', sortable: true, mobile: true },
  { key: 'paidOrders', label: 'Оплачено заказов', type: 'number', sortable: true, mobile: true },
  { key: 'prints', label: 'Печать', type: 'number', sortable: true },
  { key: 'files', label: 'Эл. кадры', type: 'number', sortable: true },
  { key: 'bundles', label: 'Комплекты', type: 'number', sortable: true },
  { key: 'total', label: 'Оплачено', type: 'money', sortable: true, mobile: true }
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
      files: child.digitalFiles,
      bundles: child.bundles,
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
    :empty-title="emptyTitle"
    :empty-description="emptyDescription"
    data-testid="report-children"
    @sort="table.order"
    @page="table.page.value = $event"
    @page-size="table.resize"
  >
    <template #cell-code="{ row }">
      <strong>{{ child(row.id).code }}</strong>
    </template>
    <template #cell-status="{ row }">
      <MfStatus :tone="CHILD_STATUS[child(row.id).status].tone" data-testid="report-child-status">{{ row.status }}</MfStatus>
    </template>
    <template #cell-paidOrders="{ row }">{{ childOrdersText(child(row.id).paidOrders, child(row.id).orders) }}</template>
  </UiDataTable>
</template>
