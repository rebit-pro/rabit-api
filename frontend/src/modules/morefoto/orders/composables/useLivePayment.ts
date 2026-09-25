import { shallowRef, watch, type Ref } from 'vue';
import { useRouter } from 'vue-router';
import { apiProblem } from '../live/api';
import { livePaymentsApi } from '../live/payments-api';
import { isUncertain, paymentError, rememberOrderKey, startKey, type UncertainStart } from '../live/payment-rules';
import type { PaymentMethod, PaymentQuote } from '../live/payment-types';
import { newRequestId } from '../live/rules';

/**
 * Payment of the buyer's order: the server quote, the provider page. A start without an answer keeps its key and
 * method; the server quote then shows whether it created an attempt before another method gets a new key.
 */
export function useLivePayment(orderKey: Ref<string>, active: Ref<boolean>) {
  const router = useRouter();
  const quote = shallowRef<PaymentQuote | null>(null);
  const loading = shallowRef(false);
  const starting = shallowRef<PaymentMethod | null>(null);
  const uncertain = shallowRef<UncertainStart | null>(null);
  const error = shallowRef('');
  async function load(): Promise<void> {
    loading.value = true;
    try {
      quote.value = await livePaymentsApi.quote(orderKey.value);
      // The server answered: an attempt of the lost request is shown as active, otherwise none was created.
      uncertain.value = null;
    } catch (cause) {
      error.value = paymentError(apiProblem(cause));
    } finally {
      loading.value = false;
    }
  }
  async function pay(method: PaymentMethod) {
    const current = quote.value;
    if (!current || starting.value || (uncertain.value && uncertain.value.method !== method)) return;
    starting.value = method;
    error.value = '';
    const key = startKey(uncertain.value, method, newRequestId);
    try {
      const attempt = await livePaymentsApi.start(
        orderKey.value,
        {
          orderVersion: current.orderVersion,
          precedingAttemptId: current.precedingAttemptId,
          quoteToken: current.quoteToken,
          paymentMethod: method
        },
        key
      );
      uncertain.value = null;
      rememberOrderKey(localStorage, attempt.id, orderKey.value);
      if (attempt.redirectUrl) {
        window.location.assign(attempt.redirectUrl);
        return;
      }
      await router.push('/orders/payment/' + attempt.id);
    } catch (cause) {
      const problem = apiProblem(cause);
      if (isUncertain(problem)) {
        uncertain.value = { method, key };
        error.value = 'Не удалось получить ответ сервера. Проверяем, началась ли оплата; повтор тем же способом не создаст второй платёж.';
      } else {
        uncertain.value = null;
        error.value = paymentError(problem);
      }
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
  return { quote, loading, starting, uncertain, error, load, pay, follow };
}
