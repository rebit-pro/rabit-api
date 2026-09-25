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
  // A request outlives the page or its attempt: an answer of an older generation changes nothing and plans no timer (#83).
  let generation = 0;
  function restart(): number {
    clearTimeout(timer);
    return ++generation;
  }
  async function poll(current: number) {
    const key = orderKey.value;
    if (!key) return;
    try {
      const next = await livePaymentsApi.attempt(key, attemptId.value);
      if (current !== generation) return;
      attempt.value = next;
      error.value = '';
    } catch (cause) {
      if (current !== generation) return;
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
    else timer = setTimeout(() => void poll(current), delay);
  }
  function start() {
    const current = restart();
    attempt.value = null;
    error.value = '';
    waitOver.value = false;
    orderKey.value = recallOrderKey(localStorage, attemptId.value);
    startedAt = Date.now();
    void poll(current);
  }
  function recheck() {
    const current = restart();
    waitOver.value = false;
    startedAt = Date.now();
    void poll(current);
  }
  watch(attemptId, start, { immediate: true });
  onBeforeUnmount(restart);
  return { attempt, orderKey, error, waitOver, recheck };
}
