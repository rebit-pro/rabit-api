<script setup lang="ts">
import { lateDecision } from '../../settlement/rules';
import { computed } from 'vue';
import type { OrderSnapshot } from '../types';
import { formatMoment } from '../formatters';
const props = defineProps<{ order: OrderSnapshot }>();
const state = computed(
  () =>
    ({
      unpaid: {
        type: 'info' as const,
        title: 'Заказ готов к тестовой оплате',
        text: 'Выберите сценарий ниже и запустите оплату. Реального списания не будет.'
      },
      pending: {
        type: 'info' as const,
        title: 'Ожидаем подтверждение оплаты',
        text: 'Результат текущей попытки ещё неизвестен. Проверьте статус; повторно оплачивать заказ сейчас не нужно.'
      },
      declined: {
        type: 'warning' as const,
        title: 'Тестовая оплата отклонена',
        text: 'Отказ подтверждён. Повторить оплату можно, пока открыт приём группы.'
      },
      paid: {
        type: 'success' as const,
        title: 'Тестовая оплата подтверждена',
        text: 'Заказ отмечен оплаченным в демонстрации. Реального списания не было.'
      }
    })[props.order.paymentStatus]
);
</script>
<template>
  <v-alert :type="state.type" variant="tonal" role="status" data-testid="payment-status">
    <h2 class="payment-status__title">{{ state.title }}</h2>
    <p class="payment-status__text">{{ state.text }}</p>
    <p v-if="order.paidAt" class="payment-status__text mt-2">Подтверждено {{ formatMoment(order.paidAt) }} мск.</p>
  </v-alert>
  <v-alert v-if="order.latePayment" type="warning" variant="tonal" class="mt-4" data-testid="late-payment">
    <template v-if="lateDecision(order)"
      >Поздняя оплата: {{ lateDecision(order) === 'fulfil' ? 'исполнение согласовано' : 'согласован возврат' }}. Подробности — в истории
      сопровождения.</template
    >
    <template v-else
      >Подтверждение пришло после закрытия приёма. Оплата сохранена; куратор согласует исполнение или возврат. Повторная оплата не
      требуется.</template
    >
  </v-alert>
</template>
<style scoped>
.payment-status__title {
  font-size: 18px;
  line-height: 1.5;
  margin-bottom: 8px;
}
.payment-status__text {
  font-size: 14px;
  line-height: 1.7;
}
</style>
