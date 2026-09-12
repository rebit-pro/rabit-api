<script setup lang="ts">
import type { OrderSnapshot } from '../types';
import { getDemoNow, setDemoNow } from '../../mocks/clock';
import { writeDemo } from '../../mocks/storage';
import { failNextRequest } from '../../mocks/runtime';
import { downloadDeadline } from '../delivery/rules';
import { formatMoment } from '../formatters';
import { shallowRef } from 'vue';
const props = defineProps<{ order: OrderSnapshot; busy: boolean }>();
const notice = shallowRef('');
function failure(target: 'receipt' | 'filesEmail' | 'request') {
  if (target === 'request') failNextRequest();
  else writeDemo('delivery:fail-once:' + target, true);
  notice.value = 'Следующее соответствующее действие завершится тестовой ошибкой. После него можно повторить попытку.';
}
function time(expired: boolean) {
  if (!props.order.paidAt) return;
  setDemoNow(expired ? downloadDeadline(props.order.paidAt) : props.order.paidAt);
  notice.value = expired ? 'Демонстрационное время переведено на окончание доступа.' : 'Восстановлено время покупки.';
}
function max(available: boolean) {
  writeDemo('checkout:capabilities', { maxAvailable: available });
  notice.value = available ? 'Демонстрационный MAX доступен.' : 'Демонстрационный MAX недоступен.';
}
</script>
<template>
  <details class="mf-panel delivery-demo">
    <summary>Демонстрация: срок доступа и ошибки доставки</summary>
    <p class="mf-muted delivery-demo__note mt-4">
      {{ formatMoment(getDemoNow()) }} мск · тестовые часы этого браузера. Оплата и состав заказа сохраняются.
    </p>
    <div v-if="order.paidAt" class="delivery-demo__actions">
      <v-btn variant="outlined" :disabled="busy" @click="time(true)">Срок скачивания истёк</v-btn>
      <v-btn variant="text" :disabled="busy" @click="time(false)">Вернуть время покупки</v-btn>
    </div>
    <div class="delivery-demo__actions">
      <v-btn variant="outlined" :disabled="busy" @click="failure('request')">Сбой следующего запроса</v-btn>
      <v-btn variant="outlined" :disabled="busy" @click="failure('receipt')">Сбой доставки чека</v-btn>
      <v-btn variant="outlined" :disabled="busy" @click="failure('filesEmail')">Сбой письма со ссылкой</v-btn>
      <v-btn variant="text" :disabled="busy" @click="max(true)">Включить тестовый MAX</v-btn>
      <v-btn variant="text" :disabled="busy" @click="max(false)">Отключить тестовый MAX</v-btn>
    </div>
    <p v-if="notice" class="mf-muted delivery-demo__note mt-4" role="status">
      {{ notice }}
    </p>
  </details>
</template>
<style scoped>
.delivery-demo {
  font-size: var(--mf-text-small);
}
.delivery-demo summary {
  color: rgb(var(--v-theme-primary));
  cursor: pointer;
  min-height: var(--mf-touch-size);
  display: list-item;
  align-content: center;
}
.delivery-demo__note {
  font-size: var(--mf-text-small);
}
.delivery-demo__actions {
  display: flex;
  flex-wrap: wrap;
  gap: var(--mf-space-2);
  margin-top: var(--mf-space-4);
}
</style>
