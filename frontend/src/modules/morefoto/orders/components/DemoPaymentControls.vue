<script setup lang="ts">
import type { PaymentOutcome, PaymentResult } from '../types';
import { formatMoment } from '../formatters';
const outcome = defineModel<PaymentOutcome>('outcome', { required: true });
defineProps<{ busy: boolean; pending: boolean; paid: boolean; now: string }>();
defineEmits<{ respond: [result: PaymentResult]; time: [value: 'before' | 'after'] }>();
</script>
<template>
  <section class="mf-panel payment-demo" aria-labelledby="payment-demo-title">
    <p class="mf-eyebrow">ДЕМОНСТРАЦИЯ</p>
    <h2 id="payment-demo-title" class="payment-demo__title">Сценарий проверки</h2>
    <v-radio-group v-if="!pending && !paid" v-model="outcome" label="Результат тестовой попытки" :disabled="busy" class="mt-4">
      <v-radio label="Успешная оплата" value="paid" />
      <v-radio label="Отказ в оплате" value="declined" />
      <v-radio label="Ожидание подтверждения" value="pending" />
      <v-radio label="Потеря связи после начала оплаты" value="connection-lost" />
    </v-radio-group>
    <template v-if="pending">
      <p class="payment-demo__note mt-4">Текущая попытка остаётся в ожидании. Можно имитировать поступление окончательного результата.</p>
      <div class="payment-demo__buttons">
        <v-btn variant="outlined" color="primary" :disabled="busy" @click="$emit('respond', 'paid')">Имитировать подтверждение</v-btn>
        <v-btn variant="text" color="secondary" :disabled="busy" @click="$emit('respond', 'declined')">Имитировать отказ</v-btn>
      </div>
    </template>
    <details class="payment-demo__time">
      <summary>Время и закрытие группы</summary>
      <p class="payment-demo__note mt-3">{{ formatMoment(now) }} мск · демонстрационные часы</p>
      <p class="payment-demo__note mt-2">Изменение времени действует для демогрупп в этом браузере. Подтверждённые платежи сохраняются.</p>
      <div class="payment-demo__buttons">
        <v-btn variant="outlined" :disabled="busy" @click="$emit('time', 'after')">После закрытия группы</v-btn>
        <v-btn variant="text" :disabled="busy" @click="$emit('time', 'before')">Вернуть время оформления</v-btn>
      </div>
    </details>
  </section>
</template>
<style scoped>
.payment-demo {
  border-color: var(--mf-color-accent);
  background: var(--mf-color-bg);
}
.payment-demo__title {
  font-size: 20px;
  margin-top: 8px;
}
.payment-demo__note {
  font-size: 13px;
  color: var(--mf-color-text-secondary);
  line-height: 1.7;
}
.payment-demo__buttons {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin-top: 18px;
}
.payment-demo__time {
  border-top: 1px solid var(--mf-color-border);
  padding-top: 18px;
  margin-top: 18px;
  font-size: 14px;
}
.payment-demo__time summary {
  cursor: pointer;
  color: var(--mf-color-link);
}
.payment-demo__buttons :deep(.v-btn) {
  max-width: 100%;
  height: auto;
  min-height: 44px;
  padding: 10px 14px;
  white-space: normal;
}
</style>
