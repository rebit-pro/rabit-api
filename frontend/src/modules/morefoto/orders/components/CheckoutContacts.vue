<script setup lang="ts">
import type { BuyerFields, BuyerErrors } from '../types';
import UiClearButton from '../../ui/components/UiClearButton.vue';
import { isMockApiEnabled } from '@/mocks/config';
defineProps<{ draft: BuyerFields; errors: BuyerErrors; busy: boolean; maxAvailable: boolean; receiptAvailable: boolean }>();
const emit = defineEmits<{ change: [patch: Partial<BuyerFields>] }>();
function text(field: 'email' | 'comment', value: string | null) {
  emit('change', { [field]: value ?? '' });
}
function channel(value: unknown) {
  if (value === 'email' || value === 'max') emit('change', { receiptChannel: value });
}
</script>
<template>
  <section class="mf-panel checkout-contacts">
    <h2>Контакт покупателя</h2>
    <p class="mf-muted checkout-contacts__intro">
      {{ isMockApiEnabled ? 'Укажите тестовые данные. ' : '' }}Заполненные поля сохранятся при обновлении страницы и ошибке отправки.
    </p>
    <fieldset :disabled="busy">
      <legend class="visually-hidden">Контакты и получение чека</legend>
      <v-text-field
        :model-value="draft.email"
        name="buyer-email"
        label="Email"
        aria-label="Email"
        type="email"
        required
        autocomplete="email"
        maxlength="254"
        :disabled="busy"
        :error-messages="errors.email"
        :aria-invalid="!!errors.email || undefined"
        clearable
        class="contact-email"
        @update:model-value="text('email', $event)"
      >
        <template #clear="{ props: clearProps }"><UiClearButton v-bind="clearProps" label="Очистить email" :disabled="busy" /></template>
      </v-text-field>
      <v-textarea
        :model-value="draft.comment"
        name="buyer-comment"
        label="Комментарий — необязательно"
        aria-label="Комментарий — необязательно"
        :rows="3"
        maxlength="1000"
        :counter="1000"
        :disabled="busy"
        :error-messages="errors.comment"
        :aria-invalid="!!errors.comment || undefined"
        @update:model-value="text('comment', $event)"
      />
      <p v-if="!receiptAvailable" class="mf-muted contact-channel-note" data-testid="receipt-unavailable">
        Выбор канала для чека появится позже. Оплата — на странице заказа после оформления.
      </p>
      <div v-else class="contact-receipt">
        <v-radio-group
          :model-value="draft.receiptChannel"
          name="buyer-receiptChannel"
          label="Куда направить чек"
          :disabled="busy"
          :error-messages="errors.receiptChannel"
          @update:model-value="channel"
        >
          <v-radio label="На email" value="email" />
          <v-radio :label="maxAvailable ? 'В MAX' : 'В MAX — пока недоступно'" value="max" :disabled="!maxAvailable" />
        </v-radio-group>
        <p class="mf-muted contact-channel-note">
          {{
            maxAvailable
              ? 'Демонстрационный канал MAX доступен. Сообщение будет только имитировано.'
              : 'Когда MAX недоступен, чек можно получить по email.'
          }}
        </p>
      </div>
    </fieldset>
  </section>
</template>
<style scoped>
.checkout-contacts {
  container-type: inline-size;
  padding: var(--mf-space-6);
}
.checkout-contacts__intro {
  margin: var(--mf-space-2) 0 var(--mf-space-6);
  font-size: var(--mf-text-small);
}
fieldset {
  display: grid;
  gap: var(--mf-space-4);
  border: 0;
  min-width: 0;
  max-width: 640px;
}
.visually-hidden {
  display: block;
  position: absolute;
  width: 1px;
  max-width: 1px;
  padding: 0;
  height: 1px;
  overflow: hidden;
  clip-path: inset(50%);
  white-space: nowrap;
}
.contact-email {
  max-width: 560px;
}
.contact-channel-note {
  font-size: var(--mf-text-small);
  margin-top: var(--mf-space-2);
}
@media (max-width: 767px) {
  .checkout-contacts {
    padding: var(--mf-space-4);
  }
}
</style>
