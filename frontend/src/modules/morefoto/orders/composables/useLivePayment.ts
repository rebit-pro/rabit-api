import { shallowRef, watch, type Ref } from 'vue';
import { useRouter } from 'vue-router';
import { apiProblem } from '../live/api';
import { livePaymentsApi } from '../live/payments-api';
import { paymentError, rememberOrderKey } from '../live/payment-rules';
import type { PaymentMethod, PaymentQuote } from '../live/payment-types';
import { newRequestId } from '../live/rules';

/** Payment of the buyer's order: the server quote, one attempt per click series, the provider page. */
export function useLivePayment(orderKey: Ref<string>, active: Ref<boolean>) {
  const router = useRouter();
  const quote = shallowRef<PaymentQuote | null>(null);
  const loading = shallowRef(false);
  const starting = shallowRef<PaymentMethod | null>(null);
  const error = shallowRef('');
  // The same key repeats a lost request safely; a fresh quote gets a fresh key.
  let requestId = newRequestId();
  async function load() {
    loading.value = true;
    error.value = '';
    try {
      quote.value = await livePaymentsApi.quote(orderKey.value);
      requestId = newRequestId();
    } catch (cause) {
      error.value = paymentError(apiProblem(cause));
    } finally {
      loading.value = false;
    }
  }
  async function pay(method: PaymentMethod) {
    const current = quote.value;
    if (!current || starting.value) return;
    starting.value = method;
    error.value = '';
    try {
      const attempt = await livePaymentsApi.start(
        orderKey.value,
        {
          orderVersion: current.orderVersion,
          precedingAttemptId: current.precedingAttemptId,
          quoteToken: current.quoteToken,
          paymentMethod: method
        },
        requestId
      );
      rememberOrderKey(localStorage, attempt.id, orderKey.value);
      if (attempt.redirectUrl) {
        window.location.assign(attempt.redirectUrl);
        return;
      }
      await router.push('/orders/payment/' + attempt.id);
    } catch (cause) {
      const problem = apiProblem(cause);
      error.value = paymentError(problem);
      if (
        ['QUOTE_CHANGED', 'ATTEMPT_CONFLICT', 'PAYMENT_METHOD_UNAVAILABLE', 'ORDER_ALREADY_PAID', 'PAYMENT_CLOSED'].includes(problem.code)
      )
        await load();
    } finally {
      starting.value = null;
    }
  }
  function follow(attemptId: string) {
    rememberOrderKey(localStorage, attemptId, orderKey.value);
    void router.push('/orders/payment/' + attemptId);
  }
  watch([orderKey, active], () => (active.value ? void load() : (quote.value = null)), { immediate: true });
  return { quote, loading, starting, error, load, pay, follow };
}
