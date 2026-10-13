<script setup lang="ts">
import { isMockApiEnabled } from '@/mocks/config';
import type { CartQuote, Catalog } from '../types';
import { money } from '../money';
defineProps<{ quote: CartQuote; catalog: Catalog; staff: boolean }>();
</script>
<template>
  <!-- UI-DEC-04: the total and the action lead; the gift rule follows them in small print. -->
  <aside class="mf-panel cart-summary">
    <h2>Ваш заказ</h2>
    <dl>
      <div>
        <dt>Товары · {{ quote.count }}</dt>
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
    <p v-if="isMockApiEnabled" class="cart-summary__note">Цены демонстрационные. Настоящие платежи не выполняются.</p>
    <slot />
    <div v-if="catalog.giftThreshold > 0 && (!staff || catalog.giftForStaff)" class="cart-summary__offer">
      <v-icon icon="mdi-gift-outline" size="20" />
      <p>
        От {{ money(catalog.giftThreshold) }} печатной продукции для одной серии — электронный комплект в подарок.
        <span>Порог считается после скидки, отдельно для каждого ребёнка в этом заказе.</span>
      </p>
    </div>
  </aside>
</template>
<style scoped>
.cart-summary {
  display: grid;
  gap: var(--mf-space-4);
  align-self: start;
  position: sticky;
  top: 24px;
}
.cart-summary dl {
  display: grid;
  gap: var(--mf-space-2);
}
.cart-summary dl > div {
  display: flex;
  justify-content: space-between;
  gap: var(--mf-space-4);
  color: var(--mf-color-text-secondary);
  font-size: var(--mf-text-sm);
}
.cart-summary dd {
  margin: 0;
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}
.cart-summary dl > .cart-summary__total {
  margin-top: var(--mf-space-1);
  padding-top: var(--mf-space-3);
  border-top: 1px solid var(--mf-color-border);
  color: var(--mf-color-text);
  font-size: 1.375rem;
  font-weight: 600;
}
.cart-summary__note {
  color: var(--mf-color-text-secondary);
  font-size: var(--mf-text-xs);
  line-height: 1.5;
}
.cart-summary__offer {
  display: flex;
  gap: var(--mf-space-2);
  padding: var(--mf-space-3);
  border-radius: var(--mf-radius-sm);
  background: var(--mf-color-selected);
  color: var(--mf-color-link);
  font-size: var(--mf-text-sm);
  line-height: 1.5;
}
.cart-summary__offer span {
  display: block;
  margin-top: 2px;
  color: var(--mf-color-text-secondary);
  font-size: var(--mf-text-xs);
}
@media (max-width: 1000px) {
  .cart-summary {
    position: static;
  }
}
</style>
