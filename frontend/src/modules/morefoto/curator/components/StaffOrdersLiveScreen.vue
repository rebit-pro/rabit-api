<script setup lang="ts">
import { computed, shallowRef } from 'vue';
import { useRoute } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { money } from '../../commerce/money';
import OrderComposition from '../../orders/components/OrderComposition.vue';
import OrderLiveFacts from '../../orders/components/OrderLiveFacts.vue';
import { formatMoment, livePaymentLabels as paymentLabels, productionLabels } from '../../orders/formatters';
import { hasStaffFilters, orderQuoteAsCart } from '../../orders/live/rules';
import { formatPhone } from '../../ui/field-values';
import { useStaffOrders } from '../useStaffOrders';
import { useStaffOrderScope } from '../useStaffOrderScope';
import MfStatus from '@/components/status/MfStatus.vue';
import { toneOf } from '@/components/status/tones';
import { paymentTone, productionTone } from '../../ui/statusTone';
import MfStatTile from '@/components/viz/MfStatTile.vue';
import MfDistribution from '@/components/viz/MfDistribution.vue';
import { plural } from '@/components/viz/measures';
import { CHART_CATEGORY } from '../../ui/chartPalette';
import StaffOrderTable from './StaffOrderTable.vue';
import UiRemoveDialog from '../../ui/components/UiRemoveDialog.vue';
import UiBulkNotice from '../../ui/components/UiBulkNotice.vue';
import { bulkNotice, runEach, type BulkNotice } from '../../ui/removal';
import { csvText, downloadCsv } from '../../ui/csv';
import { liveOrdersApi } from '../../orders/live/api';
import type { StaffOrder } from '../../orders/live/types';
const route = useRoute();
const auth = useAuthStore();
const { orderId, filters, view, page, card, loading, error, reload, apply, reset, selectScope } = useStaffOrders();
const {
  institutionId: scopeInstitution,
  options: scopeOptions,
  listing: scopeListing,
  loading: scopeLoading,
  error: scopeError,
  retry: retryScope
} = useStaffOrderScope(filters, () => !orderId.value);
// A shoot or group list without an institution has nothing to offer, unless a link already chose a value there.
const shootDisabled = computed(() => !scopeInstitution.value && !filters.shootId);
const groupDisabled = computed(() => !scopeInstitution.value && !filters.groupId);
// Managed previews are organizer-only in Media; curators see frame codes (E5-DEC-03).
const thumb = computed(() =>
  auth.user?.permissions?.includes('media.manage') ? (photoId: string) => '/api/v1/photos/' + photoId + '/thumb' : undefined
);
const listLink = computed(() => ({ path: '/cabinet/orders', query: route.query }));
const paymentOptions = [{ title: 'Любая оплата', value: '' }, ...Object.entries(paymentLabels).map(([value, title]) => ({ title, value }))];
const productionOptions = [
  { title: 'Любое изготовление', value: '' },
  ...Object.entries(productionLabels).map(([value, title]) => ({ title, value }))
];
const hasFilters = computed(() => hasStaffFilters(filters));
// U5: the split ignores the production filter, so it keeps showing where the found orders are in production.
const summary = computed(() => page.value?.meta.summary ?? null);
const productionSegments = computed(() =>
  Object.entries(productionLabels).map(([status, label]) => ({
    key: status,
    label,
    value: summary.value?.byProductionStatus[status] ?? 0,
    tone: toneOf(productionTone, status)
  }))
);
const photoCodes = computed(() => card.value?.correctionPhotos.map((photo) => photo.code).join(', ') ?? '');
// Period presets end today by Moscow time, the day the filter dates are counted in.
function moscowDate(offsetDays: number): string {
  return new Intl.DateTimeFormat('en-CA', { timeZone: 'Europe/Moscow' }).format(new Date(Date.now() - offsetDays * 86400000));
}
// Selection survives paging: the chosen orders are kept whole, so CSV and removal need no second read.
const selected = shallowRef<string[]>([]);
const picked = new Map<string, StaffOrder>();
function select(ids: string[]): void {
  for (const order of page.value?.items ?? []) if (ids.includes(order.id)) picked.set(order.id, order);
  for (const id of [...picked.keys()]) if (!ids.includes(id)) picked.delete(id);
  selected.value = ids;
}
const more = shallowRef(!!(filters.shootId || filters.groupId || filters.dateFrom || filters.dateTo));
const canRemove = computed(() => !!auth.user?.permissions?.includes('organization.manage'));
const removal = shallowRef<StaffOrder[] | null>(null);
const skipped = shallowRef(0);
const removing = shallowRef(false);
const notice = shallowRef<BulkNotice | null>(null);
function askRemove(orders: StaffOrder[]): void {
  const unpaid = orders.filter((order) => order.paymentStatus === 'unpaid' || order.paymentStatus === 'declined');
  skipped.value = orders.length - unpaid.length;
  if (unpaid.length) removal.value = unpaid;
  else notice.value = { tone: 'warning', text: 'Выбранные заказы оплачены или оплачиваются — удалять нечего.', failures: [] };
}
async function confirmRemove(): Promise<void> {
  if (!removal.value) return;
  removing.value = true;
  try {
    const result = await runEach(
      removal.value,
      (order) => order.number,
      (order) => liveOrdersApi.remove(order.id),
      () => 'заказ оплачен или оплата ещё идёт'
    );
    notice.value = bulkNotice('Удалено', result);
    select([]);
    removal.value = null;
    await reload();
  } finally {
    removing.value = false;
  }
}
const csvHeader = [
  'Номер',
  'Создан (мск)',
  'Учреждение',
  'Съёмка',
  'Группа',
  'Покупатель',
  'Телефон',
  'Email',
  'Сумма, ₽',
  'Оплата',
  'Изготовление'
];
function exportRows(orders: StaffOrder[]): void {
  const rows = orders.map((order) => [
    order.number,
    formatMoment(order.createdAt),
    order.institutionName,
    order.shootName,
    order.groupName,
    order.buyer.name,
    formatPhone(order.buyer.phone),
    order.buyer.email,
    (order.quote.total / 100).toFixed(2).replace('.', ','),
    paymentLabels[order.paymentStatus],
    productionLabels[order.productionStatus]
  ]);
  downloadCsv('orders-' + moscowDate(0) + '.csv', csvText(csvHeader, rows));
}
/** Everything the filter finds, page by page up to the limit (#92 DEC-07); a larger set asks for a narrower filter. */
const EXPORT_LIMIT = 1000;
const exporting = shallowRef(false);
async function exportAll(): Promise<void> {
  if ((page.value?.meta.total ?? 0) > EXPORT_LIMIT) {
    notice.value = {
      tone: 'warning',
      text: 'Выгрузка — не больше ' + EXPORT_LIMIT + ' заказов. Уточните фильтр или период.',
      failures: []
    };
    return;
  }
  exporting.value = true;
  try {
    const orders: StaffOrder[] = [];
    for (let number = 1, pages = 1; number <= pages; number++) {
      const result = await liveOrdersApi.search(filters, { ...view.value, page: number, pageSize: 100 });
      orders.push(...result.items);
      pages = result.meta.totalPages;
    }
    exportRows(orders);
  } finally {
    exporting.value = false;
  }
}
function lastDays(days: number): void {
  filters.dateFrom = moscowDate(days - 1);
  filters.dateTo = moscowDate(0);
  apply();
}
</script>
<template>
  <header class="staff-orders__heading">
    <div>
      <p class="mf-eyebrow">РАБОТА С УЧРЕЖДЕНИЕМ</p>
      <h1>{{ orderId ? 'Заказ' : 'Заказы' }}</h1>
      <p class="mf-muted">Заказы покупателей в вашей области. Ключи доступа покупателей здесь не показываются.</p>
    </div>
    <v-btn
      v-if="!orderId && page?.meta.total"
      variant="outlined"
      prepend-icon="mdi-download-outline"
      :loading="exporting"
      data-testid="order-export-all"
      @click="exportAll"
      >Выгрузить всё · CSV</v-btn
    >
  </header>
  <v-alert v-if="error" type="error" variant="tonal" class="mb-5" role="alert"
    >{{ error }}<v-btn variant="text" @click="reload">Повторить</v-btn></v-alert
  >
  <template v-if="orderId">
    <v-btn :to="listLink" variant="text" prepend-icon="mdi-arrow-left" class="mb-4">Все заказы</v-btn>
    <p v-if="loading && !card" role="status">Загружаем заказ…</p>
    <article v-if="card" class="staff-order" data-testid="staff-order-card">
      <header class="mf-panel staff-order__header">
        <div>
          <h2 data-testid="staff-order-number">{{ card.number }}</h2>
          <p class="mf-muted">{{ card.institutionName }} · {{ card.shootName }} · {{ card.groupName }}</p>
          <p class="mf-muted">Создан {{ formatMoment(card.createdAt) }} мск</p>
        </div>
        <div class="staff-order__state">
          <strong>{{ money(card.quote.total) }}</strong>
          <MfStatus :tone="toneOf(paymentTone, card.paymentStatus)">{{ paymentLabels[card.paymentStatus] }}</MfStatus>
          <span v-if="card.paidAt" class="mf-muted" data-testid="staff-order-paid-at">Оплачен {{ formatMoment(card.paidAt) }} мск</span>
          <MfStatus v-if="card.latePayment" tone="warning" data-testid="staff-order-late">Поздняя оплата</MfStatus>
          <RouterLink :to="{ path: '/cabinet/payments', query: { orderNumber: card.number } }" class="mf-muted">Платежи заказа</RouterLink>
          <MfStatus :tone="toneOf(productionTone, card.productionStatus)">{{ productionLabels[card.productionStatus] }}</MfStatus>
        </div>
      </header>
      <OrderComposition :quote="orderQuoteAsCart(card.quote, thumb)" />
      <OrderLiveFacts :period="card.period" :buyer="card.buyer" />
      <section class="mf-panel" data-testid="correction-photos">
        <h2 class="staff-order__title">Кадры детей заказа</h2>
        <p class="mf-muted">
          {{ photoCodes ? 'Готовые кадры для будущих исправлений: ' + photoCodes + '.' : 'Готовых кадров этих детей сейчас нет.' }}
        </p>
      </section>
    </article>
  </template>
  <template v-else>
    <section v-if="summary" class="staff-orders__summary" aria-label="Заказы по изготовлению" data-testid="order-summary">
      <MfStatTile
        label="Заказов найдено"
        :value="summary.total"
        :unit="plural(summary.total, ['заказ', 'заказа', 'заказов'])"
        :pastel="CHART_CATEGORY.orders"
        icon="mdi-receipt-text-outline"
      />
      <div class="mf-panel">
        <MfDistribution title="Изготовление" :segments="productionSegments" :unit-forms="['заказа', 'заказов', 'заказов']" />
      </div>
    </section>
    <form class="mf-panel staff-orders__filters" role="search" @submit.prevent="apply()">
      <!-- Vuetify sets null on clear; keep the filter a string for apply() and the URL. -->
      <v-text-field
        v-model="filters.q"
        name="q"
        label="Номер, имя, email или телефон"
        aria-label="Номер, имя, email или телефон"
        prepend-inner-icon="mdi-magnify"
        density="compact"
        clearable
        hide-details="auto"
        @click:clear="filters.q = ''"
      />
      <v-select
        :model-value="filters.institutionId"
        :items="scopeOptions.institutionId"
        label="Учреждение"
        aria-label="Учреждение"
        density="compact"
        hide-details
        :loading="scopeListing"
        @update:model-value="selectScope('institutionId', $event)"
      />
      <v-select v-model="filters.paymentStatus" :items="paymentOptions" label="Оплата" density="compact" hide-details />
      <v-select v-model="filters.productionStatus" :items="productionOptions" label="Изготовление" density="compact" hide-details />
      <div class="mf-actions staff-orders__buttons">
        <v-btn type="submit" color="primary" density="compact" :loading="loading">Найти</v-btn>
        <v-btn
          variant="text"
          density="compact"
          :prepend-icon="more ? 'mdi-chevron-up' : 'mdi-tune-variant'"
          :aria-expanded="more"
          @click="more = !more"
          >{{ more ? 'Меньше' : 'Фильтры' }}</v-btn
        >
        <v-btn variant="text" density="compact" :disabled="!hasFilters" @click="reset">Сбросить</v-btn>
      </div>
      <div v-if="more" class="staff-orders__more" data-testid="order-scope-filters">
        <v-select
          :model-value="filters.shootId"
          :items="scopeOptions.shootId"
          label="Съёмка"
          aria-label="Съёмка"
          density="compact"
          hide-details
          :loading="scopeLoading"
          :disabled="shootDisabled"
          @update:model-value="selectScope('shootId', $event)"
        />
        <v-select
          :model-value="filters.groupId"
          :items="scopeOptions.groupId"
          label="Группа"
          aria-label="Группа"
          density="compact"
          hide-details
          :loading="scopeLoading"
          :disabled="groupDisabled"
          @update:model-value="selectScope('groupId', $event)"
        />
        <v-text-field v-model="filters.dateFrom" type="date" label="Создан с" aria-label="Создан с" density="compact" hide-details />
        <v-text-field v-model="filters.dateTo" type="date" label="Создан по" aria-label="Создан по" density="compact" hide-details />
        <div class="mf-actions staff-orders__presets" aria-label="Быстрый период">
          <v-btn variant="outlined" density="compact" @click="lastDays(7)">7 дней</v-btn>
          <v-btn variant="outlined" density="compact" @click="lastDays(30)">30 дней</v-btn>
        </div>
      </div>
      <div v-if="scopeError" class="staff-orders__scope-error">
        <p class="mf-muted" role="alert">{{ scopeError }}</p>
        <v-btn variant="text" density="compact" @click="retryScope">Повторить</v-btn>
      </div>
    </form>
    <UiBulkNotice v-if="notice" :notice="notice" class="mb-4" />
    <p v-if="loading && !page" role="status">Загружаем заказы…</p>
    <StaffOrderTable
      v-if="page"
      :selected="selected"
      :orders="page.items"
      :total="page.meta.total"
      :page="page.meta.page"
      :page-size="view.pageSize"
      :sort="view.sort"
      :loading="loading"
      :can-remove="canRemove"
      :query="route.query"
      @update:selected="select"
      @sort="apply(1, { sort: $event })"
      @page="apply($event)"
      @page-size="apply(1, { pageSize: $event })"
      @remove="askRemove"
      @export="exportRows([...picked.values()])"
    />
  </template>
  <UiRemoveDialog
    :open="!!removal"
    :title="removal?.length === 1 ? 'Удалить заказ ' + removal[0]?.number + '?' : 'Удалить заказы: ' + (removal?.length ?? 0) + '?'"
    :names="removal?.map((order) => order.number + ' · ' + order.buyer.name + ' · ' + money(order.quote.total)) ?? []"
    :busy="removing"
    testid="order-remove-dialog"
    @close="removal = null"
    @confirm="confirmRemove"
  >
    Заказ исчезнет из кабинета вместе с отменёнными попытками оплаты. Ссылка покупателя на заказ перестанет открываться.
    <template v-if="skipped" #warning>
      <v-alert type="info" variant="tonal" density="compact"
        >Оплаченные и оплачиваемые заказы не удаляются — пропущено: {{ skipped }}.</v-alert
      >
    </template>
  </UiRemoveDialog>
</template>
<style scoped>
.staff-orders__heading {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  justify-content: space-between;
  gap: var(--mf-space-3);
  margin-bottom: 24px;
}
.staff-orders__summary {
  display: grid;
  grid-template-columns: minmax(200px, 1fr) minmax(0, 3fr);
  gap: var(--mf-space-4);
  margin-bottom: var(--mf-space-4);
}
.staff-orders__filters {
  display: grid;
  grid-template-columns: minmax(200px, 1.6fr) repeat(3, minmax(160px, 1fr)) auto;
  gap: 12px;
  align-items: center;
  padding: 12px;
  margin-bottom: var(--mf-space-4);
}
.staff-orders__buttons {
  flex-wrap: nowrap;
  gap: var(--mf-space-1);
}
.staff-orders__more {
  grid-column: 1 / -1;
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr)) auto;
  gap: 12px;
  align-items: center;
}
.staff-orders__presets {
  flex-wrap: nowrap;
  gap: var(--mf-space-2);
}
.staff-orders__scope-error {
  grid-column: 1 / -1;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--mf-space-2);
  font-size: 14px;
}
@media (max-width: 1100px) {
  .staff-orders__filters,
  .staff-orders__more {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .staff-orders__filters > :first-child,
  .staff-orders__buttons {
    grid-column: 1 / -1;
  }
}
@media (max-width: 600px) {
  .staff-orders__summary,
  .staff-orders__filters,
  .staff-orders__more {
    grid-template-columns: minmax(0, 1fr);
  }
  .staff-orders__buttons {
    flex-wrap: wrap;
  }
}
.staff-order {
  display: grid;
  gap: 24px;
}
.staff-order__header {
  display: flex;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;
}
.staff-order__state {
  display: grid;
  justify-items: end;
  gap: 8px;
}
.staff-order__state strong {
  font-size: 24px;
}
.staff-order__title {
  font-size: 20px;
  margin-bottom: 12px;
}
@media (max-width: 600px) {
  .staff-order__state {
    justify-items: start;
  }
}
</style>
