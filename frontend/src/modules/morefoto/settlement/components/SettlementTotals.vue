<script setup lang="ts">
import type { FinancialTotals } from '../types';
import { money } from '../../commerce/money';
defineProps<{ totals: FinancialTotals; compact?: boolean; single?: boolean }>();
</script>
<template>
  <div class="sale-totals" data-testid="settlement-totals">
    <p v-if="!single">
      <span>Оплаченных заказов</span><strong>{{ totals.paidCount }}</strong>
    </p>
    <p v-if="!compact">
      <span>Оплачено</span><strong>{{ money(totals.paid) }}</strong>
    </p>
    <p>
      <span>Подтверждённые возвраты</span><strong>{{ money(totals.refunded) }}</strong>
    </p>
    <p>
      <span>Итого после возвратов</span><strong>{{ money(totals.net) }}</strong>
    </p>
    <p v-if="!compact && totals.pending">
      <span>В обработке</span><strong>{{ money(totals.pending) }}</strong>
    </p>
    <small v-if="!single" class="sale-totals__note mf-muted"
      >В демонстрации полный возврат уменьшает сумму; заказ остаётся в количестве оплаченных.</small
    >
  </div>
</template>
<style scoped>
.sale-totals {
  display: flex;
  flex-wrap: wrap;
  gap: 20px 28px;
  margin: 24px 0;
  min-width: 0;
}
.sale-totals p {
  display: grid;
  gap: 8px;
  flex: 1 1 160px;
  min-width: 0;
}
.sale-totals span {
  color: #5e6872;
  font-size: 14px;
}
.sale-totals strong {
  font-size: 20px;
  overflow-wrap: anywhere;
}
.sale-totals__note {
  flex-basis: 100%;
  line-height: 1.6;
}
</style>
