<script setup lang="ts">
import { buyerGuideHref, type BuyerGuideStep } from './buyerGuide';
// KK-DEC-05: a new tab keeps the cart, the typed email and the open frame where the buyer left them.
// #202: the gallery header shows the prominent variant — a filled brand button parents notice at once.
withDefaults(defineProps<{ step?: BuyerGuideStep; label?: string; prominent?: boolean }>(), {
  step: undefined,
  label: 'Как купить фото',
  prominent: false
});
</script>
<template>
  <a
    :href="buyerGuideHref(step)"
    target="_blank"
    rel="noopener"
    class="buyer-guide-link"
    :class="{ 'buyer-guide-link--prominent': prominent }"
    data-testid="buyer-guide-link"
  >
    <v-icon icon="mdi-book-open-page-variant-outline" :size="prominent ? 20 : 18" aria-hidden="true" />
    <span>{{ label }}</span>
    <span class="mf-sr-only">(откроется в новой вкладке)</span>
  </a>
</template>
<style scoped>
.buyer-guide-link {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  min-height: 36px;
  padding: 6px 14px;
  border-radius: var(--mf-radius-full);
  background: var(--mf-color-primary-soft);
  color: var(--mf-color-link);
  font-size: var(--mf-text-sm);
  font-weight: 600;
  line-height: 1.2;
  text-decoration: none;
}
.buyer-guide-link:hover {
  text-decoration: underline;
}
.buyer-guide-link--prominent {
  gap: 8px;
  min-height: 44px;
  padding: 10px 20px;
  background: var(--mf-color-primary);
  color: var(--mf-color-on-primary);
  font-size: var(--mf-text-md);
  box-shadow: var(--mf-shadow-md);
}
.buyer-guide-link--prominent:hover {
  background: var(--mf-color-primary-hover);
  text-decoration: none;
}
.buyer-guide-link--prominent:focus-visible {
  outline: 3px solid var(--mf-color-focus);
  outline-offset: 2px;
}
</style>
