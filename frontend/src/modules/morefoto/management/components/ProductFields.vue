<script setup lang="ts">
import type { ProductCommand, ManagementErrors } from '../types';
const model = defineModel<ProductCommand>({ required: true });
defineProps<{ errors: ManagementErrors; existing: boolean; live?: boolean }>();
const kinds = [
  { title: 'Печатный товар', value: 'physical' },
  { title: 'Электронный кадр', value: 'digital' },
  { title: 'Полный электронный комплект', value: 'bundle' }
];
</script>
<template>
  <div class="product-fields">
    <v-text-field
      v-model="model.product.name"
      label="Название продукции"
      aria-label="Название продукции"
      :error-messages="errors.name"
      :aria-invalid="!!errors.name"
      :maxlength="live ? 255 : 100"
    />
    <v-select
      v-model="model.product.kind"
      :items="kinds"
      label="Тип продукции"
      aria-label="Тип продукции"
      :disabled="existing"
      :error-messages="errors.kind"
      :aria-invalid="!!errors.kind"
      hint="Тип существующей позиции сохраняется для истории заказов."
      persistent-hint
    />
    <div class="fields-pair mt-4">
      <v-text-field
        v-model="model.product.format"
        label="Формат"
        aria-label="Формат"
        :error-messages="errors.format"
        :aria-invalid="!!errors.format"
        :maxlength="live ? 100 : 80"
      />
      <v-text-field
        v-model="model.product.unit"
        label="Единица продажи"
        aria-label="Единица продажи"
        :error-messages="errors.unit"
        :aria-invalid="!!errors.unit"
        :maxlength="live ? 100 : 40"
      />
      <v-text-field
        v-model="model.product.price"
        label="Цена, ₽"
        aria-label="Цена, ₽"
        inputmode="decimal"
        :error-messages="errors.price"
        :aria-invalid="!!errors.price"
      />
      <v-text-field
        v-if="model.product.kind === 'physical'"
        v-model="model.product.printCount"
        label="Отпечатков в единице"
        aria-label="Отпечатков в единице"
        inputmode="numeric"
        :error-messages="errors.printCount"
        :aria-invalid="!!errors.printCount"
      />
    </div>
    <v-textarea
      v-model="model.product.description"
      label="Описание"
      aria-label="Описание"
      rows="3"
      auto-grow
      :error-messages="errors.description"
      :aria-invalid="!!errors.description"
      :maxlength="live ? 4000 : 600"
    />
    <v-checkbox
      v-model="model.product.active"
      label="Доступно для покупки"
      aria-label="Доступно для покупки"
      hint="Отключение сохраняет историю. В группах со своими условиями новый товар нужно включить отдельно."
      persistent-hint
    />
    <v-checkbox
      v-model="model.product.staffDiscount"
      label="Скидка сотрудникам 50%"
      aria-label="Скидка сотрудникам 50%"
      hint="Применяется только в группе сотрудников."
      persistent-hint
    />
  </div>
</template>
<style scoped>
.fields-pair {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0 20px;
}
@media (max-width: 600px) {
  .fields-pair {
    grid-template-columns: 1fr;
  }
}
</style>
