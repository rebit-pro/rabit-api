<script setup lang="ts">
import { shallowRef } from 'vue';
import type { BuyerFields, BuyerErrors } from '../types';
import UiClearButton from '../../ui/components/UiClearButton.vue';
const props = defineProps<{
  draft: BuyerFields;
  errors: BuyerErrors;
  busy: boolean;
  maxAvailable: boolean;
  receiptAvailable: boolean;
  orderEmail?: boolean;
  /** G2: «Чеки от ЮKassa» send the receipt to the order email. */
  receiptEmail?: boolean;
}>();
const emit = defineEmits<{ change: [patch: Partial<BuyerFields>] }>();
// UI-DEC-04: the optional comment stays folded until the buyer asks for it or already wrote one.
const commenting = shallowRef(props.draft.comment !== '' || !!props.errors.comment);
function text(field: 'email' | 'comment', value: string | null) {
  emit('change', { [field]: value ?? '' });
}
function channel(value: unknown) {
  if (value === 'email' || value === 'max') emit('change', { receiptChannel: value });
}
</script>
<template>
  <fieldset class="checkout-contacts" :disabled="busy">
    <legend class="mf-sr-only">Контакт покупателя</legend>
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
      :hint="
        orderEmail
          ? 'Ссылку на заказ покажем после оформления. На этот адрес отправим фото и ссылку на заказ после оплаты'
          : 'Ссылку на заказ покажем после оформления — по ней оплата и файлы'
      "
      persistent-hint
      clearable
      @update:model-value="text('email', $event)"
    >
      <template #clear="{ props: clearProps }"><UiClearButton v-bind="clearProps" label="Очистить email" :disabled="busy" /></template>
    </v-text-field>
    <p v-if="receiptEmail" class="checkout-contacts__receipt" data-testid="checkout-receipt-email">
      <v-icon icon="mdi-receipt-text-outline" size="16" aria-hidden="true" />Чек об оплате придёт на этот e-mail от ЮKassa.
    </p>
    <v-textarea
      v-if="commenting"
      :model-value="draft.comment"
      name="buyer-comment"
      label="Комментарий — необязательно"
      aria-label="Комментарий — необязательно"
      :rows="2"
      auto-grow
      maxlength="1000"
      :counter="1000"
      :disabled="busy"
      :error-messages="errors.comment"
      :aria-invalid="!!errors.comment || undefined"
      @update:model-value="text('comment', $event)"
    />
    <v-btn v-else variant="text" density="compact" prepend-icon="mdi-plus" class="checkout-contacts__more" @click="commenting = true"
      >Добавить комментарий</v-btn
    >
    <v-radio-group
      v-if="receiptAvailable"
      :model-value="draft.receiptChannel"
      name="buyer-receiptChannel"
      label="Куда направить чек"
      density="compact"
      :disabled="busy"
      :error-messages="errors.receiptChannel"
      hide-details="auto"
      @update:model-value="channel"
    >
      <v-radio label="На email" value="email" />
      <v-radio :label="maxAvailable ? 'В MAX' : 'В MAX — пока недоступно'" value="max" :disabled="!maxAvailable" />
    </v-radio-group>
  </fieldset>
</template>
<style scoped>
.checkout-contacts {
  display: grid;
  gap: var(--mf-space-3);
  min-width: 0;
  border: 0;
}
.checkout-contacts__more {
  justify-self: start;
}
.checkout-contacts__receipt {
  display: flex;
  gap: 6px;
  align-items: center;
  font-size: var(--mf-text-sm);
  color: var(--mf-color-text-secondary);
}
</style>
