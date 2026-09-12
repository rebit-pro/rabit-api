<script setup lang="ts">
import { nextTick, useTemplateRef, watch } from 'vue';
import type { OrderSnapshot } from '../types';
import { usePayment } from '../composables/usePayment';
import { money } from '../../commerce/money';
import { formatMoment } from '../formatters';
import PaymentStatus from './PaymentStatus.vue';
import DemoPaymentControls from './DemoPaymentControls.vue';
import PaymentHistory from './PaymentHistory.vue';
import OrderComposition from './OrderComposition.vue';
const props = defineProps<{ order: OrderSnapshot }>();
const { busy, error, notice, outcome, quote, revisedQuote, reviewed, context, pending, paid, canStart, start, respond, check, time } =
  usePayment(() => props.order);
const feedback = useTemplateRef<HTMLElement>('feedback');
watch(error, async (value) => {
  if (value) {
    await nextTick();
    feedback.value?.focus();
  }
});
</script>
<template>
  <div class="payment-layout">
    <div class="payment-sections">
      <section class="mf-panel">
        <PaymentStatus :order="order" />
        <p class="payment-total-label">Сумма заказа</p>
        <p class="payment-total" data-testid="payment-total">{{ money(quote.total) }}</p>
        <div v-if="error" ref="feedback" tabindex="-1" role="alert" class="payment-feedback">
          <p>{{ error }}</p>
          <p v-if="revisedQuote" class="mt-2">Было {{ money(order.quote.total) }}. Новый итог {{ money(revisedQuote.total) }}.</p>
        </div>
        <p v-if="notice" role="status" class="payment-notice">{{ notice }}</p>
        <template v-if="!paid && !pending">
          <v-alert v-if="!context.accepting" type="warning" variant="tonal" class="mb-4"
            >Приём группы закрыт. Новая попытка оплаты недоступна.</v-alert
          >
          <p v-else class="payment-note mb-4">Приём до {{ formatMoment(context.closesAt) }} мск.</p>
          <v-alert v-if="quote.invalid.length" type="warning" variant="tonal" class="mb-4"
            >Часть продукции недоступна. Оплата заблокирована; вернитесь к заказу.</v-alert
          >
          <v-checkbox
            v-if="revisedQuote && !quote.invalid.length"
            v-model="reviewed"
            label="Новый состав и итог проверены"
            :disabled="busy"
            color="primary"
            hide-details
            class="mb-4"
          />
          <v-btn color="primary" block :loading="busy" :disabled="!canStart" data-testid="pay-demo" @click="start">{{
            order.paymentStatus === 'declined' ? 'Повторить тестовую оплату' : 'Оплатить тестово'
          }}</v-btn>
          <p class="payment-note mt-4">Тестовая операция без списания денег. Карта и банковские данные не запрашиваются.</p>
        </template>
        <v-btn v-if="pending" color="primary" block :loading="busy" :disabled="busy" @click="check">Проверить статус оплаты</v-btn>
        <v-btn :to="'/orders/access/' + order.accessKey" :variant="paid ? 'flat' : 'text'" color="primary" block class="mt-4"
          >Вернуться к заказу</v-btn
        >
      </section>
      <DemoPaymentControls
        v-model:outcome="outcome"
        :busy="busy"
        :pending="pending"
        :paid="paid"
        :now="context.now"
        @respond="respond"
        @time="time"
      />
    </div>
    <div class="payment-sections">
      <OrderComposition :quote="quote" />
      <PaymentHistory :attempts="order.paymentAttempts ?? []" />
    </div>
  </div>
</template>
<style scoped>
.payment-layout {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(300px, 420px);
  gap: var(--mf-space-6);
  align-items: start;
}
.payment-sections {
  display: grid;
  gap: 24px;
}
.payment-total-label {
  font-size: 13px;
  color: #5e6872;
  margin: 28px 0 6px;
}
.payment-total {
  font-size: 40px;
  line-height: 1.3;
  font-weight: 600;
  color: #24658a;
  margin-bottom: 24px;
}
.payment-note {
  font-size: 13px;
  line-height: 1.7;
  color: #5e6872;
}
.payment-feedback {
  padding: 16px;
  background: #fff3df;
  border-radius: 6px;
  font-size: 14px;
  line-height: 1.7;
  margin-bottom: 20px;
}
.payment-notice {
  padding: 16px;
  background: #eaf3f9;
  color: #24658a;
  border-radius: 6px;
  font-size: 14px;
  line-height: 1.7;
  margin-bottom: 20px;
}
@media (max-width: 1000px) {
  .payment-layout {
    grid-template-columns: minmax(0, 1fr);
  }
}
</style>
