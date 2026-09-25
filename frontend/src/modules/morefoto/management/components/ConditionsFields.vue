<script setup lang="ts">
import { computed } from 'vue';
import type { ConditionsCommand, ManagementErrors } from '../types';
import type { Catalog } from '../../commerce/types';
import { money } from '../../commerce/money';
import { moneyInputValue } from '../../ui/field-values';
import { MAX_PRICE } from '../../conditions/conditions-command';
import { rateInputValue, salePrice, type PaymentCostPolicy } from '../../conditions/payment-costs';
const model = defineModel<ConditionsCommand>({ required: true });
const props = defineProps<{ errors: ManagementErrors; catalog: Catalog; paymentCosts?: PaymentCostPolicy | null }>();
/** Общие условия берут политику из формы, условия группы — сохранённую общую политику. */
const policy = computed<PaymentCostPolicy | null>(() => {
  const costs = model.value.paymentCosts;
  if (!model.value.groupId && costs) {
    const rateBps = rateInputValue(costs.rate, costs.maxRateBps);
    return rateBps === null ? null : { enabled: costs.enabled, rateBps };
  }
  return props.paymentCosts ?? null;
});
function buyerPrice(price: string): number | null {
  const base = moneyInputValue(price);
  return policy.value?.enabled && base !== null && base <= MAX_PRICE ? salePrice(base, policy.value) : null;
}
const kinds = { physical: 'Печатный товар', digital: 'Один электронный кадр', bundle: 'Весь набор ребёнка в одной съёмке' };
</script>
<template>
  <fieldset v-if="model.groupId" class="conditions-mode mb-5">
    <legend>Условия группы</legend>
    <div class="conditions-mode__segments">
      <label class="conditions-mode__segment" :class="{ 'conditions-mode__segment--on': model.inherit }">
        <input v-model="model.inherit" type="radio" name="conditions-mode" :value="true" />
        Общие условия каталога
      </label>
      <label class="conditions-mode__segment" :class="{ 'conditions-mode__segment--on': !model.inherit }">
        <input v-model="model.inherit" type="radio" name="conditions-mode" :value="false" />
        Собственные условия группы
      </label>
    </div>
    <p class="mf-muted mt-2">
      {{
        model.inherit
          ? 'Обновления общего каталога будут действовать для этой группы.'
          : 'Цены и предложения ниже действуют только для этой группы.'
      }}
    </p>
  </fieldset>
  <p class="mf-muted mb-5">
    Скидка сотрудникам — 50% только на отмеченные позиции. Печатные товары учитываются в пороге подарка после скидки, отдельно для каждого
    ребёнка в одном заказе.
  </p>
  <section v-if="!model.groupId && model.paymentCosts" class="payment-costs mb-5" data-testid="payment-costs">
    <h3>Расходы на оплату</h3>
    <p class="mf-muted mt-2 mb-3">
      Цена для покупателя = цена каталога ÷ (1 − ставка), с округлением вверх до 50 ₽. Скидка сотрудникам и порог подарка считаются от неё.
      Выключение возвращает цены каталога.
    </p>
    <v-checkbox
      v-model="model.paymentCosts.enabled"
      label="Учитывать расходы на оплату в цене"
      aria-label="Учитывать расходы на оплату в цене"
      hide-details
    />
    <v-text-field
      v-model="model.paymentCosts.rate"
      label="Ставка расходов"
      aria-label="Ставка расходов на оплату, %"
      suffix="%"
      inputmode="decimal"
      class="payment-costs__rate"
      :disabled="!model.paymentCosts.enabled"
      :error-messages="errors.paymentCostRate"
      :aria-invalid="!!errors.paymentCostRate"
    />
  </section>
  <v-alert v-if="errors.products" type="error" variant="tonal" class="mb-4">{{ errors.products }}</v-alert>
  <div v-for="product in model.products" :key="product.id" class="condition-product" :data-testid="'condition-' + product.id">
    <div class="condition-name">
      <strong>{{ product.name }}</strong
      ><span class="mf-muted">{{ kinds[product.kind] }}</span
      ><span v-if="model.groupId && !catalog.products.find((p) => p.id === product.id)?.active" class="mf-muted"
        >Отключено в общем каталоге</span
      >
    </div>
    <div class="condition-price">
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
      <p v-if="buyerPrice(product.price) !== null" class="condition-sale mf-muted" :data-testid="'sale-price-' + product.id">
        Для покупателя: {{ money(buyerPrice(product.price) ?? 0) }}
      </p>
    </div>
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
.conditions-mode {
  margin: 0;
  padding: 0;
  border: 0;
}
.conditions-mode legend {
  margin-bottom: var(--mf-space-2);
  font-weight: var(--mf-weight-semibold);
}
.conditions-mode__segments {
  display: inline-grid;
  grid-auto-flow: column;
  grid-auto-columns: minmax(0, 1fr);
  max-width: 100%;
  padding: 2px;
  border: 1px solid var(--mf-color-border-strong);
  border-radius: var(--mf-radius-sm);
  background: var(--mf-color-surface-2);
}
.conditions-mode__segment {
  position: relative;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 40px;
  padding: 0 var(--mf-space-4);
  border-radius: var(--mf-radius-xs);
  color: var(--mf-color-text-secondary);
  font-weight: var(--mf-weight-medium);
  text-align: center;
  cursor: pointer;
}
.conditions-mode__segment input {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  margin: 0;
  opacity: 0;
  cursor: pointer;
}
.conditions-mode__segment--on {
  background: var(--mf-color-surface);
  box-shadow: var(--mf-shadow-sm);
  color: var(--mf-color-text);
}
.conditions-mode__segment:has(input:focus-visible) {
  outline: var(--mf-focus-width) solid var(--mf-color-focus);
  outline-offset: 2px;
}
@media (max-width: 600px) {
  .conditions-mode__segments {
    display: grid;
    grid-auto-flow: row;
    width: 100%;
  }
}
.condition-product {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(160px, 0.6fr);
  gap: 16px;
  padding: 20px 0;
  border-top: 1px solid var(--mf-color-border);
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
.condition-price {
  min-width: 0;
}
.condition-sale {
  margin-top: 4px;
  font-size: 14px;
}
.payment-costs {
  padding-bottom: 20px;
  border-bottom: 1px solid var(--mf-color-border);
}
.payment-costs__rate {
  max-width: 220px;
}
.gift-fields {
  margin-top: 24px;
  padding-top: 24px;
  border-top: 1px solid var(--mf-color-border);
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
