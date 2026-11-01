<script setup lang="ts">
import { money } from '../rules';
import type { OrderNameSales } from '../api';

defineProps<{ items: OrderNameSales[] }>();
const statuses: Record<string, string> = { paid: 'Оплачен', pending: 'Ожидает оплаты', declined: 'Оплата отклонена', unpaid: 'Не оплачен' };
</script>

<template>
  <div class="report-orders" data-testid="report-orders">
    <p v-if="items.length === 0" class="mf-muted">Заказов пока нет.</p>
    <article v-for="order in items" :key="order.id" class="report-orders__row" data-testid="report-order">
      <h3>{{ order.number }}</h3>
      <dl>
        <div>
          <dt>Имя и фамилия ребёнка</dt>
          <dd>{{ order.firstName && order.lastName ? `${order.lastName} ${order.firstName}` : 'Не указано' }}</dd>
        </div>
        <div>
          <dt>Оплата</dt>
          <dd>{{ order.latePayment ? 'Поздняя оплата' : (statuses[order.paymentStatus] ?? order.paymentStatus) }}</dd>
        </div>
        <div>
          <dt>Сумма заказа</dt>
          <dd>{{ money(order.total) }}</dd>
        </div>
      </dl>
    </article>
  </div>
</template>

<style scoped>
.report-orders {
  display: grid;
  border: 1px solid var(--mf-color-border);
  border-radius: var(--mf-radius-sm);
}
.report-orders__row {
  padding: var(--mf-space-3);
  border-bottom: 1px solid var(--mf-color-border);
  overflow-wrap: anywhere;
}
.report-orders__row h3 {
  margin-bottom: var(--mf-space-2);
  font-size: var(--mf-text-base);
}
.report-orders__row dl {
  display: grid;
  grid-template-columns: minmax(160px, 2fr) repeat(2, minmax(110px, 1fr));
  gap: var(--mf-space-3);
  margin: 0;
}
.report-orders__row dt {
  font-size: var(--mf-text-xs);
  color: var(--mf-color-text-secondary);
}
.report-orders__row dd {
  margin: 0;
}
.report-orders__row:last-child {
  border-bottom: 0;
}
@media (max-width: 700px) {
  .report-orders__row dl {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .report-orders__row dl > div:first-child {
    grid-column: 1 / -1;
  }
}
</style>
