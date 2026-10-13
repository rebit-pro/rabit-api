<script setup lang="ts">
import type { GallerySnapshot } from '../../gallery/types';
import { useCart } from '../composables/useCart';
import { money } from '../money';
import { plural } from '@/components/viz/measures';
/** UI-DEC-06: on a phone the cart total and the way to the order stay at the bottom of the gallery. */
const props = defineProps<{ gallery: GallerySnapshot; token: string }>();
const { quote } = useCart(() => props.gallery);
</script>
<template>
  <template v-if="quote.count">
    <div class="cart-bar__spacer" aria-hidden="true" />
    <aside class="cart-bar" aria-label="Корзина" data-testid="cart-bar">
      <span class="cart-bar__summary">
        <strong>{{ money(quote.total) }}</strong>
        <small>{{ quote.count }} {{ plural(quote.count, ['позиция', 'позиции', 'позиций']) }} в корзине</small>
      </span>
      <v-btn :to="'/g/' + token + '/cart'" color="primary" append-icon="mdi-arrow-right">Оформить</v-btn>
    </aside>
  </template>
</template>
<style scoped>
.cart-bar,
.cart-bar__spacer {
  display: none;
}
@media (max-width: 767px) {
  .cart-bar__spacer {
    display: block;
    height: 76px;
  }
  .cart-bar {
    position: fixed;
    inset: auto 0 0;
    z-index: 5;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--mf-space-3);
    padding: var(--mf-space-3) var(--mf-space-4) calc(var(--mf-space-3) + env(safe-area-inset-bottom));
    border-top: 1px solid var(--mf-color-border);
    background: var(--mf-color-surface);
    box-shadow: 0 -4px 16px rgba(15, 44, 62, 0.08);
  }
  .cart-bar__summary {
    display: grid;
    line-height: 1.3;
  }
  .cart-bar__summary strong {
    font-size: 1.125rem;
    font-variant-numeric: tabular-nums;
  }
  .cart-bar__summary small {
    color: var(--mf-color-text-secondary);
    font-size: var(--mf-text-xs);
  }
}
</style>
