<script setup lang="ts">
import { formatMoment } from '../formatters';
import type { LiveBuyer, OrderPeriod } from '../live/types';
defineProps<{ period: OrderPeriod; buyer: LiveBuyer }>();
const states = { preparing: 'Ещё не открыт', open: 'Идёт приём заказов', closed: 'Приём закрыт' };
</script>
<template>
  <section class="mf-panel order-facts" data-testid="order-period">
    <h2>Сроки группы</h2>
    <dl>
      <div>
        <dt>Приём заказов</dt>
        <dd>
          {{ states[period.state] }}<template v-if="period.closesAt"> · до {{ formatMoment(period.closesAt) }} мск</template>
        </dd>
      </div>
      <div v-if="period.deliveryDueAt">
        <dt>Печатная продукция</dt>
        <dd>Передача в учреждение до {{ formatMoment(period.deliveryDueAt) }} мск</dd>
      </div>
    </dl>
  </section>
  <section class="mf-panel order-facts" data-testid="order-contacts">
    <h2>Контакты покупателя</h2>
    <dl>
      <div>
        <dt>Покупатель</dt>
        <dd>{{ buyer.name }}</dd>
      </div>
      <div>
        <dt>Телефон</dt>
        <dd>{{ buyer.phone }}</dd>
      </div>
      <div>
        <dt>Email</dt>
        <dd>{{ buyer.email }}</dd>
      </div>
      <div v-if="buyer.comment">
        <dt>Комментарий</dt>
        <dd>{{ buyer.comment }}</dd>
      </div>
    </dl>
  </section>
</template>
<style scoped>
.order-facts h2 {
  font-size: 20px;
  margin-bottom: 16px;
}
.order-facts dl {
  display: grid;
  gap: 14px;
  margin: 0;
}
.order-facts dl > div {
  display: grid;
  grid-template-columns: minmax(120px, 180px) minmax(0, 1fr);
  gap: 12px;
}
.order-facts dt {
  color: var(--mf-color-text-secondary);
}
.order-facts dd {
  margin: 0;
  overflow-wrap: anywhere;
}
@media (max-width: 600px) {
  .order-facts dl > div {
    grid-template-columns: minmax(0, 1fr);
    gap: 2px;
  }
}
</style>
