<script setup lang="ts">
import type { SaleCommand, SaleErrors, SaleOrder } from '../types';
defineProps<{ command: SaleCommand; errors: SaleErrors; order: SaleOrder }>();
defineEmits<{ change: [value: Partial<SaleCommand>] }>();
</script>
<template>
  <div class="sale-fields">
    <v-select
      v-if="order.supportRequests?.length"
      :model-value="command.supportId"
      label="Связанное обращение"
      aria-label="Связанное обращение"
      :items="[{ value: '', title: 'Без отдельного обращения' }, ...order.supportRequests.map((r) => ({ value: r.id, title: r.number }))]"
      @update:model-value="$emit('change', { supportId: $event })"
    />
    <v-textarea
      :model-value="command.reason"
      label="Причина операции"
      rows="3"
      :error-messages="errors.reason"
      :aria-invalid="!!errors.reason"
      @update:model-value="$emit('change', { reason: $event })"
    />
    <v-checkbox
      :model-value="command.confirmed"
      label="Подтверждаю изменения и последствия для оплаты, файлов и исполнения"
      :error-messages="errors.confirmed"
      :aria-invalid="!!errors.confirmed"
      @update:model-value="$emit('change', { confirmed: !!$event })"
    />
    <p class="mf-muted">Демонстрация: реальные возвраты и сообщения не выполняются. История сохранится в этом браузере.</p>
  </div>
</template>
