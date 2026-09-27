<script setup lang="ts">
import { computed, shallowRef } from 'vue';
import { useRoute } from 'vue-router';
import { money } from '../commerce/money';
import { formatMoment } from '../orders/formatters';
import { attemptStatusLabels, confirmationLabels, paymentMethodLabels } from '../orders/live/payment-rules';
import type { AttemptStatus } from '../orders/live/payment-types';
import MfStatus from '@/components/status/MfStatus.vue';
import type { StatusTone } from '@/components/status/tones';
import { usePaymentRegistry } from './usePaymentRegistry';
import PaymentTable from './PaymentTable.vue';
import { csvText, downloadCsv } from '../ui/csv';
import { livePaymentsApi } from '../orders/live/payments-api';
import type { StaffPayment } from '../orders/live/payment-types';
const route = useRoute();
const { attemptId, filters, view, page, card, loading, error, reload, apply, reset } = usePaymentRegistry();
const tone: Record<AttemptStatus, StatusTone> = { unknown: 'warning', pending: 'pending', succeeded: 'success', canceled: 'neutral' };
const statusOptions = [
  { title: 'Любой статус', value: '' },
  ...Object.entries(attemptStatusLabels).map(([value, title]) => ({ title, value }))
];
const lateOptions = [
  { title: 'Любой срок', value: '' },
  { title: 'Поздняя оплата', value: 'true' },
  { title: 'В срок', value: 'false' }
];
const hasFilters = computed(() => (['status', 'orderNumber', 'dateFrom', 'dateTo', 'late'] as const).some((key) => filters[key] !== ''));
const listLink = computed(() => ({ path: '/cabinet/payments', query: route.query }));
// Selection survives paging: the chosen payments are kept whole for the CSV.
const selected = shallowRef<string[]>([]);
const picked = new Map<string, StaffPayment>();
function select(ids: string[]): void {
  for (const payment of page.value?.items ?? []) if (ids.includes(payment.id)) picked.set(payment.id, payment);
  for (const id of [...picked.keys()]) if (!ids.includes(id)) picked.delete(id);
  selected.value = ids;
}
const csvHeader = ['Заказ', 'Учреждение', 'Группа', 'Способ', 'Сумма, ₽', 'Статус', 'Поздняя', 'Начат (мск)', 'Оплачен (мск)'];
function exportRows(payments: StaffPayment[]): void {
  const rows = payments.map((payment) => [
    payment.orderNumber,
    payment.institutionName,
    payment.groupName,
    paymentMethodLabels[payment.paymentMethod],
    (payment.amount / 100).toFixed(2).replace('.', ','),
    attemptStatusLabels[payment.status],
    payment.latePayment ? 'да' : '',
    formatMoment(payment.createdAt),
    payment.paidAt ? formatMoment(payment.paidAt) : ''
  ]);
  const today = new Intl.DateTimeFormat('en-CA', { timeZone: 'Europe/Moscow' }).format(new Date());
  downloadCsv('payments-' + today + '.csv', csvText(csvHeader, rows));
}
/** Everything the filter finds, page by page up to the limit (#92 DEC-07). */
const EXPORT_LIMIT = 1000;
const exporting = shallowRef(false);
const exportError = shallowRef('');
async function exportAll(): Promise<void> {
  exportError.value = '';
  if ((page.value?.meta.total ?? 0) > EXPORT_LIMIT) {
    exportError.value = 'Выгрузка — не больше ' + EXPORT_LIMIT + ' платежей. Уточните фильтр или период.';
    return;
  }
  exporting.value = true;
  try {
    const payments: StaffPayment[] = [];
    for (let number = 1, pages = 1; number <= pages; number++) {
      const result = await livePaymentsApi.search(filters, { ...view.value, page: number, pageSize: 100 });
      payments.push(...result.items);
      pages = result.meta.totalPages;
    }
    exportRows(payments);
  } finally {
    exporting.value = false;
  }
}
</script>
<template>
  <header class="payments__heading">
    <div>
      <p class="mf-eyebrow">ДЕНЬГИ</p>
      <h1>{{ attemptId ? 'Платёж' : 'Платежи' }}</h1>
      <p class="mf-muted">
        Попытки оплаты заказов вашей области. Оплату подтверждает только ЮKassa; ключи покупателей здесь не показываются.
      </p>
    </div>
    <v-btn
      v-if="!attemptId && page?.meta.total"
      variant="outlined"
      prepend-icon="mdi-download-outline"
      :loading="exporting"
      data-testid="payment-export-all"
      @click="exportAll"
      >Выгрузить всё · CSV</v-btn
    >
  </header>
  <v-alert v-if="exportError" type="warning" variant="tonal" class="mb-5" role="status">{{ exportError }}</v-alert>
  <v-alert v-if="error" type="error" variant="tonal" class="mb-5" role="alert"
    >{{ error }}<v-btn variant="text" @click="reload">Повторить</v-btn></v-alert
  >
  <template v-if="attemptId">
    <v-btn :to="listLink" variant="text" prepend-icon="mdi-arrow-left" class="mb-4">Все платежи</v-btn>
    <p v-if="loading && !card" role="status">Загружаем платёж…</p>
    <article v-if="card" class="payments__card" data-testid="payment-card">
      <header class="mf-panel payments__card-header">
        <div>
          <h2>{{ money(card.payment.amount) }} · {{ paymentMethodLabels[card.payment.paymentMethod] }}</h2>
          <p class="mf-muted">
            Заказ
            <RouterLink :to="'/cabinet/orders/' + card.payment.orderId">{{ card.payment.orderNumber }}</RouterLink>
            · {{ card.payment.institutionName }} · {{ card.payment.groupName }}
          </p>
          <p class="mf-muted">Начат {{ formatMoment(card.payment.createdAt) }} мск</p>
        </div>
        <div class="payments__state">
          <MfStatus :tone="tone[card.payment.status]" data-testid="payment-card-status">{{
            attemptStatusLabels[card.payment.status]
          }}</MfStatus>
          <MfStatus v-if="card.payment.latePayment" tone="warning">Поздняя оплата</MfStatus>
        </div>
      </header>
      <section class="mf-panel">
        <h3 class="payments__title">Сверка с ЮKassa</h3>
        <dl class="payments__facts">
          <div>
            <dt>Платёж в ЮKassa</dt>
            <dd>{{ card.providerPaymentId ?? 'ещё не создан' }}</dd>
          </div>
          <div>
            <dt>Проверок</dt>
            <dd>
              {{ card.checkCount }}<template v-if="card.lastCheckAt"> · последняя {{ formatMoment(card.lastCheckAt) }} мск</template>
            </dd>
          </div>
          <div v-if="card.nextCheckAt">
            <dt>Следующая проверка</dt>
            <dd>{{ formatMoment(card.nextCheckAt) }} мск</dd>
          </div>
          <div v-if="card.payment.cancelReason">
            <dt>Причина</dt>
            <dd>{{ card.payment.cancelReason }}</dd>
          </div>
        </dl>
      </section>
      <section class="mf-panel" data-testid="payment-facts">
        <h3 class="payments__title">Подтверждённые оплаты заказа</h3>
        <p v-if="!card.facts.length" class="mf-muted">Денег по заказу пока не поступало.</p>
        <ul v-else class="payments__plain">
          <li v-for="fact in card.facts" :key="fact.attemptId">
            {{ money(fact.amount) }} · {{ formatMoment(fact.paidAt) }} мск · подтверждено {{ confirmationLabels[fact.confirmedBy] }}
            <template v-if="fact.incomeAmount !== null"> · к зачислению {{ money(fact.incomeAmount) }}</template>
            <template v-if="fact.latePayment"> · поздняя оплата</template>
          </li>
        </ul>
      </section>
      <section v-if="card.attempts.length > 1" class="mf-panel">
        <h3 class="payments__title">Все попытки заказа</h3>
        <ul class="payments__plain">
          <li v-for="attempt in card.attempts" :key="attempt.id">
            <RouterLink :to="'/cabinet/payments/' + attempt.id">{{ formatMoment(attempt.createdAt) }}</RouterLink>
            · {{ paymentMethodLabels[attempt.paymentMethod] }} · {{ attemptStatusLabels[attempt.status] }}
          </li>
        </ul>
      </section>
    </article>
  </template>
  <template v-else>
    <form class="mf-panel payments__filters" role="search" @submit.prevent="apply()">
      <v-text-field
        v-model="filters.orderNumber"
        name="orderNumber"
        label="Номер заказа"
        aria-label="Номер заказа"
        prepend-inner-icon="mdi-magnify"
        density="compact"
        clearable
        hide-details="auto"
        @click:clear="filters.orderNumber = ''"
      />
      <v-select
        v-model="filters.status"
        :items="statusOptions"
        label="Статус"
        density="compact"
        hide-details
        data-testid="payment-status-filter"
      />
      <v-select v-model="filters.late" :items="lateOptions" label="Срок оплаты" density="compact" hide-details />
      <v-text-field v-model="filters.dateFrom" type="date" label="Начат с" aria-label="Начат с" density="compact" hide-details />
      <v-text-field v-model="filters.dateTo" type="date" label="Начат по" aria-label="Начат по" density="compact" hide-details />
      <div class="mf-actions payments__buttons">
        <v-btn type="submit" color="primary" density="compact" :loading="loading">Найти</v-btn>
        <v-btn variant="text" density="compact" :disabled="!hasFilters" @click="reset">Сбросить</v-btn>
      </div>
    </form>
    <p v-if="loading && !page" role="status">Загружаем платежи…</p>
    <PaymentTable
      v-if="page"
      :selected="selected"
      :payments="page.items"
      :total="page.meta.total"
      :page="page.meta.page"
      :page-size="view.pageSize"
      :sort="view.sort"
      :loading="loading"
      :query="route.query"
      @update:selected="select"
      @sort="apply(1, { sort: $event })"
      @page="apply($event)"
      @page-size="apply(1, { pageSize: $event })"
      @export="exportRows([...picked.values()])"
    />
  </template>
</template>
<style scoped>
.payments__heading {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  justify-content: space-between;
  gap: var(--mf-space-3);
  margin-bottom: 24px;
}
.payments__filters {
  display: grid;
  grid-template-columns: minmax(180px, 1.5fr) repeat(4, minmax(130px, 1fr)) auto;
  gap: 12px;
  align-items: center;
  padding: 12px;
  margin-bottom: var(--mf-space-4);
}
.payments__buttons {
  flex-wrap: nowrap;
  gap: var(--mf-space-1);
}
.payments__plain {
  display: grid;
  gap: 12px;
  padding: 0;
  list-style: none;
}
.payments__state {
  display: grid;
  justify-items: end;
  align-content: start;
  gap: 6px;
}
.payments__card {
  display: grid;
  gap: 20px;
}
.payments__card-header {
  display: flex;
  justify-content: space-between;
  gap: 16px;
}
.payments__title {
  font-size: 18px;
  margin-bottom: 12px;
}
.payments__facts {
  display: grid;
  gap: 10px;
  margin: 0;
}
.payments__facts > div {
  display: grid;
  grid-template-columns: minmax(140px, 200px) minmax(0, 1fr);
  gap: 12px;
}
.payments__facts dt {
  color: var(--mf-color-text-secondary);
}
.payments__facts dd {
  margin: 0;
  overflow-wrap: anywhere;
}
@media (max-width: 1100px) {
  .payments__filters {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .payments__filters > :first-child,
  .payments__buttons {
    grid-column: 1 / -1;
  }
}
@media (max-width: 600px) {
  .payments__filters,
  .payments__facts > div {
    grid-template-columns: minmax(0, 1fr);
  }
  .payments__card-header {
    flex-direction: column;
  }
  .payments__state {
    justify-items: start;
  }
}
</style>
