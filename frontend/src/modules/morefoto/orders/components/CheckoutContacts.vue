<script setup lang="ts">
import type { BuyerFields, BuyerErrors } from '../types';
import UiPhoneField from '../../ui/components/UiPhoneField.vue';
import UiClearButton from '../../ui/components/UiClearButton.vue';
defineProps<{ draft: BuyerFields; errors: BuyerErrors; busy: boolean; maxAvailable: boolean }>();
const emit = defineEmits<{ change: [patch: Partial<BuyerFields>] }>();
function text(field: 'name' | 'email' | 'comment' | 'phone', value: string | null) {
  emit('change', { [field]: value ?? '' });
}
function channel(value: unknown) {
  if (value === 'email' || value === 'max') emit('change', { receiptChannel: value });
}
</script>
<template>
  <section class="mf-panel checkout-contacts">
    <h2>Контакты покупателя</h2>
    <p class="mf-muted checkout-contacts__intro">Укажите тестовые данные. Они сохранятся при обновлении страницы и ошибке отправки.</p>
    <fieldset :disabled="busy">
      <legend class="visually-hidden">Контакты и получение чека</legend>
      <v-text-field
        :model-value="draft.name"
        name="buyer-name"
        label="Имя покупателя"
        aria-label="Имя покупателя"
        required
        autocomplete="name"
        maxlength="100"
        :disabled="busy"
        :error-messages="errors.name"
        :aria-invalid="!!errors.name || undefined"
        clearable
        class="contact-name"
        @update:model-value="text('name', $event)"
      >
        <template #clear="{ props: clearProps }"
          ><UiClearButton v-bind="clearProps" label="Очистить имя покупателя" :disabled="busy"
        /></template>
      </v-text-field>
      <div class="contact-channels">
        <UiPhoneField
          :model-value="draft.phone"
          name="buyer-phone"
          required
          maxlength="40"
          :disabled="busy"
          :error-messages="errors.phone"
          hint="Например, +7 (900) 123-45-67"
          persistent-hint
          @update:model-value="text('phone', $event)"
        />
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
          @update:model-value="text('email', $event)"
        >
          <template #clear="{ props: clearProps }"><UiClearButton v-bind="clearProps" label="Очистить email" :disabled="busy" /></template>
        </v-text-field>
      </div>
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
      <div class="contact-receipt">
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
.contact-name {
  max-width: 560px;
}
.contact-channels {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  gap: var(--mf-space-4);
  align-items: start;
}
.contact-channels > :first-child {
  width: 100%;
  max-width: 280px;
}
.contact-channel-note {
  font-size: var(--mf-text-small);
  margin-top: var(--mf-space-2);
}
@container (min-width: 560px) {
  .contact-channels {
    grid-template-columns: minmax(240px, 280px) minmax(0, 1fr);
  }
}
@media (max-width: 767px) {
  .checkout-contacts {
    padding: var(--mf-space-4);
  }
  .contact-channels > :first-child {
    max-width: none;
  }
}
</style>
