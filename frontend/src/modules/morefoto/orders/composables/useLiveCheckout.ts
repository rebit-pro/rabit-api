import { computed, nextTick, reactive, shallowRef, watch } from 'vue';
import { useRouter } from 'vue-router';
import { forgetLiveCart, liveQuote, liveState, refreshStorefront } from '../../commerce/services/storefront';
import type { GallerySnapshot } from '../../gallery/types';
import { apiProblem, liveOrdersApi } from '../live/api';
import { checkoutOutcome, newRequestId } from '../live/rules';
import type { CheckoutBody } from '../live/types';
import { validateBuyer } from '../services/validation';
import type { BuyerErrors, CheckoutDraft } from '../types';

interface LiveDraft extends CheckoutDraft {
  /** Exact body of an attempt whose outcome is unknown; it is resent unchanged with the same key. */
  pending: CheckoutBody | null;
}
function storageKey(groupId: string): string {
  return 'morefoto:live:checkout:' + groupId;
}
function readDraft(groupId: string): LiveDraft {
  let stored: Partial<LiveDraft> = {};
  try {
    stored = JSON.parse(localStorage.getItem(storageKey(groupId)) ?? '{}') as Partial<LiveDraft>;
  } catch {
    stored = {};
  }
  return {
    name: typeof stored.name === 'string' ? stored.name : '',
    phone: typeof stored.phone === 'string' ? stored.phone : '',
    email: typeof stored.email === 'string' ? stored.email : '',
    comment: typeof stored.comment === 'string' ? stored.comment : '',
    receiptChannel: 'email',
    reviewed: false,
    requestId: typeof stored.requestId === 'string' && /^[a-f0-9]{32}$/.test(stored.requestId) ? stored.requestId : newRequestId(),
    pending: stored.pending && typeof stored.pending === 'object' ? stored.pending : null
  };
}

export function useLiveCheckout(gallery: GallerySnapshot, token: string) {
  const router = useRouter();
  const state = liveState(gallery.groupId);
  const quote = computed(() => liveQuote(gallery.groupId));
  const catalog = computed(() => state.catalog);
  const draft = reactive(readDraft(gallery.groupId));
  const submitting = shallowRef(false);
  // A running server recalculation also blocks submission and is shown as loading, not as a silently disabled button.
  const busy = computed(() => submitting.value || state.busy);
  const error = shallowRef('');
  const errors = shallowRef<BuyerErrors>({});
  const oldTotal = shallowRef<number | null>(null);
  const capabilities = shallowRef({ maxAvailable: false, receiptAvailable: false });
  const previous = shallowRef(null);
  watch(draft, () => localStorage.setItem(storageKey(gallery.groupId), JSON.stringify({ ...draft, reviewed: false })), { flush: 'sync' });
  const canSubmit = computed(
    () =>
      state.catalog.capabilities?.purchaseEnabled === true &&
      gallery.state === 'open' &&
      quote.value.lines.length > 0 &&
      !quote.value.invalid.length &&
      state.quoteToken !== null
  );
  async function focus(selector: string) {
    await nextTick();
    document.querySelector<HTMLElement>(selector)?.focus();
  }
  function body(): CheckoutBody {
    return {
      lines: state.lines.map(({ assignmentId, productId, quantity }) => ({ assignmentId, productId, quantity })),
      buyer: { name: draft.name, phone: draft.phone, email: draft.email, comment: draft.comment, reviewed: draft.reviewed },
      quoteToken: state.quoteToken ?? ''
    };
  }
  async function submit(): Promise<void> {
    if (busy.value) return;
    error.value = '';
    errors.value = draft.pending ? {} : validateBuyer(draft, false);
    if (Object.keys(errors.value).length) {
      await focus('[name="buyer-' + Object.keys(errors.value)[0] + '"]');
      return;
    }
    submitting.value = true;
    const attempt = draft.pending ?? body();
    draft.pending = attempt;
    try {
      const order = await liveOrdersApi.create(token, attempt, draft.requestId);
      forgetLiveCart(gallery.groupId);
      localStorage.removeItem(storageKey(gallery.groupId));
      await router.replace('/orders/access/' + order.accessKey);
      return;
    } catch (cause) {
      const outcome = checkoutOutcome(apiProblem(cause));
      if (outcome.kind === 'unknown') {
        error.value = 'Ответ сервера не получен. Нажмите «Создать тестовый заказ» ещё раз: повтор вернёт тот же заказ и не создаст второй.';
        await focus('#checkout-error');
        return;
      }
      // A definitive answer means nothing was stored: the next attempt starts with a fresh key.
      draft.pending = null;
      draft.requestId = newRequestId();
      if (outcome.kind === 'field') {
        errors.value = outcome.errors;
        await focus('[name="buyer-' + Object.keys(outcome.errors)[0] + '"]');
        return;
      }
      if (outcome.kind === 'recalculate') {
        const before = quote.value.total;
        await refreshStorefront(gallery.groupId);
        oldTotal.value = quote.value.total === before ? null : before;
        draft.reviewed = false;
      }
      error.value = outcome.message;
      await focus('#checkout-error');
    } finally {
      submitting.value = false;
    }
  }
  return { quote, catalog, draft, busy, error, errors, oldTotal, capabilities, previous, canSubmit, submit };
}
