<script setup lang="ts">
import { formatMoment } from '../formatters';
import { formatPhone } from '../../ui/field-values';
import type { LiveBuyer, OrderPeriod } from '../live/types';
defineProps<{ period: OrderPeriod; buyer: LiveBuyer }>();
const states = {
  preparing: 'Ещё не открыт',
  open: 'Идёт приём заказов',
  digital: 'Печать закрыта, продаются электронные фото',
  closed: 'Приём закрыт'
};
</script>
<template>
  <section class="mf-panel order-facts" data-testid="order-period">
    <h2>Сроки группы</h2>
    <dl>
      <div>
        <dt>Приём заказов</dt>
        <dd>{{ states[period.state] }}</dd>
      </div>
      <div v-if="period.closesAt">
        <dt>Печать</dt>
        <dd>Заказ и оплата до {{ formatMoment(period.closesAt) }} мск</dd>
      </div>
      <div v-if="period.digitalSalesUntil">
        <dt>Электронные фото</dt>
        <dd>Покупка до {{ formatMoment(period.digitalSalesUntil) }} мск</dd>
      </div>
      <div v-if="period.deliveryDueAt">
        <dt>Передача печати</dt>
        <dd>В учреждение до {{ formatMoment(period.deliveryDueAt) }} мск</dd>
      </div>
    </dl>
  </section>
  <section class="mf-panel order-facts" data-testid="order-contacts">
    <h2>Контакты покупателя</h2>
    <dl>
      <!-- E8: orders since E8 have the email only; earlier ones keep the name and the phone. -->
      <div v-if="buyer.name">
        <dt>Покупатель</dt>
        <dd>{{ buyer.name }}</dd>
      </div>
      <div v-if="buyer.phone">
        <dt>Телефон</dt>
        <dd>{{ formatPhone(buyer.phone) }}</dd>
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
