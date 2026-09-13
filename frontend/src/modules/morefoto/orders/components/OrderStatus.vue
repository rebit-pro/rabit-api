<script setup lang="ts">
import { lateDecision } from '../../settlement/rules';
import { computed } from 'vue';
import type { OrderSnapshot } from '../types';
import { paymentLabels, productionLabels, formatMoment } from '../formatters';
import { paymentContext } from '../services/payment';
const props = defineProps<{ order: OrderSnapshot }>();
const context = computed(() => paymentContext(props.order));
const linkLabel = computed(() =>
  props.order.paymentStatus === 'paid'
    ? 'Посмотреть результат оплаты'
    : props.order.paymentStatus === 'pending'
      ? 'Проверить оплату'
      : context.value.accepting
        ? 'Перейти к тестовой оплате'
        : 'Посмотреть статус оплаты'
);
</script>
<template>
  <dl class="order-status">
    <div class="order-status__row">
      <dt>Оплата</dt>
      <dd data-testid="order-payment-status">{{ paymentLabels[order.paymentStatus] }}</dd>
    </div>
    <div class="order-status__row">
      <dt>Изготовление</dt>
      <dd data-testid="order-production-status">{{ productionLabels[order.productionStatus] }}</dd>
    </div>
  </dl>
  <p v-if="order.paidAt" class="order-status__note">Оплата подтверждена {{ formatMoment(order.paidAt) }} мск.</p>
  <p v-if="order.latePayment" class="order-status__late" role="status" data-testid="order-late-decision">
    <template v-if="lateDecision(order)"
      >Поздняя оплата сохранена: {{ lateDecision(order) === 'fulfil' ? 'исполнение согласовано' : 'согласован возврат' }}.</template
    >
    <template v-else>Поздняя оплата сохранена. Исполнение или возврат согласует куратор.</template>
  </p>
  <p v-else-if="order.paymentStatus === 'pending'" class="order-status__note">
    Результат неизвестен. Повторная оплата недоступна до окончательного ответа.
  </p>
  <p v-else-if="order.paymentStatus === 'paid'" class="order-status__note">
    Оплата и изготовление учитываются отдельно. Печатные позиции передаются в производство после закрытия группы.
  </p>
  <p v-else-if="!context.accepting" class="order-status__note">Приём закрыт. Новая оплата недоступна.</p>
  <p v-else class="order-status__note">Перед началом оплаты проверим актуальные цену и доступность продукции.</p>
  <v-btn :to="'/orders/access/' + order.accessKey + '/payment'" color="primary" block class="mt-5">{{ linkLabel }}</v-btn>
</template>
<style scoped>
.order-status {
  display: grid;
  gap: 16px;
  margin: 24px 0;
  font-size: 14px;
  line-height: 1.6;
}
.order-status__row {
  display: grid;
  grid-template-columns: 110px minmax(0, 1fr);
  gap: 14px;
}
.order-status__row dt {
  color: #5e6872;
}
.order-status__row dd {
  margin: 0;
  overflow-wrap: anywhere;
}
.order-status__note {
  font-size: 13px;
  line-height: 1.7;
  color: #5e6872;
}
.order-status__late {
  padding: 12px;
  background: #fff3df;
  border-radius: 6px;
  font-size: 13px;
  line-height: 1.7;
}
</style>
