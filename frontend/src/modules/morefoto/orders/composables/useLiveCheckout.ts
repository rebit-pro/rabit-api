import { computed, nextTick, reactive, shallowRef, watch } from 'vue';
import { useRouter } from 'vue-router';
import { forgetLiveCart, liveQuote, liveState, refreshStorefront } from '../../commerce/services/storefront';
import type { GallerySnapshot } from '../../gallery/types';
import { apiProblem, liveOrdersApi } from '../live/api';
import { checkoutOutcome, isCreatedOrder, newRequestId, showsCheckoutRecovery, type CheckoutSubmission } from '../live/rules';
import type { CheckoutBody } from '../live/types';
import { validateBuyer } from '../services/validation';
import type { BuyerErrors, CheckoutDraft } from '../types';
import { acceptedDocuments, isDraftFresh, ORDER_DOCUMENTS } from '../../legal/rules';
import { useLegalCatalog } from '../../legal/useLegalCatalog';

interface LiveDraft extends CheckoutDraft {
  /** Exact body of an attempt whose outcome is unknown; it is resent unchanged with the same key. */
  pending: CheckoutBody | null;
}
interface StoredDraft extends LiveDraft {
  savedAt: number;
}
function storageKey(groupId: string): string {
  return 'morefoto:live:checkout:' + groupId;
}
function readDraft(groupId: string): LiveDraft {
  let stored: Partial<StoredDraft> = {};
  try {
    stored = JSON.parse(localStorage.getItem(storageKey(groupId)) ?? '{}') as Partial<StoredDraft>;
  } catch {
    stored = {};
  }
  // Contacts live here for a week at most; an unconfirmed attempt is kept, because it may hold a created order.
  const pending = stored.pending && typeof stored.pending === 'object' ? stored.pending : null;
  if (pending === null && !isDraftFresh(stored.savedAt, Date.now())) stored = {};
  return {
    name: typeof stored.name === 'string' ? stored.name : '',
    phone: typeof stored.phone === 'string' ? stored.phone : '',
    email: typeof stored.email === 'string' ? stored.email : '',
    comment: typeof stored.comment === 'string' ? stored.comment : '',
    receiptChannel: 'email',
    reviewed: false,
    requestId: typeof stored.requestId === 'string' && /^[a-f0-9]{32}$/.test(stored.requestId) ? stored.requestId : newRequestId(),
    pending
  };
}

export function useLiveCheckout(gallery: GallerySnapshot, token: string) {
  const router = useRouter();
  const state = liveState(gallery.groupId);
  const quote = computed(() => liveQuote(gallery.groupId));
  const catalog = computed(() => state.catalog);
  const draft = reactive(readDraft(gallery.groupId));
  const submission = shallowRef<CheckoutSubmission>('idle');
  // A running server recalculation also blocks submission and is shown as loading, not as a silently disabled button.
  const busy = computed(() => submission.value !== 'idle' || state.busy);
  // An unconfirmed attempt is recovered on its own screen, independent of a fresh quote or an open group.
  const recovering = computed(() => showsCheckoutRecovery(draft.pending !== null, submission.value));
  const error = shallowRef('');
  const errors = shallowRef<BuyerErrors>({});
  const oldTotal = shallowRef<number | null>(null);
  const capabilities = shallowRef({ maxAvailable: false, receiptAvailable: false });
  const previous = shallowRef(null);
  const { catalog: legal, reload: reloadLegal } = useLegalCatalog();
  // Separate unticked checkboxes for the consent and the offer; they are never stored with the draft.
  const consents = reactive({ consent: false, offer: false });
  watch(
    draft,
    () => localStorage.setItem(storageKey(gallery.groupId), JSON.stringify({ ...draft, reviewed: false, savedAt: Date.now() })),
    { flush: 'sync' }
  );
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
      quoteToken: state.quoteToken ?? '',
      consents: acceptedDocuments(legal.value, ORDER_DOCUMENTS) ?? []
    };
  }
  function consentErrors(): BuyerErrors {
    const found: BuyerErrors = {};
    if (!consents.consent) found.consent = 'Нужно согласие на обработку персональных данных.';
    if (!consents.offer) found.offer = 'Примите условия оферты.';
    return found;
  }
  async function submit(): Promise<void> {
    if (busy.value) return;
    error.value = '';
    // A stored attempt may already have created the order, so its key is released only by a proof from the server.
    const recovery = draft.pending !== null;
    errors.value = recovery ? {} : { ...validateBuyer(draft, false), ...consentErrors() };
    if (Object.keys(errors.value).length) {
      await focus('[name="buyer-' + Object.keys(errors.value)[0] + '"]');
      return;
    }
    if (!recovery && acceptedDocuments(legal.value, ORDER_DOCUMENTS) === null) {
      error.value = 'Не удалось загрузить согласие и оферту. Обновите страницу и попробуйте ещё раз.';
      void reloadLegal();
      await focus('#checkout-error');
      return;
    }
    submission.value = recovery ? 'recovery' : 'first';
    const attempt = draft.pending ?? body();
    draft.pending = attempt;
    try {
      const order: unknown = await liveOrdersApi.create(token, attempt, draft.requestId);
      if (!isCreatedOrder(order)) throw new Error('Unconfirmed order response.');
      forgetLiveCart(gallery.groupId);
      localStorage.removeItem(storageKey(gallery.groupId));
      await router.replace('/orders/access/' + order.accessKey);
      return;
    } catch (cause) {
      const outcome = checkoutOutcome(apiProblem(cause), recovery);
      if (outcome.kind === 'unknown') {
        error.value = outcome.message;
        await focus('#checkout-error');
        return;
      }
      // The answer proves that nothing is stored under this key: the next attempt starts with a fresh one.
      draft.pending = null;
      draft.requestId = newRequestId();
      if (outcome.kind === 'field') {
        if (outcome.errors.consent) {
          consents.consent = false;
          consents.offer = false;
          void reloadLegal();
        }
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
      submission.value = 'idle';
    }
  }
  return { quote, catalog, draft, busy, error, errors, oldTotal, capabilities, previous, canSubmit, recovering, submit, consents, legal };
}
