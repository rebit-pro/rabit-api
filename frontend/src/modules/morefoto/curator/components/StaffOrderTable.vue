<script setup lang="ts">
import { computed } from 'vue';
import type { LocationQueryRaw } from 'vue-router';
import UiDataTable from '../../ui/components/UiDataTable.vue';
import type { UiTableColumn, UiTableRow, UiTableSort } from '../../ui/table-types';
import type { StaffOrder } from '../../orders/live/types';
import { money } from '../../commerce/money';
import { livePaymentLabels as paymentLabels, productionLabels } from '../../orders/formatters';
import { tableMoment } from '../../ui/table-values';
import { formatPhone } from '../../ui/field-values';
import MfStatus from '@/components/status/MfStatus.vue';
import { toneOf } from '@/components/status/tones';
import { paymentTone, productionTone } from '../../ui/statusTone';
/** One server page of orders; sorting and paging are asked from the server, the whole filter is ordered (#92 DEC-05). */
const props = defineProps<{
  orders: StaffOrder[];
  total: number;
  page: number;
  pageSize: number;
  sort: UiTableSort;
  loading: boolean;
  canRemove: boolean;
  query: LocationQueryRaw;
}>();
const emit = defineEmits<{
  sort: [sort: UiTableSort];
  page: [page: number];
  pageSize: [size: number];
  remove: [orders: StaffOrder[]];
  export: [];
}>();
const selected = defineModel<string[]>('selected', { default: () => [] });
const columns: UiTableColumn[] = [
  { key: 'createdAt', label: 'Заказ', sortable: true, primary: true, width: '14%' },
  { key: 'buyerName', label: 'Покупатель', sortable: true, mobile: true, width: '17%' },
  { key: 'institutionName', label: 'Учреждение и группа', sortable: true, width: '21%' },
  { key: 'total', label: 'Сумма', type: 'money', sortable: true, mobile: true, width: '9%' },
  { key: 'paymentStatus', label: 'Оплата', sortable: true, mobile: true, width: '18%' },
  { key: 'productionStatus', label: 'Изготовление', sortable: true, width: '17%' }
];
const byId = computed(() => new Map(props.orders.map((order) => [order.id, order])));
const rows = computed<UiTableRow[]>(() =>
  props.orders.map((order) => ({
    id: order.id,
    createdAt: order.createdAt,
    number: order.number,
    buyerName: order.buyer.name,
    institutionName: order.institutionName,
    total: order.quote.total,
    paymentStatus: order.paymentStatus,
    productionStatus: order.productionStatus
  }))
);
const order = (id: string) => byId.value.get(id)!;
/** Paid or paying orders stay: only an order without money can be removed (#92 DEC-02); the server decides finally. */
function deletable(id: string): boolean {
  const status = order(id).paymentStatus;
  return status === 'unpaid' || status === 'declined';
}
</script>
<template>
  <UiDataTable
    title="Заказы"
    label-key="number"
    density="compact"
    actions-width="88px"
    :columns="columns"
    :rows="rows"
    :total="total"
    :page="page"
    :page-size="pageSize"
    :sort="sort"
    :selected="selected"
    :loading="loading"
    empty-title="Заказов по этим условиям нет"
    empty-description="Измените или сбросьте фильтры."
    @sort="emit('sort', $event)"
    @page="emit('page', $event)"
    @page-size="emit('pageSize', $event)"
    @select="selected = $event"
  >
    <template #selection>
      <div class="order-bulk" data-testid="order-bulk">
        <p>Выбрано: {{ selected.length }}</p>
        <v-btn variant="outlined" density="compact" prepend-icon="mdi-file-delimited-outline" @click="emit('export')">Выгрузить CSV</v-btn>
        <v-btn
          v-if="canRemove"
          color="error"
          variant="outlined"
          density="compact"
          prepend-icon="mdi-delete-outline"
          @click="emit('remove', selected.filter((id) => byId.has(id)).map(order))"
          >Удалить неоплаченные</v-btn
        >
        <v-btn variant="text" density="compact" @click="selected = []">Снять выбор</v-btn>
      </div>
    </template>
    <template #cell-createdAt="{ row }">
      <span class="order-cell">
        <RouterLink :to="{ path: '/cabinet/orders/' + row.id, query }" class="order-number">{{ row.number }}</RouterLink>
        <small>{{ tableMoment(String(row.createdAt)) }}</small>
      </span>
    </template>
    <template #cell-buyerName="{ row }">
      <span class="order-cell">
        <span>{{ row.buyerName }}</span>
        <small>{{ formatPhone(order(row.id).buyer.phone) }}</small>
      </span>
    </template>
    <template #cell-institutionName="{ row }">
      <span class="order-cell">
        <span class="order-clip" :title="String(row.institutionName)">{{ row.institutionName }}</span>
        <small class="order-clip">{{ order(row.id).groupName }}</small>
      </span>
    </template>
    <template #cell-total="{ row }">{{ money(Number(row.total)) }}</template>
    <template #cell-paymentStatus="{ row }">
      <span class="order-states">
        <MfStatus :tone="toneOf(paymentTone, String(row.paymentStatus))">{{ paymentLabels[order(row.id).paymentStatus] }}</MfStatus>
        <MfStatus v-if="order(row.id).latePayment" tone="warning">Поздняя</MfStatus>
      </span>
    </template>
    <template #cell-productionStatus="{ row }">
      <MfStatus :tone="toneOf(productionTone, String(row.productionStatus))">{{
        productionLabels[order(row.id).productionStatus]
      }}</MfStatus>
    </template>
    <template #actions="{ row }">
      <div class="order-actions">
        <v-btn
          icon="mdi-credit-card-outline"
          variant="text"
          density="compact"
          :to="{ path: '/cabinet/payments', query: { orderNumber: String(row.number) } }"
          :aria-label="'Платежи заказа ' + row.number"
        />
        <v-btn
          v-if="canRemove"
          icon="mdi-delete-outline"
          variant="text"
          density="compact"
          color="error"
          :disabled="!deletable(row.id)"
          :aria-label="deletable(row.id) ? 'Удалить заказ ' + row.number : 'Заказ ' + row.number + ' с оплатой не удаляется'"
          @click="emit('remove', [order(row.id)])"
        />
      </div>
    </template>
  </UiDataTable>
</template>
<style scoped>
.order-cell {
  display: grid;
  min-width: 0;
}
.order-cell small {
  color: var(--mf-color-text-secondary);
  font-size: var(--mf-text-sm);
  font-weight: 400;
}
.order-number {
  color: var(--mf-color-primary);
  font-weight: 600;
  text-decoration: none;
}
.order-number:hover,
.order-number:focus-visible {
  text-decoration: underline;
}
.order-clip {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.order-states {
  display: flex;
  flex-wrap: wrap;
  gap: var(--mf-space-1);
}
.order-actions,
.order-bulk {
  display: flex;
  align-items: center;
  gap: var(--mf-space-1);
}
.order-bulk {
  flex-wrap: wrap;
  gap: var(--mf-space-2);
}
</style>
