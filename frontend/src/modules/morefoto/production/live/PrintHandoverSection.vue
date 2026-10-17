<script setup lang="ts">
import { computed, watch } from 'vue';
import MfStatus from '@/components/status/MfStatus.vue';
import { formatMoment } from '../../handoff/display';
import {
  handoverOrderText,
  handoverReasonTexts,
  handoverSummary,
  receiptStatusLabels,
  receiptTones
} from '../../orders/live/receipt-rules';
import { usePrintHandover } from './usePrintHandover';
const props = defineProps<{ groupId: string }>();
const { view, selected, marking, busy, error, notice, open, start, cancel, confirm } = usePrintHandover();
watch(
  () => props.groupId,
  (id) => open(id),
  { immediate: true }
);
const summary = computed(() => (view.value ? handoverSummary(view.value) : null));
const waiting = computed(() => view.value?.orders.filter((order) => order.state === 'waiting') ?? []);
const others = computed(() => view.value?.orders.filter((order) => order.state !== 'waiting') ?? []);
</script>
<template>
  <section v-if="view && summary" class="print-handover" aria-labelledby="print-handover-title" data-testid="print-handover">
    <h3 id="print-handover-title">Выдача печати</h3>
    <p class="mf-muted" data-testid="print-handover-summary">{{ summary }}</p>
    <v-alert v-if="notice" type="success" variant="tonal" density="compact" role="status">{{ notice }}</v-alert>
    <v-alert v-if="error" type="error" variant="tonal" density="compact" role="alert">{{ error }}</v-alert>
    <p v-if="!view.enabled" class="mf-muted">Чеки магазина выключены — отметка выдачи недоступна.</p>
    <template v-else-if="marking">
      <p class="print-handover__warning">
        Отметьте заказы, печать которых отдана родителям или в группу сегодня. Каждому покупателю уйдёт закрывающий чек «Полный расчёт».
        Отметку нельзя отменить.
      </p>
      <div class="print-handover__list" role="group" aria-label="Заказы, ждущие выдачи">
        <v-checkbox
          v-for="order in waiting"
          :key="order.orderId"
          v-model="selected"
          :value="order.orderId"
          :label="handoverOrderText(order)"
          density="compact"
          hide-details
          data-testid="print-handover-order"
        />
      </div>
      <div class="print-handover__actions">
        <v-btn color="primary" :loading="busy" :disabled="busy || !selected.length" data-testid="print-handover-confirm" @click="confirm"
          >Отметить выдачу ({{ selected.length }})</v-btn
        >
        <v-btn variant="text" :disabled="busy" @click="cancel">Отмена</v-btn>
      </div>
    </template>
    <v-btn
      v-else-if="waiting.length"
      variant="outlined"
      prepend-icon="mdi-package-variant-closed"
      data-testid="print-handover-start"
      @click="start"
      >Отметить выдачу</v-btn
    >
    <ul v-if="others.length" class="print-handover__done">
      <li v-for="order in others" :key="order.orderId" data-testid="print-handover-done">
        <span>{{ handoverOrderText(order) }}</span>
        <template v-if="order.state === 'handed'">
          <span class="mf-muted">выдано {{ order.handedAt ? formatMoment(order.handedAt) : '' }}</span>
          <MfStatus v-if="order.receiptStatus" :tone="receiptTones[order.receiptStatus]">{{
            receiptStatusLabels[order.receiptStatus]
          }}</MfStatus>
        </template>
        <span v-else-if="order.reason" class="print-handover__blocked">{{ handoverReasonTexts[order.reason] }}</span>
      </li>
    </ul>
  </section>
</template>
<style scoped>
.print-handover {
  display: grid;
  gap: var(--mf-space-2);
  padding: var(--mf-space-4);
  border: 1px solid var(--mf-color-border);
  border-radius: var(--mf-radius-md);
}
.print-handover h3 {
  font-size: 17px;
}
.print-handover__warning,
.print-handover__blocked {
  font-size: var(--mf-text-sm);
  line-height: 1.5;
}
.print-handover__blocked {
  color: var(--mf-tone-warning-fg);
}
.print-handover__list {
  display: grid;
}
.print-handover__actions {
  display: flex;
  flex-wrap: wrap;
  gap: var(--mf-space-2);
}
.print-handover__done {
  display: grid;
  gap: var(--mf-space-2);
  margin: 0;
  padding: 0;
  list-style: none;
  font-size: var(--mf-text-sm);
}
.print-handover__done li {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 4px 10px;
}
@media (max-width: 760px) {
  .print-handover__actions .v-btn {
    flex: 1 1 auto;
  }
}
</style>
