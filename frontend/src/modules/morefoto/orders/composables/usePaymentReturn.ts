import { computed, onBeforeUnmount, shallowRef, watch } from 'vue';
import { useRoute } from 'vue-router';
import { apiProblem } from '../live/api';
import { livePaymentsApi } from '../live/payments-api';
import { forgetOrderKey, isFinal, nextPollDelay, paymentError, recallOrderKey } from '../live/payment-rules';
import type { PaymentAttempt } from '../live/payment-types';

/** Return from the provider page: asks the server for the result until it is final or the wait ends. */
export function usePaymentReturn() {
  const route = useRoute();
  const attemptId = computed(() => String(route.params.attemptId ?? ''));
  const orderKey = shallowRef<string | null>(null);
  const attempt = shallowRef<PaymentAttempt | null>(null);
  const error = shallowRef('');
  const waitOver = shallowRef(false);
  let timer: ReturnType<typeof setTimeout> | undefined;
  let startedAt = 0;
  async function poll() {
    const key = orderKey.value;
    if (!key) return;
    try {
      attempt.value = await livePaymentsApi.attempt(key, attemptId.value);
      error.value = '';
    } catch (cause) {
      const problem = apiProblem(cause);
      // A lost connection is a page state, not a refused payment: keep asking.
      error.value = problem.network ? 'Нет связи с сервером. Продолжаем проверять оплату.' : paymentError(problem);
      if (!problem.network) return;
    }
    if (attempt.value && isFinal(attempt.value)) {
      forgetOrderKey(localStorage, attemptId.value);
      return;
    }
    const delay = nextPollDelay(Date.now() - startedAt);
    if (null === delay) waitOver.value = true;
    else timer = setTimeout(poll, delay);
  }
  function start() {
    clearTimeout(timer);
    attempt.value = null;
    error.value = '';
    waitOver.value = false;
    orderKey.value = recallOrderKey(localStorage, attemptId.value);
    startedAt = Date.now();
    void poll();
  }
  function recheck() {
    waitOver.value = false;
    startedAt = Date.now();
    void poll();
  }
  watch(attemptId, start, { immediate: true });
  onBeforeUnmount(() => clearTimeout(timer));
  return { attempt, orderKey, error, waitOver, recheck };
}
