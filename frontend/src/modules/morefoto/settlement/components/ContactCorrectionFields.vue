<script setup lang="ts">
import type { SaleCommand, SaleErrors, SaleOrder } from '../types';
import { currentBuyer } from '../rules';
defineProps<{ command: SaleCommand; errors: SaleErrors; order: SaleOrder }>();
defineEmits<{ change: [value: Partial<SaleCommand>] }>();
</script>
<template>
  <div class="sale-fields">
    <p><strong>Сейчас:</strong> {{ currentBuyer(order).name }} · {{ currentBuyer(order).email }} · {{ currentBuyer(order).phone }}</p>
    <template v-if="command.action === 'contacts'"
      ><v-text-field
        :model-value="command.name"
        label="Имя покупателя"
        :error-messages="errors.name"
        :aria-invalid="!!errors.name"
        @update:model-value="$emit('change', { name: $event })" /><v-text-field
        :model-value="command.phone"
        label="Телефон покупателя"
        type="tel"
        :error-messages="errors.phone"
        :aria-invalid="!!errors.phone"
        @update:model-value="$emit('change', { phone: $event })"
    /></template>
    <v-text-field
      :model-value="command.email"
      label="Проверенный email"
      type="email"
      :error-messages="errors.email"
      :aria-invalid="!!errors.email"
      @update:model-value="$emit('change', { email: $event })"
    />
    <v-checkbox
      :model-value="command.verified"
      label="Заказ и контакт проверены по обращению родителя"
      :error-messages="errors.verified"
      :aria-invalid="!!errors.verified"
      @update:model-value="$emit('change', { verified: !!$event })"
    />
    <p>
      <strong>Станет:</strong> {{ command.action === 'contacts' ? command.name : currentBuyer(order).name }} · {{ command.email }} ·
      {{ command.action === 'contacts' ? command.phone : currentBuyer(order).phone }}
    </p>
    <p class="mf-muted">Личная ссылка, история оплаты и исходный месячный срок файлов сохранятся. Настоящие письма не отправляются.</p>
  </div>
</template>
