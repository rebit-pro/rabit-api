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
  <div class="cart-children">
    <section v-for="child in groups" :key="child.code" class="mf-panel">
      <h2>Серия {{ child.code }}</h2>
      <CartLineItem
        v-for="line in child.lines"
        :key="line.id"
        :line="line"
        :busy="busy"
        :closed="closed"
        @change="(id, quantity) => $emit('change', id, quantity)"
      />
      <div v-if="child.gift" class="cart-gift" data-testid="cart-gift">
        <v-icon icon="mdi-gift-outline" />
        <div>
          <strong>Электронный комплект в подарок</strong>
          <p>Все кадры серии {{ child.code }} · 0 ₽</p>
        </div>
      </div>
    </section>
  </div>
</template>
<style scoped>
.cart-children {
  display: grid;
  gap: var(--mf-space-4);
  min-width: 0;
}
.cart-children .mf-panel {
  padding: var(--mf-space-6);
}
.cart-gift {
  display: flex;
  gap: var(--mf-space-3);
  padding: var(--mf-space-4);
  margin-top: var(--mf-space-6);
  background: #eaf3f9;
  color: #24658a;
  border-radius: var(--mf-radius-field);
  font-size: var(--mf-text-small);
}
@media (max-width: 767px) {
  .cart-children .mf-panel {
    padding: var(--mf-space-4);
  }
}
</style>
