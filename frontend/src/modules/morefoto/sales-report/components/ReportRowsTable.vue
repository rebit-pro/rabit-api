<script setup lang="ts">
import UiDataTable from '../../ui/components/UiDataTable.vue';
import type { UiTableColumn } from '../../ui/table-types';
import type { SalesReport } from '../api';
import { useLocalTable } from '../useLocalTable';

// Institutions, shoots or groups of the level: each name opens the level below with its own report.
const props = defineProps<{ report: SalesReport; title: string; rowHref: (id: string) => string }>();
const columns: UiTableColumn[] = [
  { key: 'name', label: 'Название', sortable: true, primary: true, width: '24%' },
  { key: 'children', label: 'Детей', type: 'number', sortable: true },
  { key: 'ordered', label: 'Заказали', type: 'number', sortable: true, mobile: true },
  { key: 'paidFamilies', label: 'Оплатили', type: 'number', sortable: true, mobile: true },
  { key: 'paidOrders', label: 'Заказов', type: 'number', sortable: true },
  { key: 'prints', label: 'Печать', type: 'number', sortable: true },
  { key: 'files', label: 'Цифровые', type: 'number', sortable: true },
  { key: 'total', label: 'Сумма', type: 'money', sortable: true, mobile: true, width: '13%' }
];
const table = useLocalTable(
  () =>
    props.report.rows.map((row) => ({
      id: row.id,
      name: row.name,
      children: row.totals.children,
      ordered: row.totals.orderedFamilies,
      paidFamilies: row.totals.paidFamilies,
      paidOrders: row.totals.paidOrders,
      prints: row.totals.prints,
      files: row.totals.digitalFiles + row.totals.bundles,
      total: row.totals.paidTotal
    })),
  columns,
  { key: 'all' === props.report.level ? 'name' : 'total', direction: 'all' === props.report.level ? 'asc' : 'desc' }
);
</script>

<template>
  <UiDataTable
    :title="title"
    label-key="name"
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
    empty-title="Здесь пока пусто"
    empty-description="В вашей области ещё нет групп с назначенной съёмкой."
    data-testid="report-rows"
    @sort="table.order"
    @page="table.page.value = $event"
    @page-size="table.resize"
  >
    <template #cell-name="{ row }">
      <RouterLink :to="rowHref(row.id)" class="report-link" data-testid="report-row-link">{{ row.name }}</RouterLink>
    </template>
  </UiDataTable>
</template>

<style scoped>
.report-link {
  color: var(--mf-color-primary);
  font-weight: var(--mf-weight-semibold);
  text-decoration: none;
  overflow-wrap: anywhere;
}
.report-link:hover,
.report-link:focus-visible {
  text-decoration: underline;
}
</style>
