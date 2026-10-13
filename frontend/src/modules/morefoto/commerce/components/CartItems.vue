<script setup lang="ts">
import { computed } from 'vue';
import type { CartQuote } from '../types';
import CartLineItem from './CartLineItem.vue';
defineEmits<{ change: [id: string, quantity: number] }>();
const props = defineProps<{ quote: CartQuote; busy: boolean; closed: boolean }>();
const groups = computed(() =>
  [...new Set(props.quote.lines.map((line) => line.childCode))].map((code) => ({
    code,
    lines: props.quote.lines.filter((line) => line.childCode === code),
    gift: props.quote.gifts.includes(code)
  }))
);
</script>
<template>
  <!-- One panel for the whole choice; a series is a small heading inside it, not a panel of its own (UI-DEC-04). -->
  <section class="mf-panel cart-children" aria-label="Выбранные фотографии">
    <div v-for="child in groups" :key="child.code" class="cart-child">
      <h2 class="cart-child__code">Серия {{ child.code }}</h2>
      <CartLineItem
        v-for="line in child.lines"
        :key="line.id"
        :line="line"
        :busy="busy"
        :closed="closed"
        @change="(id, quantity) => $emit('change', id, quantity)"
      />
      <div v-if="child.gift" class="cart-gift" data-testid="cart-gift">
        <v-icon icon="mdi-gift-outline" size="20" />
        <span><strong>Электронный комплект в подарок</strong> · все кадры серии {{ child.code }} · 0 ₽</span>
      </div>
    </div>
  </section>
</template>
<style scoped>
.cart-children {
  display: grid;
  gap: var(--mf-space-4);
  min-width: 0;
  padding: var(--mf-space-4) var(--mf-space-5);
}
.cart-child + .cart-child {
  padding-top: var(--mf-space-4);
  border-top: 1px solid var(--mf-color-border);
}
.cart-children .cart-child__code {
  color: var(--mf-color-text-tertiary);
  font-family: var(--mf-font-sans);
  font-size: var(--mf-text-xs);
  font-weight: var(--mf-weight-semibold);
  letter-spacing: var(--mf-tracking-caps);
  text-transform: uppercase;
}
.cart-gift {
  display: flex;
  align-items: center;
  gap: var(--mf-space-2);
  margin-top: var(--mf-space-2);
  padding: var(--mf-space-2) var(--mf-space-3);
  border-radius: var(--mf-radius-sm);
  background: var(--mf-color-selected);
  color: var(--mf-color-link);
  font-size: var(--mf-text-sm);
}
@media (max-width: 767px) {
  .cart-children {
    padding: var(--mf-space-3) var(--mf-space-4);
  }
}
</style>
