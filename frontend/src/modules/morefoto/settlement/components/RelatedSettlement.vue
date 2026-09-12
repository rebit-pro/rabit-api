<script setup lang="ts">
import { computed } from 'vue';
import type { SettlementState } from '../types';
import { formatMoment } from '../../handoff/display';
import { money } from '../../commerce/money';
const props = defineProps<{ state?: SettlementState; supportId: string }>();
const events = computed(() => {
  const s = props.state;
  if (!s) return [];
  return [
    ...s.corrections.map((c) => ({ ...c, label: c.kind === 'contacts' ? 'Контакты исправлены' : 'Позиция исправлена' })),
    ...s.refunds.map((r) => ({
      ...r,
      label: 'Возврат ' + money(r.amount) + ' · ' + { pending: 'обрабатывается', confirmed: 'подтверждён', failed: 'ошибка' }[r.status]
    })),
    ...s.decisions.map((d) => ({ ...d, label: d.decision === 'fulfil' ? 'Исполнение согласовано' : 'Согласован возврат' })),
    ...s.recoveries.map((r) => ({
      ...r,
      label: r.status === 'sent' ? 'Повторное письмо: отправка имитирована' : 'Повторное письмо: ошибка доставки'
    }))
  ]
    .filter((e) => e.supportId === props.supportId)
    .sort((a, b) => s.operations.findIndex((o) => o.id === a.id) - s.operations.findIndex((o) => o.id === b.id));
});
</script>
<template>
  <div v-if="events.length" class="related-sale" data-testid="related-settlement">
    <h3>Решения по этому обращению</h3>
    <div v-for="e in events" :key="e.id + e.label" class="related-sale__event">
      <strong>{{ e.label }}</strong>
      <p class="mf-muted">{{ e.actorName }} · {{ formatMoment(e.at) }}</p>
      <p>{{ e.reason }}</p>
    </div>
  </div>
</template>
<style scoped>
.related-sale {
  margin-top: 24px;
  overflow-wrap: anywhere;
}
.related-sale h3 {
  font-size: 18px;
}
.related-sale__event {
  border-top: 1px solid #dce3e8;
  padding: 16px 0;
  margin-top: 12px;
}
.related-sale__event p {
  margin-top: 8px;
  white-space: pre-wrap;
  line-height: 1.6;
}
</style>
