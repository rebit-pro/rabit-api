<script setup lang="ts">
import { buyerGuideHref, type BuyerGuideStep } from './buyerGuide';
// KK-DEC-05: a new tab keeps the cart, the typed email and the open frame where the buyer left them.
withDefaults(defineProps<{ step?: BuyerGuideStep; label?: string }>(), { step: undefined, label: 'Как купить фото' });
</script>
<template>
  <a :href="buyerGuideHref(step)" target="_blank" rel="noopener" class="buyer-guide-link" data-testid="buyer-guide-link">
    <v-icon icon="mdi-book-open-page-variant-outline" size="18" aria-hidden="true" />
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
</style>
