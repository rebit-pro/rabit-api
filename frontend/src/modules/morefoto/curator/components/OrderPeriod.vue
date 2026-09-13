<script setup lang="ts">
import type { OrderPeriod } from '../types';
import { formatMoment } from '../../handoff/display';
defineProps<{ period: OrderPeriod }>();
</script>
<template>
  <section class="mf-panel order-period" data-testid="order-period">
    <h2>Сроки группы</h2>
    <div>
      <p>
        Приём заказов до <strong>{{ formatMoment(period.closesAt) }}</strong>
      </p>
      <p>
        Доставка в учреждение до <strong>{{ formatMoment(period.deliveryAt) }}</strong>
      </p>
    </div>
    <p v-if="period.extensions.length" class="mf-muted">Срок продлён по согласованию с куратором. Даты относятся ко всей группе.</p>
    <p class="mf-muted">
      {{ period.state === 'closed' ? 'Приём завершён.' : period.state === 'preparing' ? 'Приём ещё не открыт.' : 'Приём открыт.' }}
      Оплаченные заказы сохраняются.
    </p>
  </section>
</template>
<style scoped>
.order-period h2 {
  font-size: 20px;
}
.order-period div {
  display: grid;
  gap: 10px;
  margin: 20px 0;
}
.order-period p {
  line-height: 1.6;
  overflow-wrap: anywhere;
}
</style>
