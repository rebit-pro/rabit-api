<script setup lang="ts">
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { money } from '../../commerce/money';
import OrderComposition from '../../orders/components/OrderComposition.vue';
import OrderLiveFacts from '../../orders/components/OrderLiveFacts.vue';
import { formatMoment, paymentLabels, productionLabels } from '../../orders/formatters';
import { orderQuoteAsCart } from '../../orders/live/rules';
import { useStaffOrders } from '../useStaffOrders';
const route = useRoute();
const auth = useAuthStore();
const { orderId, filters, page, card, loading, error, reload, apply, reset } = useStaffOrders();
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
const hasFilters = computed(() =>
  (['q', 'paymentStatus', 'productionStatus', 'dateFrom', 'dateTo'] as const).some((key) => filters[key] !== '')
);
const photoCodes = computed(() => card.value?.correctionPhotos.map((photo) => photo.code).join(', ') ?? '');
</script>
<template>
  <header class="staff-orders__heading">
    <p class="mf-eyebrow">РАБОТА С УЧРЕЖДЕНИЕМ</p>
    <h1>{{ orderId ? 'Заказ' : 'Заказы' }}</h1>
    <p class="mf-muted">Заказы покупателей в вашей области. Ключи доступа покупателей здесь не показываются.</p>
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
          <v-chip size="small">{{ paymentLabels[card.paymentStatus] }}</v-chip>
          <v-chip size="small" variant="outlined">{{ productionLabels[card.productionStatus] }}</v-chip>
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
      <div class="mf-actions">
        <v-btn type="submit" color="primary" density="compact" :loading="loading">Найти</v-btn>
        <v-btn variant="text" density="compact" :disabled="!hasFilters" @click="reset">Сбросить</v-btn>
      </div>
      <div class="staff-orders__refine">
        <v-select v-model="filters.paymentStatus" :items="paymentOptions" label="Оплата" density="compact" hide-details />
        <v-select v-model="filters.productionStatus" :items="productionOptions" label="Изготовление" density="compact" hide-details />
        <v-text-field v-model="filters.dateFrom" type="date" label="Создан с" aria-label="Создан с" density="compact" hide-details />
        <v-text-field v-model="filters.dateTo" type="date" label="Создан по" aria-label="Создан по" density="compact" hide-details />
      </div>
    </form>
    <p v-if="loading && !page" role="status">Загружаем заказы…</p>
    <template v-if="page">
      <p class="staff-orders__total" role="status">Найдено заказов: {{ page.meta.total }}</p>
      <p v-if="!page.items.length" class="mf-panel mf-muted">Заказов по этим условиям нет.</p>
      <ul v-else class="staff-orders__list">
        <li v-for="order in page.items" :key="order.id" class="mf-panel" data-testid="staff-order">
          <div class="staff-orders__main">
            <RouterLink :to="{ path: '/cabinet/orders/' + order.id, query: route.query }" class="staff-orders__number">{{
              order.number
            }}</RouterLink>
            <p class="mf-muted">{{ order.institutionName }} · {{ order.groupName }}</p>
            <p>{{ order.buyer.name }} · {{ order.buyer.phone }}</p>
          </div>
          <div class="staff-orders__side">
            <strong>{{ money(order.quote.total) }}</strong>
            <span class="mf-muted">{{ formatMoment(order.createdAt) }}</span>
            <v-chip size="small">{{ paymentLabels[order.paymentStatus] }}</v-chip>
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
.staff-orders__heading {
  margin-bottom: 24px;
}
.staff-orders__filters {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  gap: 12px;
  align-items: center;
  padding: 16px;
  margin-bottom: 24px;
}
.staff-orders__refine {
  grid-column: 1 / -1;
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}
@media (min-width: 1280px) {
  .staff-orders__refine {
    grid-template-columns: repeat(2, minmax(0, 4fr)) repeat(2, minmax(0, 3fr));
  }
}
@media (max-width: 600px) {
  .staff-orders__filters {
    grid-template-columns: minmax(0, 1fr);
  }
  .staff-orders__filters .v-btn[type='submit'] {
    flex: 1;
  }
  .staff-orders__refine > .v-select {
    grid-column: 1 / -1;
  }
}
.staff-orders__total {
  margin-bottom: 12px;
  color: #5e6872;
}
.staff-orders__list {
  list-style: none;
  padding: 0;
  display: grid;
  gap: 12px;
  margin-bottom: 20px;
}
.staff-orders__list li {
  display: flex;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;
}
.staff-orders__main {
  min-width: 0;
  overflow-wrap: anywhere;
}
.staff-orders__number {
  font-size: 18px;
  font-weight: 600;
}
.staff-orders__side {
  display: grid;
  justify-items: end;
  gap: 6px;
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
  .staff-orders__side,
  .staff-order__state {
    justify-items: start;
  }
}
</style>
