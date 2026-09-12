<script setup lang="ts">
import type { CartQuote, Catalog } from '../types';
import { money } from '../money';
defineProps<{ quote: CartQuote; catalog: Catalog; staff: boolean }>();
</script>
<template>
  <aside class="mf-panel cart-summary">
    <h2>Ваш выбор</h2>
    <dl>
      <div>
        <dt>Товары</dt>
        <dd>{{ money(quote.subtotal) }}</dd>
      </div>
      <div v-if="quote.discount">
        <dt>Льгота сотрудника</dt>
        <dd>−{{ money(quote.discount) }}</dd>
      </div>
      <div v-if="quote.giftSaving">
        <dt>Файлы в подарок</dt>
        <dd>−{{ money(quote.giftSaving) }}</dd>
      </div>
      <div class="cart-summary__total">
        <dt>Итого</dt>
        <dd data-testid="cart-total">{{ money(quote.total) }}</dd>
      </div>
    </dl>
    <p class="mf-muted">Цены демонстрационные. Настоящие платежи не выполняются.</p>
    <div v-if="catalog.giftThreshold > 0 && (!staff || catalog.giftForStaff)" class="cart-summary__offer">
      <v-icon icon="mdi-gift-outline" size="22" />
      <p>От {{ money(catalog.giftThreshold) }} печатной продукции для одной серии — электронный комплект в подарок.</p>
    </div>
    <p class="cart-summary__note">
      Порог считается после скидки, отдельно для каждого ребёнка в этом заказе. Покупки из других групп и прошлые заказы не складываются.
    </p>
    <slot />
  </aside>
</template>
<style scoped>
.cart-summary {
  align-self: start;
  position: sticky;
  top: 24px;
}
.cart-summary dl {
  margin: 20px 0;
}
.cart-summary dl > div {
  display: flex;
  justify-content: space-between;
  gap: 16px;
  font-size: 14px;
  margin: 14px 0;
}
.cart-summary dd {
  margin: 0;
  white-space: nowrap;
}
.cart-summary__total {
  border-top: 1px solid #dce4ea;
  padding-top: 20px;
  font-size: 22px !important;
  font-weight: 600;
}
.cart-summary .mf-muted,
.cart-summary__note {
  font-size: 12px;
  line-height: 1.6;
}
.cart-summary__offer {
  display: flex;
  gap: 10px;
  margin: 24px 0 12px;
  color: #24658a;
  font-size: 14px;
  line-height: 1.5;
}
.cart-summary__note {
  color: #5e6872;
}
@media (max-width: 767px) {
  .cart-summary {
    position: static;
  }
}
</style>
