<script setup lang="ts">
import { computed } from 'vue';
import MfStatus from '@/components/status/MfStatus.vue';
import UiDataTable from '../../ui/components/UiDataTable.vue';
import type { UiTableColumn } from '../../ui/table-types';
import { reportOrderName, reportOrderStatus } from '../rules';
import type { OrderNameSales } from '../api';
import { useLocalTable } from '../useLocalTable';

const props = defineProps<{ items: OrderNameSales[] }>();
const columns: UiTableColumn[] = [
  { key: 'number', label: 'Заказ', primary: true, sortable: true },
  { key: 'name', label: 'Имя и фамилия ребёнка', mobile: true, sortable: true },
  { key: 'status', label: 'Оплата', mobile: true, sortable: true },
  { key: 'total', label: 'Сумма заказа', type: 'money', mobile: true, sortable: true }
];
const byId = computed(() => new Map(props.items.map((order) => [order.id, order])));
const table = useLocalTable(
  () =>
    props.items.map((order) => ({
      id: order.id,
      number: order.number,
      name: reportOrderName(order),
      status: reportOrderStatus(order).label,
      total: order.total
    })),
  columns,
  { key: 'number', direction: 'asc' }
);
const tone = (id: string) => reportOrderStatus(byId.value.get(id)!).tone;
</script>

<template>
  <UiDataTable
    title="Заказы"
    label-key="number"
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
    empty-title="Заказов пока нет"
    empty-description="Здесь появятся оформленные заказы группы, включая неоплаченные."
    data-testid="report-orders"
    @sort="table.order"
    @page="table.page.value = $event"
    @page-size="table.resize"
  >
    <template #cell-number="{ row }"
      ><strong data-testid="report-order">{{ row.number }}</strong></template
    >
    <template #cell-status="{ row }"
      ><MfStatus :tone="tone(row.id)">{{ row.status }}</MfStatus></template
    >
  </UiDataTable>
</template>
