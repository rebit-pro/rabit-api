<script setup lang="ts">
import type { ConditionsCommand, ManagementErrors } from '../types';
import type { Catalog } from '../../commerce/types';
const model = defineModel<ConditionsCommand>({ required: true });
defineProps<{ errors: ManagementErrors; catalog: Catalog }>();
const kinds = { physical: 'Печатный товар', digital: 'Один электронный кадр', bundle: 'Весь набор ребёнка в одной съёмке' };
</script>
<template>
  <v-checkbox
    v-if="model.groupId"
    v-model="model.inherit"
    label="Наследовать общий прайс и предложения"
    aria-label="Наследовать общий прайс и предложения"
    hint="Обновления общего каталога будут действовать для этой группы."
    persistent-hint
    class="mb-4"
  />
  <p class="mf-muted mb-5">
    Скидка сотрудникам — 50% только на отмеченные позиции. Печатные товары учитываются в пороге подарка после скидки, отдельно для каждого
    ребёнка в одном заказе.
  </p>
  <v-alert v-if="errors.products" type="error" variant="tonal" class="mb-4">{{ errors.products }}</v-alert>
  <div v-for="product in model.products" :key="product.id" class="condition-product" :data-testid="'condition-' + product.id">
    <div class="condition-name">
      <strong>{{ product.name }}</strong
      ><span class="mf-muted">{{ kinds[product.kind] }}</span
      ><span v-if="model.groupId && !catalog.products.find((p) => p.id === product.id)?.active" class="mf-muted"
        >Отключено в общем каталоге</span
      >
    </div>
    <v-text-field
      v-model="product.price"
      label="Цена"
      :aria-label="'Цена: ' + product.name"
      suffix="₽"
      inputmode="decimal"
      :disabled="!!model.groupId && model.inherit"
      :error-messages="errors['price:' + product.id]"
      :aria-invalid="!!errors['price:' + product.id]"
    />
    <div class="condition-switches">
      <v-checkbox
        v-model="product.active"
        label="В продаже"
        :aria-label="'В продаже: ' + product.name"
        :disabled="!!model.groupId && (model.inherit || !catalog.products.find((p) => p.id === product.id)?.active)"
        hide-details
      />
      <v-checkbox
        v-model="product.staffDiscount"
        label="Сотрудникам 50%"
        :aria-label="'50% сотрудникам: ' + product.name"
        :disabled="!!model.groupId && model.inherit"
        hide-details
      />
    </div>
  </div>
  <section class="gift-fields">
    <h3>Электронный комплект в подарок</h3>
    <p class="mf-muted mt-2 mb-3">
      Все кадры одного ребёнка из этой съёмки. Суммы разных детей и заказов не объединяются. Комплект заменяет отдельные электронные кадры
      без двойной оплаты.
    </p>
    <v-checkbox
      v-model="model.giftEnabled"
      label="Предлагать подарок от суммы печатных товаров"
      aria-label="Предлагать подарок от суммы печатных товаров"
      :disabled="!!model.groupId && model.inherit"
    />
    <v-text-field
      v-if="model.giftEnabled"
      v-model="model.giftThreshold"
      label="Порог подарка, ₽"
      aria-label="Порог подарка, ₽"
      inputmode="decimal"
      :disabled="!!model.groupId && model.inherit"
      :error-messages="errors.giftThreshold"
      :aria-invalid="!!errors.giftThreshold"
    />
    <v-checkbox
      v-model="model.giftForStaff"
      label="Подарок также действует для сотрудников"
      aria-label="Подарок также действует для сотрудников"
      :disabled="!model.giftEnabled || (!!model.groupId && model.inherit)"
      hint="Порог считается после скидки сотрудника и отдельно для каждого ребёнка."
      persistent-hint
    />
  </section>
</template>
<style scoped>
.condition-product {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(160px, 0.6fr);
  gap: 16px;
  padding: 20px 0;
  border-top: 1px solid #dce3e8;
  min-width: 0;
}
.condition-name {
  display: flex;
  flex-direction: column;
  gap: 5px;
  overflow-wrap: anywhere;
}
.condition-switches {
  grid-column: 1/-1;
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px;
}
.gift-fields {
  margin-top: 24px;
  padding-top: 24px;
  border-top: 1px solid #dce3e8;
}
@media (max-width: 600px) {
  .condition-product,
  .condition-switches {
    grid-template-columns: 1fr;
  }
  .condition-product {
    gap: 8px;
  }
}
</style>
