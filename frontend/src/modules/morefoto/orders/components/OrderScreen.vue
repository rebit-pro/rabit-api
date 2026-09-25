<script setup lang="ts">
import OrderPhysicalDelivery from '../../shipping/components/OrderPhysicalDelivery.vue';
import SettlementPanel from '../../settlement/components/SettlementPanel.vue';
import OrderPeriod from '../../curator/components/OrderPeriod.vue';
import { useOrderPeriod } from '../../curator/useOrderPeriod';
import { useOrder } from '../composables/useOrder';
import { formatMoment } from '../formatters';
import { money } from '../../commerce/money';
import OrderComposition from './OrderComposition.vue';
import OrderAfterSales from './OrderAfterSales.vue';
import OrderSupport from './OrderSupport.vue';
import OrderContacts from './OrderContacts.vue';
import OrderAccessLinks from './OrderAccessLinks.vue';
import OrderStatus from './OrderStatus.vue';
import PaymentHistory from './PaymentHistory.vue';
const { order, loading, error, unavailable, reload } = useOrder();
const period = useOrderPeriod(() => order.value?.groupId ?? '');
</script>
<template>
  <main class="mf-main order-page">
    <v-skeleton-loader v-if="loading" type="heading, article" />
    <section v-else-if="error" class="mf-panel mf-empty">
      <h1>
        {{ unavailable ? 'Заказ недоступен' : 'Не удалось открыть заказ' }}
      </h1>
      <p class="mf-muted my-5">{{ error }}</p>
      <v-btn v-if="!unavailable" color="primary" @click="reload">Повторить загрузку</v-btn>
      <p v-else class="mf-muted">
        Номер заказа не заменяет личную ссылку. В демонстрации данные доступны в браузере, где заказ оформлялся.
      </p>
    </section>
    <template v-else-if="order">
      <RouterLink :to="'/g/' + order.galleryToken" class="mf-back">← К фотографиям</RouterLink>
      <header class="mf-page-heading">
        <p class="mf-eyebrow">ВАШ ЗАКАЗ</p>
        <h1>Заказ {{ order.number }}</h1>
        <p class="mf-muted">
          {{ order.institutionName }} · {{ order.groupName }} ·
          {{ order.shootName }}
        </p>
        <p class="order-created">Создан {{ formatMoment(order.createdAt) }} мск</p>
      </header>
      <v-alert type="info" variant="tonal" class="mb-6"
        >Демонстрационный заказ. Платежи, изготовление и отправка сообщений проверяются на тестовых данных.</v-alert
      >
      <div class="order-layout">
        <div class="order-sections">
          <SettlementPanel
            v-if="
              order.settlement &&
              (order.settlement.corrections.length ||
                order.settlement.refunds.length ||
                order.settlement.decisions.length ||
                order.settlement.recoveries.length)
            "
            :order="order"
          /><OrderPeriod :period="period" /><OrderPhysicalDelivery :order="order" /><OrderAfterSales
            :key="order.accessKey"
            :order="order"
          /><OrderComposition :quote="order.quote" /><OrderContacts :order="order" /><PaymentHistory
            :attempts="order.paymentAttempts ?? []"
          />
          <OrderSupport :key="order.accessKey" :order="order" />
        </div>
        <aside class="mf-panel order-summary">
          <p class="mf-eyebrow">ИТОГ ЗАКАЗА</p>
          <p class="order-total" data-testid="order-total">
            {{ money(order.quote.total) }}
          </p>
          <OrderStatus :order="order" /><OrderAccessLinks :order="order" />
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
@media (max-width: 1000px) {
  .order-summary {
    grid-row: 1;
  }
  .order-layout {
    grid-template-columns: minmax(0, 1fr);
  }
}
</style>
