<script setup lang="ts">
import { computed } from 'vue';
import type { LocationQueryRaw } from 'vue-router';
import UiDataTable from '../ui/components/UiDataTable.vue';
import type { UiTableColumn, UiTableRow, UiTableSort } from '../ui/table-types';
import type { AttemptStatus, StaffPayment } from '../orders/live/payment-types';
import { money } from '../commerce/money';
import { tableMoment } from '../ui/table-values';
import { attemptStatusLabels, paymentMethodLabels } from '../orders/live/payment-rules';
import MfStatus from '@/components/status/MfStatus.vue';
import type { StatusTone } from '@/components/status/tones';
/** The payment registry as a journal: server-side sort and pages, selection only for the CSV export (#92 DEC-03). */
const props = defineProps<{
  payments: StaffPayment[];
  total: number;
  page: number;
  pageSize: number;
  sort: UiTableSort;
  loading: boolean;
  query: LocationQueryRaw;
}>();
const emit = defineEmits<{ sort: [sort: UiTableSort]; page: [page: number]; pageSize: [size: number]; export: [] }>();
const selected = defineModel<string[]>('selected', { default: () => [] });
const tone: Record<AttemptStatus, StatusTone> = { unknown: 'warning', pending: 'pending', succeeded: 'success', canceled: 'neutral' };
const columns: UiTableColumn[] = [
  { key: 'orderNumber', label: 'Заказ', sortable: true, primary: true, width: '14%' },
  { key: 'place', label: 'Учреждение и группа', width: '25%' },
  { key: 'paymentMethod', label: 'Способ', sortable: true, width: '15%' },
  { key: 'amount', label: 'Сумма', type: 'money', sortable: true, mobile: true, width: '12%' },
  { key: 'status', label: 'Статус', sortable: true, mobile: true, width: '16%' },
  { key: 'createdAt', label: 'Начат', sortable: true, mobile: true, width: '16%' }
];
const byId = computed(() => new Map(props.payments.map((payment) => [payment.id, payment])));
const rows = computed<UiTableRow[]>(() =>
  props.payments.map((payment) => ({
    id: payment.id,
    orderNumber: payment.orderNumber,
    place: payment.institutionName,
    paymentMethod: paymentMethodLabels[payment.paymentMethod],
    amount: payment.amount,
    status: payment.status,
    createdAt: payment.createdAt
  }))
);
const payment = (id: string) => byId.value.get(id)!;
</script>
<template>
  <UiDataTable
    title="Платежи"
    label-key="orderNumber"
    density="compact"
    actions-width="80px"
    :columns="columns"
    :rows="rows"
    :total="total"
    :page="page"
    :page-size="pageSize"
    :sort="sort"
    :selected="selected"
    :removable="false"
    :loading="loading"
    empty-title="Платежей по этим условиям нет"
    empty-description="Измените или сбросьте фильтры."
    @sort="emit('sort', $event)"
    @page="emit('page', $event)"
    @page-size="emit('pageSize', $event)"
    @select="selected = $event"
  >
    <template #selection>
      <div class="payment-bulk" data-testid="payment-bulk">
        <p>Выбрано: {{ selected.length }}</p>
        <v-btn variant="outlined" density="compact" prepend-icon="mdi-file-delimited-outline" @click="emit('export')">Выгрузить CSV</v-btn>
        <v-btn variant="text" density="compact" @click="selected = []">Снять выбор</v-btn>
      </div>
    </template>
    <template #cell-orderNumber="{ row }">
      <RouterLink :to="{ path: '/cabinet/payments/' + row.id, query }" class="payment-number">{{ row.orderNumber }}</RouterLink>
    </template>
    <template #cell-place="{ row }">
      <span class="payment-cell">
        <span class="payment-clip" :title="String(row.place)">{{ row.place }}</span>
        <small class="payment-clip">{{ payment(row.id).groupName }}</small>
      </span>
    </template>
    <template #cell-amount="{ row }">{{ money(Number(row.amount)) }}</template>
    <template #cell-status="{ row }">
      <span class="payment-states">
        <MfStatus :tone="tone[payment(row.id).status]">{{ attemptStatusLabels[payment(row.id).status] }}</MfStatus>
        <MfStatus v-if="payment(row.id).latePayment" tone="warning">Поздняя</MfStatus>
      </span>
    </template>
    <template #cell-createdAt="{ row }">{{ tableMoment(String(row.createdAt)) }}</template>
    <template #actions="{ row }">
      <v-btn
        icon="mdi-receipt-text-outline"
        variant="text"
        density="compact"
        :to="'/cabinet/orders/' + payment(row.id).orderId"
        :aria-label="'Заказ ' + row.orderNumber"
      />
    </template>
  </UiDataTable>
</template>
<style scoped>
.payment-cell {
  display: grid;
  min-width: 0;
}
.payment-cell small {
  color: var(--mf-color-text-secondary);
  font-size: var(--mf-text-sm);
}
.payment-number {
  color: var(--mf-color-primary);
  font-weight: 600;
  text-decoration: none;
}
.payment-number:hover,
.payment-number:focus-visible {
  text-decoration: underline;
}
.payment-clip {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.payment-states,
.payment-bulk {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--mf-space-1);
}
.payment-bulk {
  gap: var(--mf-space-2);
}
</style>
