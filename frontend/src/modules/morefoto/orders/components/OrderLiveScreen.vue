<script setup lang="ts">
import { money } from '../../commerce/money';
import { formatMoment, livePaymentLabels as paymentLabels, productionLabels } from '../formatters';
import { useLiveOrder } from '../composables/useLiveOrder';
import { orderQuoteAsCart } from '../live/rules';
import OrderComposition from './OrderComposition.vue';
import OrderLiveFacts from './OrderLiveFacts.vue';
import OrderPaymentPanel from './OrderPaymentPanel.vue';
import { useRoute } from 'vue-router';
const { order, loading, error, missing, copied, reload, copyLink } = useLiveOrder();
const route = useRoute();
</script>
<template>
  <main class="mf-main order-page">
    <v-skeleton-loader v-if="loading" type="heading, article" />
    <section v-else-if="error" class="mf-panel mf-empty" data-testid="order-unavailable">
      <h1>{{ missing ? 'Заказ недоступен' : 'Не удалось открыть заказ' }}</h1>
      <p class="mf-muted my-5">{{ error }}</p>
      <v-btn v-if="!missing" color="primary" @click="reload">Повторить загрузку</v-btn>
    </section>
    <template v-else-if="order">
      <header class="mf-page-heading">
        <p class="mf-eyebrow">ВАШ ЗАКАЗ</p>
        <h1 data-testid="order-number">Заказ {{ order.number }}</h1>
        <p class="mf-muted">{{ order.institutionName }} · {{ order.groupName }} · {{ order.shootName }}</p>
        <p class="order-created">Создан {{ formatMoment(order.createdAt) }} мск</p>
      </header>
      <v-alert type="info" variant="tonal" class="mb-6"
        >Деньги списываются только после оплаты на странице ЮKassa. Электронные файлы и печать подключаются отдельными этапами.</v-alert
      >
      <div class="order-layout">
        <div class="order-sections">
          <OrderComposition :quote="orderQuoteAsCart(order.quote)" />
          <OrderLiveFacts :period="order.period" :buyer="order.buyer" />
        </div>
        <aside class="mf-panel order-summary">
          <p class="mf-eyebrow">ИТОГ ЗАКАЗА</p>
          <p class="order-total" data-testid="order-total">{{ money(order.quote.total) }}</p>
          <dl class="order-state">
            <div>
              <dt>Оплата</dt>
              <dd data-testid="order-payment-status">{{ paymentLabels[order.paymentStatus] }}</dd>
            </div>
            <div>
              <dt>Изготовление</dt>
              <dd data-testid="order-production-status">{{ productionLabels[order.productionStatus] }}</dd>
            </div>
          </dl>
          <OrderPaymentPanel :order="order" :order-key="String(route.params.orderKey ?? '')" />
          <p class="order-note" data-testid="order-key-expiry">
            Личная ссылка открывает только этот заказ и действует до {{ formatMoment(order.accessKeyExpiresAt) }} мск. Номер заказа доступа
            не даёт.
          </p>
          <v-btn block variant="outlined" prepend-icon="mdi-content-copy" @click="copyLink">{{
            copied ? 'Ссылка скопирована' : 'Скопировать личную ссылку'
          }}</v-btn>
        </aside>
      </div>
    </template>
  </main>
</template>
<style scoped>
.order-page {
  width: 100%;
  min-height: 100svh;
}
.order-layout {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 360px;
  gap: 28px;
  align-items: start;
}
.order-sections {
  display: grid;
  gap: 24px;
  min-width: 0;
}
.order-total {
  font-size: 36px;
  font-weight: 600;
  margin-top: 12px;
  color: var(--mf-color-link);
}
.order-created {
  font-size: 13px;
  color: var(--mf-color-text-secondary);
  line-height: 1.7;
  margin-top: 8px;
}
.order-state {
  display: grid;
  gap: 12px;
  margin: 20px 0;
}
.order-state div {
  display: flex;
  justify-content: space-between;
  gap: 12px;
}
.order-state dt {
  color: var(--mf-color-text-secondary);
}
.order-state dd {
  margin: 0;
  font-weight: 600;
  text-align: right;
}
.order-note {
  font-size: 13px;
  line-height: 1.6;
  color: var(--mf-color-text-secondary);
  margin-bottom: 16px;
}
@media (max-width: 1000px) {
  .order-summary {
    grid-row: 1;
  }
  .order-layout {
    grid-template-columns: minmax(0, 1fr);
  }
}
</style>
