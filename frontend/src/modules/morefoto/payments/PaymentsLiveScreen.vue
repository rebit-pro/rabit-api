<script setup lang="ts">
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import { money } from '../commerce/money';
import { formatMoment } from '../orders/formatters';
import { attemptStatusLabels, confirmationLabels, paymentMethodLabels } from '../orders/live/payment-rules';
import type { AttemptStatus } from '../orders/live/payment-types';
import MfStatus from '@/components/status/MfStatus.vue';
import type { StatusTone } from '@/components/status/tones';
import { usePaymentRegistry } from './usePaymentRegistry';
const route = useRoute();
const { attemptId, filters, page, card, loading, error, reload, apply, reset } = usePaymentRegistry();
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
</script>
<template>
  <header class="payments__heading">
    <p class="mf-eyebrow">ДЕНЬГИ</p>
    <h1>{{ attemptId ? 'Платёж' : 'Платежи' }}</h1>
    <p class="mf-muted">
      Попытки оплаты заказов вашей области. Оплату подтверждает только ЮKassa; ключи покупателей здесь не показываются.
    </p>
  </header>
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
      <div class="mf-actions">
        <v-btn type="submit" color="primary" density="compact" :loading="loading">Найти</v-btn>
        <v-btn variant="text" density="compact" :disabled="!hasFilters" @click="reset">Сбросить</v-btn>
      </div>
      <div class="payments__refine">
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
      </div>
    </form>
    <p v-if="loading && !page" role="status">Загружаем платежи…</p>
    <template v-if="page">
      <p class="payments__total" role="status">Найдено платежей: {{ page.meta.total }}</p>
      <p v-if="!page.items.length" class="mf-panel mf-muted">Платежей по этим условиям нет.</p>
      <ul v-else class="payments__list">
        <li v-for="payment in page.items" :key="payment.id" class="mf-panel" data-testid="payment-row">
          <div>
            <RouterLink :to="{ path: '/cabinet/payments/' + payment.id, query: route.query }" class="payments__number">{{
              payment.orderNumber
            }}</RouterLink>
            <p class="mf-muted">{{ payment.institutionName }} · {{ payment.groupName }}</p>
            <p class="mf-muted">{{ paymentMethodLabels[payment.paymentMethod] }} · {{ formatMoment(payment.createdAt) }}</p>
          </div>
          <div class="payments__side">
            <strong>{{ money(payment.amount) }}</strong>
            <MfStatus :tone="tone[payment.status]">{{ attemptStatusLabels[payment.status] }}</MfStatus>
            <MfStatus v-if="payment.latePayment" tone="warning">Поздняя</MfStatus>
          </div>
        </li>
      </ul>
      <v-pagination
        v-if="page.meta.totalPages > 1"
        :model-value="page.meta.page"
        :length="page.meta.totalPages"
        total-visible="5"
        @update:model-value="apply"
      />
    </template>
  </template>
</template>
<style scoped>
.payments__heading {
  margin-bottom: 24px;
}
.payments__filters {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  gap: 12px;
  align-items: center;
  padding: 16px;
  margin-bottom: 24px;
}
.payments__refine {
  grid-column: 1 / -1;
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 12px;
}
.payments__total {
  margin-bottom: 12px;
}
.payments__list,
.payments__plain {
  display: grid;
  gap: 12px;
  padding: 0;
  list-style: none;
}
.payments__list li {
  display: flex;
  justify-content: space-between;
  gap: 16px;
}
.payments__number {
  font-weight: 600;
}
.payments__side,
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
@media (max-width: 900px) {
  .payments__refine {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
@media (max-width: 600px) {
  .payments__filters,
  .payments__refine,
  .payments__facts > div {
    grid-template-columns: minmax(0, 1fr);
  }
  .payments__list li,
  .payments__card-header {
    flex-direction: column;
  }
  .payments__side,
  .payments__state {
    justify-items: start;
  }
}
</style>
