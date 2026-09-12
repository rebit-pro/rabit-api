<script setup lang="ts">
import type { UiDensity, UiFieldState } from '../types';
import UiClearButton from './UiClearButton.vue';
import { useExampleFields } from '../composables/useExampleFields';
const props = defineProps<{
  density: UiDensity;
  state: UiFieldState;
  longText: boolean;
}>();
const { name, product, institution, comment, products, institutions, listState, disabled, readonly, error, choiceError, changeList } =
  useExampleFields(
    () => props.state,
    () => props.longText
  );
</script>
<template>
  <section class="mf-panel ui-example-section" aria-labelledby="ui-fields-title">
    <div>
      <h2 id="ui-fields-title">Поля</h2>
      <p class="mf-muted">Введите значение, откройте список или попробуйте очистку.</p>
    </div>
    <div class="ui-fields">
      <v-text-field
        v-model="name"
        label="Имя покупателя"
        aria-label="Имя покупателя"
        required
        clearable
        :density="density"
        :disabled="disabled"
        :readonly="readonly"
        :loading="state === 'loading'"
        :error-messages="error"
        :aria-invalid="error ? true : undefined"
        hint="Обязательное поле"
        persistent-hint
        data-testid="ui-name"
      >
        <template #clear="{ props: clearProps }"
          ><UiClearButton v-bind="clearProps" label="Очистить имя покупателя" :disabled="readonly || disabled"
        /></template>
      </v-text-field>
      <div>
        <v-select
          v-model="product"
          label="Продукция"
          aria-label="Продукция"
          :items="products"
          item-title="name"
          item-value="id"
          clearable
          :density="density"
          :disabled="disabled"
          :readonly="readonly"
          :loading="state === 'loading'"
          :error-messages="choiceError"
          :aria-invalid="choiceError ? true : undefined"
          data-testid="ui-product"
        >
          <template #clear="{ props: clearProps }"
            ><UiClearButton v-bind="clearProps" label="Очистить продукцию" :disabled="readonly || disabled"
          /></template>
        </v-select>
        <v-btn v-if="listState === 'error'" class="mt-2" variant="text" density="compact" @click="changeList('ready')"
          >Повторить загрузку</v-btn
        >
      </div>
      <v-autocomplete
        v-model="institution"
        label="Учреждение"
        aria-label="Учреждение"
        :items="institutions"
        clearable
        :density="density"
        :disabled="disabled"
        :readonly="readonly"
        :loading="state === 'loading'"
        :error-messages="error"
        :aria-invalid="error ? true : undefined"
        hint="Начните вводить название"
        persistent-hint
        data-testid="ui-autocomplete"
      >
        <template #clear="{ props: clearProps }"
          ><UiClearButton v-bind="clearProps" label="Очистить учреждение" :disabled="readonly || disabled"
        /></template>
      </v-autocomplete>
      <v-textarea
        v-model="comment"
        label="Комментарий — необязательно"
        aria-label="Комментарий"
        :density="density"
        :disabled="disabled"
        :readonly="readonly"
        :loading="state === 'loading'"
        :error-messages="error"
        :aria-invalid="error ? true : undefined"
        data-testid="ui-comment"
      />
    </div>
    <div class="ui-example-actions" aria-label="Состояние списка продукции">
      <v-btn variant="outlined" density="compact" :aria-pressed="listState === 'empty'" @click="changeList('empty')">Пустой список</v-btn>
      <v-btn variant="outlined" density="compact" :aria-pressed="listState === 'error'" @click="changeList('error')">Ошибка списка</v-btn>
      <v-btn variant="text" density="compact" @click="changeList('ready')">Восстановить список</v-btn>
    </div>
  </section>
</template>
