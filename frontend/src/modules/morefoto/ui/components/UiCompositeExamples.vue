<script setup lang="ts">
import { computed, ref, shallowRef, useTemplateRef, watch } from 'vue';
import type { UiDensity, UiFieldState } from '../types';
import { dateInputValid, moneyInputValue, quantityValue } from '../field-values';
import UiPhoneField from './UiPhoneField.vue';
import UiQuantityField from './UiQuantityField.vue';
import UiClearButton from './UiClearButton.vue';
const props = defineProps<{ density: UiDensity; state: UiFieldState }>();
const phone = ref('');
const quantity = ref<string | number>(1);
const date = ref('');
const amount = ref('');
const file = shallowRef<File | null>(null);
const checked = ref(false);
const result = ref('');
const quantityField = useTemplateRef<{ validate: () => boolean }>('quantityField');
const disabled = computed(() => props.state === 'disabled' || props.state === 'loading');
const readonly = computed(() => props.state === 'readonly');
const errors = computed(() => ({
  phone: /^[+\d\s().-]+$/.test(phone.value) && phone.value.replace(/\D/g, '').length >= 10 ? '' : 'Введите номер телефона.',
  date: dateInputValid(date.value) ? '' : 'Выберите существующую дату.',
  amount: moneyInputValue(amount.value) !== null ? '' : 'Укажите сумму: рубли и не более двух знаков копеек.',
  file: file.value && file.value.size > 5 * 1024 * 1024 ? 'В этом примере файл должен быть не больше 5 МБ.' : ''
}));
watch(
  () => props.state,
  (state) => {
    checked.value = state === 'error';
    result.value = '';
    if (state === 'empty' || state === 'error') {
      phone.value = state === 'error' ? '12' : '';
      quantity.value = state === 'error' ? 0 : 1;
      date.value = '';
      amount.value = state === 'error' ? '1,234' : '';
    } else {
      phone.value = '+7 (900) 123-45-67';
      quantity.value = 3;
      date.value = '2026-09-07';
      amount.value = '180,00';
    }
  },
  { immediate: true }
);
function selectFile(value: File | File[] | null) {
  file.value = Array.isArray(value) ? (value[0] ?? null) : value;
  result.value = '';
}
function submit() {
  if (disabled.value || readonly.value) return;
  checked.value = true;
  result.value = '';
  const quantityValid = quantityField.value?.validate();
  if (!quantityValid || Object.values(errors.value).some(Boolean)) return;
  result.value =
    'Поля проверены. Количество: ' +
    quantityValue(quantity.value) +
    '; сумма: ' +
    moneyInputValue(amount.value) +
    ' коп.; дата: ' +
    date.value +
    '. Файл никуда не отправлен.';
}
</script>
<template>
  <section class="mf-panel ui-example-section" aria-labelledby="ui-composite-title">
    <div>
      <h2 id="ui-composite-title">Составные поля</h2>
      <p class="mf-muted">Телефон форматируется после ввода. Дата, сумма и файл проверяются в этом примере.</p>
    </div>
    <form novalidate class="composite-form" @submit.prevent="submit">
      <div class="ui-fields">
        <UiPhoneField
          v-model="phone"
          label="Телефон для связи"
          :density="density"
          :disabled="disabled"
          :readonly="readonly"
          :loading="state === 'loading'"
          :error-messages="checked ? errors.phone : ''"
          data-testid="ui-phone"
        />
        <UiQuantityField
          ref="quantityField"
          v-model="quantity"
          label="Количество в образце"
          :density="density"
          :disabled="disabled"
          :readonly="readonly"
          data-testid="ui-quantity"
        />
        <v-text-field
          :model-value="date"
          type="date"
          label="Дата съёмки"
          aria-label="Дата съёмки"
          :density="density"
          :disabled="disabled"
          :readonly="readonly"
          :error-messages="checked ? errors.date : ''"
          :aria-invalid="(checked && !!errors.date) || undefined"
          clearable
          @update:model-value="date = $event ?? ''"
          data-testid="ui-date"
        >
          <template #clear="{ props: clearProps }"
            ><UiClearButton v-bind="clearProps" label="Очистить дату" :disabled="disabled || readonly"
          /></template>
        </v-text-field>
        <v-text-field
          :model-value="amount"
          label="Сумма"
          aria-label="Сумма"
          inputmode="decimal"
          suffix="₽"
          :density="density"
          :disabled="disabled"
          :readonly="readonly"
          :error-messages="checked ? errors.amount : ''"
          :aria-invalid="(checked && !!errors.amount) || undefined"
          clearable
          @update:model-value="amount = $event ?? ''"
          data-testid="ui-amount"
        >
          <template #clear="{ props: clearProps }"
            ><UiClearButton v-bind="clearProps" label="Очистить сумму" :disabled="disabled || readonly"
          /></template>
        </v-text-field>
      </div>
      <v-file-input
        :model-value="file"
        label="Файл для примера — необязательно"
        aria-label="Файл для примера"
        :density="density"
        :disabled="disabled"
        :readonly="readonly"
        show-size
        :error-messages="errors.file"
        hint="До 5 МБ для этого примера. Файл остаётся в браузере."
        persistent-hint
        @update:model-value="selectFile"
        data-testid="ui-file"
      >
        <template #clear="{ props: clearProps }"
          ><UiClearButton v-bind="clearProps" label="Убрать выбранный файл" :disabled="disabled || readonly"
        /></template>
      </v-file-input>
      <div class="ui-example-actions">
        <v-btn type="submit" :disabled="disabled || readonly">Проверить поля</v-btn>
      </div>
      <p v-if="result" role="status" data-testid="ui-composite-result">{{ result }}</p>
    </form>
  </section>
</template>
<style scoped>
.composite-form {
  display: grid;
  gap: var(--mf-space-4);
  min-width: 0;
}
.composite-form > p {
  line-height: 1.6;
  overflow-wrap: anywhere;
}
.composite-form :deep(.v-file-input .v-field__input) {
  min-width: 0;
  overflow-wrap: anywhere;
}
</style>
