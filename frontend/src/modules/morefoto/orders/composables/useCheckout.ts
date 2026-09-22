import { computed, nextTick, reactive, shallowRef, watch } from 'vue';
import { isMockApiEnabled } from '@/mocks/config';
import { useRouter } from 'vue-router';
import { quoteCart } from '../../commerce/services/cart';
import { getCatalog } from '../../commerce/mocks/catalog';
import type { GallerySnapshot } from '../../gallery/types';
import { randomKey, readOrders } from '../services/orders';
import {
  checkoutCapabilities,
  CheckoutChangedError,
  CheckoutValidationError,
  createOrder,
  quoteSignature,
  readCheckoutDraft,
  saveCheckoutDraft
} from '../services/checkout';
import { validateBuyer } from '../services/validation';
import type { BuyerErrors } from '../types';
import { useLiveCheckout } from './useLiveCheckout';
export function useCheckout(gallery: GallerySnapshot, token: string) {
  return isMockApiEnabled ? useDemoCheckout(gallery, token) : useLiveCheckout(gallery, token);
}
function useDemoCheckout(gallery: GallerySnapshot, token: string) {
  const router = useRouter();
  const quote = shallowRef(quoteCart(gallery));
  const catalog = shallowRef(getCatalog(gallery.groupId));
  const draft = reactive(readCheckoutDraft(gallery.groupId));
  const busy = shallowRef(false);
  const error = shallowRef('');
  const errors = shallowRef<BuyerErrors>({});
  const oldTotal = shallowRef<number | null>(null);
  const capabilities = shallowRef({ ...checkoutCapabilities(), receiptAvailable: true });
  const previous = computed(() => readOrders().find((order) => order.requestId === draft.requestId && order.groupId === gallery.groupId));
  // A new non-empty cart is an explicit new purchase; an empty one can recover the previous result.
  if (previous.value && quote.value.lines.length) {
    draft.requestId = randomKey();
    saveCheckoutDraft(gallery.groupId, { ...draft });
  }
  watch(draft, () => saveCheckoutDraft(gallery.groupId, { ...draft }), { flush: 'sync' });
  const canSubmit = computed(() => quote.value.lines.length > 0 && !quote.value.invalid.length && gallery.state === 'open');
  async function focusError() {
    await nextTick();
    const first = Object.keys(errors.value)[0];
    if (first) document.querySelector<HTMLInputElement>('[name="buyer-' + first + '"]')?.focus();
  }
  async function submit() {
    if (busy.value) return;
    error.value = '';
    capabilities.value = { ...checkoutCapabilities(), receiptAvailable: true };
    errors.value = validateBuyer(draft, capabilities.value.maxAvailable);
    if (Object.keys(errors.value).length) {
      await focusError();
      return;
    }
    busy.value = true;
    try {
      const result = await createOrder(token, { ...draft }, draft.requestId, quoteSignature(quote.value));
      await router.replace('/orders/access/' + result.accessKey);
    } catch (cause) {
      error.value = cause instanceof Error ? cause.message : 'Не удалось создать заказ. Заполнение сохранено, попробуйте ещё раз.';
      if (cause instanceof CheckoutChangedError) {
        oldTotal.value = quote.value.total;
        quote.value = cause.quote;
        catalog.value = getCatalog(gallery.groupId);
        draft.reviewed = false;
        await nextTick();
        document.getElementById('checkout-error')?.focus();
      }
      if (cause instanceof CheckoutValidationError) {
        errors.value = cause.fields;
        await focusError();
      }
    } finally {
      busy.value = false;
    }
  }
  return { quote, catalog, draft, busy, error, errors, oldTotal, capabilities, previous, canSubmit, submit };
}
