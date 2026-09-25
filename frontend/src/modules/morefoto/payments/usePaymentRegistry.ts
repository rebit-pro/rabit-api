import { computed, reactive, shallowRef, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { apiProblem } from '../orders/live/api';
import { livePaymentsApi } from '../orders/live/payments-api';
import { paymentFiltersFromQuery, staffPaymentError } from '../orders/live/payment-rules';
import type { PaymentPage, StaffPaymentCard } from '../orders/live/payment-types';

/** Payment registry of the staff scope: filters live in the URL, the card opens by attempt ID. */
export function usePaymentRegistry() {
  const route = useRoute();
  const router = useRouter();
  const attemptId = computed(() => (route.name === 'WorkPayment' ? String(route.params.attemptId ?? '') : ''));
  const filters = reactive(paymentFiltersFromQuery(route.query));
  const page = shallowRef<PaymentPage | null>(null);
  const card = shallowRef<StaffPaymentCard | null>(null);
  const loading = shallowRef(false);
  const error = shallowRef('');
  let request = 0;
  async function reload() {
    const id = ++request;
    loading.value = true;
    error.value = '';
    try {
      if (attemptId.value) {
        card.value = null;
        const result = await livePaymentsApi.detail(attemptId.value);
        if (id === request) card.value = result;
      } else {
        Object.assign(filters, paymentFiltersFromQuery(route.query));
        const result = await livePaymentsApi.search(filters);
        if (id === request) page.value = result;
      }
    } catch (cause) {
      if (id === request) error.value = staffPaymentError(apiProblem(cause));
    } finally {
      if (id === request) loading.value = false;
    }
  }
  function apply(pageNumber = 1) {
    const query: Record<string, string> = {};
    for (const key of ['status', 'orderNumber', 'dateFrom', 'dateTo', 'late'] as const) {
      if (filters[key].trim() !== '') query[key] = filters[key].trim();
    }
    if (pageNumber > 1) query.page = String(pageNumber);
    void router.replace({ query });
  }
  function reset() {
    Object.assign(filters, { status: '', orderNumber: '', dateFrom: '', dateTo: '', late: '' });
    apply();
  }
  watch(() => [route.name, route.params.attemptId, route.fullPath], reload, { immediate: true });
  return { attemptId, filters, page, card, loading, error, reload, apply, reset };
}
