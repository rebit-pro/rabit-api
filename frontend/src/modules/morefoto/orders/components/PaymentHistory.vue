<script setup lang="ts">
import type { PaymentAttempt } from '../types';
import { formatMoment, paymentLabels } from '../formatters';
import { money } from '../../commerce/money';
defineProps<{ attempts: PaymentAttempt[] }>();
</script>
<template>
  <section v-if="attempts.length" class="mf-panel">
    <h2 class="payment-history__title">История оплаты</h2>
    <ol class="payment-history">
      <li v-for="(attempt, index) in attempts" :key="attempt.id" class="payment-history__item" data-testid="payment-attempt">
        <div class="payment-history__heading">
          <strong>Попытка {{ index + 1 }}</strong
          ><strong>{{ money(attempt.amount) }}</strong>
        </div>
        <p class="payment-history__status">{{ paymentLabels[attempt.status] }}</p>
        <p class="payment-history__date">Начата {{ formatMoment(attempt.startedAt) }} мск</p>
        <p v-if="attempt.completedAt" class="payment-history__date">Результат {{ formatMoment(attempt.completedAt) }} мск</p>
      </li>
    </ol>
  </section>
</template>
<style scoped>
.payment-history__title {
  font-size: 20px;
}
.payment-history {
  list-style: none;
  padding: 0;
  margin: 12px 0 0;
}
.payment-history__item {
  padding: 18px 0;
  border-bottom: 1px solid var(--mf-color-border);
  font-size: 14px;
  line-height: 1.6;
}
.payment-history__item:last-child {
  border-bottom: 0;
  padding-bottom: 0;
}
.payment-history__heading {
  display: flex;
  justify-content: space-between;
  gap: 16px;
}
.payment-history__status {
  color: var(--mf-color-link);
  margin: 8px 0;
}
.payment-history__date {
  font-size: 12px;
  color: var(--mf-color-text-secondary);
}
</style>
