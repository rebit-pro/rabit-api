<script setup lang="ts">
import { computed } from 'vue';
import type { CaseCommand, CaseErrors, OrderPeriod } from '../types';
import { parseExtension } from '../rules';
import { calendarDays } from '../../handoff/rules';
import { formatMoment } from '../../handoff/display';
const props = defineProps<{ command: CaseCommand; errors: CaseErrors; period: OrderPeriod; groupName: string }>();
defineEmits<{ change: [value: Partial<CaseCommand>] }>();
const next = computed(() => parseExtension(props.command.closesAt, props.period.now, props.period.closesAt));
</script>
<template>
  <p class="mb-5">
    Продление действует для всей группы <strong>{{ groupName }}</strong
    >. Заказы, принятые ранее, сохраняют состав, стоимость и срок получения электронных файлов.
  </p>
  <p class="mf-muted mb-5">Время демонстрации: {{ formatMoment(period.now) }}</p>
  <v-text-field
    :model-value="command.closesAt"
    label="Новый срок приёма (МСК)"
    type="datetime-local"
    :error-messages="errors.closesAt"
    :aria-invalid="!!errors.closesAt"
    @update:model-value="$emit('change', { closesAt: $event })"
  />
  <div class="work-periods" aria-live="polite">
    <div>
      <h3>До продления</h3>
      <p>Приём до {{ formatMoment(period.closesAt) }}</p>
      <p>Доставка до {{ formatMoment(period.deliveryAt) }}</p>
    </div>
    <div>
      <h3>После продления</h3>
      <p>Приём до {{ formatMoment(next) }}</p>
      <p>Доставка до {{ formatMoment(next ? calendarDays(next, 7) : null) }}</p>
    </div>
  </div>
  <v-textarea
    :model-value="command.reason"
    label="Причина продления"
    rows="3"
    counter="500"
    :error-messages="errors.reason"
    :aria-invalid="!!errors.reason"
    @update:model-value="$emit('change', { reason: $event })"
  /><v-checkbox
    :model-value="command.confirmed"
    label="Подтверждаю новые сроки приёма и доставки для всей группы"
    :error-messages="errors.confirmed"
    :aria-invalid="!!errors.confirmed"
    @update:model-value="$emit('change', { confirmed: !!$event })"
  />
</template>
