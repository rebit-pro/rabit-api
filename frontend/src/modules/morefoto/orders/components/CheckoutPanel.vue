<script setup lang="ts">
import { computed } from 'vue';
import type { GallerySnapshot } from '../../gallery/types';
import { money } from '../../commerce/money';
import CartSummary from '../../commerce/components/CartSummary.vue';
import type { Checkout } from '../composables/useCheckout';
import CheckoutContacts from './CheckoutContacts.vue';
import ConsentField from '../../legal/components/ConsentField.vue';
import { findDocument } from '../../legal/rules';
/**
 * UI-DEC-04: the order is placed from the cart itself — total, email, consents and one button, no separate checkout page.
 * The cart owns the checkout state, so a lost attempt is recovered even when the cart has nothing to show.
 */
const props = defineProps<{ gallery: GallerySnapshot; checkout: Checkout }>();
const { quote, catalog, draft, busy, error, errors, oldTotal, capabilities, canSubmit, submit, consents, legal } = props.checkout;
const consentDocument = computed(() => findDocument(legal.value, 'buyer-consent'));
const offerDocument = computed(() => findDocument(legal.value, 'offer'));
/** UI-DEC-05: the composition is on the same page, so placing the order confirms it was reviewed. */
function place(): void {
  draft.reviewed = true;
  void submit();
}
</script>
<template>
  <CartSummary :quote="quote" :catalog="catalog" :staff="gallery.audience === 'staff'">
    <form class="checkout-panel" novalidate @submit.prevent="place">
      <v-alert v-if="error" id="checkout-error" type="warning" variant="tonal" density="compact" role="alert" tabindex="-1">
        {{ error }}
        <p v-if="oldTotal !== null" class="mt-2">Предыдущий итог: {{ money(oldTotal) }}. Новый итог: {{ money(quote.total) }}.</p>
      </v-alert>
      <CheckoutContacts
        :draft="draft"
        :errors="errors"
        :busy="busy"
        :max-available="capabilities.maxAvailable"
        :receipt-available="capabilities.receiptAvailable"
        :order-email="catalog.capabilities?.orderEmail === true"
        @change="Object.assign(draft, $event)"
      />
      <div v-if="consents && consentDocument && offerDocument" class="checkout-panel__consents">
        <ConsentField
          v-model="consents.consent"
          :document="consentDocument"
          name="buyer-consent"
          before="Даю"
          link="согласие на обработку персональных данных"
          :error-messages="errors.consent"
          :disabled="busy"
        />
        <ConsentField
          v-model="consents.offer"
          :document="offerDocument"
          name="buyer-offer"
          before="Принимаю условия"
          link="публичной оферты"
          :error-messages="errors.offer"
          :disabled="busy"
        />
      </div>
      <v-btn type="submit" color="primary" block size="large" :loading="busy" :disabled="busy || !canSubmit" data-testid="create-order"
        >Оформить заказ · {{ money(quote.total) }}</v-btn
      >
      <p class="checkout-panel__note">Оплата — сразу после оформления, на странице заказа.</p>
    </form>
  </CartSummary>
</template>
<style scoped>
.checkout-panel {
  display: grid;
  gap: var(--mf-space-3);
}
/* FB-4: the two consents sit close together; each line keeps a 36 px tap target. */
.checkout-panel__consents {
  display: grid;
  gap: var(--mf-space-1);
}
.checkout-panel__consents :deep(.v-selection-control) {
  --v-selection-control-size: 36px;
  min-height: 36px;
  align-items: flex-start;
}
.checkout-panel__consents :deep(.v-selection-control__wrapper) {
  margin-top: -6px;
}
.checkout-panel__consents :deep(.v-label) {
  padding-top: 2px;
  font-size: var(--mf-text-sm);
  line-height: 1.4;
  opacity: 1;
}
.checkout-panel__note {
  color: var(--mf-color-text-secondary);
  font-size: var(--mf-text-xs);
  text-align: center;
}
</style>
