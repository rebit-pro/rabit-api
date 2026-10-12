import { computed, reactive, shallowRef, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { apiProblem } from '../orders/live/api';
import { livePaymentsApi } from '../orders/live/payments-api';
import { paymentFiltersFromQuery, staffPaymentError } from '../orders/live/payment-rules';
import type { PaymentPage, StaffPaymentCard } from '../orders/live/payment-types';
import { tableViewFromQuery, tableViewQuery, type TableView } from '../ui/table-query';
import type { UiTableSort } from '../ui/table-types';

/** Columns the server orders by (#92 DEC-05); newest first by default. */
export const PAYMENT_SORTS = ['orderNumber', 'paymentMethod', 'amount', 'status', 'createdAt'] as const;
export const PAYMENT_SORT: UiTableSort = { key: 'createdAt', direction: 'desc' };

/** Payment registry of the staff scope: filters live in the URL, the card opens by attempt ID. */
export function usePaymentRegistry() {
  const route = useRoute();
  const router = useRouter();
  const attemptId = computed(() => (route.name === 'WorkPayment' ? String(route.params.attemptId ?? '') : ''));
  const filters = reactive(paymentFiltersFromQuery(route.query));
  const view = shallowRef<TableView>(tableViewFromQuery(route.query, PAYMENT_SORTS, PAYMENT_SORT));
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
        view.value = tableViewFromQuery(route.query, PAYMENT_SORTS, PAYMENT_SORT);
        const result = await livePaymentsApi.search(filters, view.value);
        if (id === request) page.value = result;
      }
    } catch (cause) {
      if (id === request) error.value = staffPaymentError(apiProblem(cause));
    } finally {
      if (id === request) loading.value = false;
    }
  }
  function apply(pageNumber = 1, change: Partial<TableView> = {}) {
    const query: Record<string, string> = tableViewQuery({ ...view.value, page: pageNumber, ...change }, PAYMENT_SORT);
    for (const key of ['status', 'orderNumber', 'dateFrom', 'dateTo', 'late'] as const) {
      if (filters[key].trim() !== '') query[key] = filters[key].trim();
    }
    void router.replace({ query });
  }
  function reset() {
    Object.assign(filters, { status: '', orderNumber: '', dateFrom: '', dateTo: '', late: '' });
    apply();
  }
  watch(() => [route.name, route.params.attemptId, route.fullPath], reload, { immediate: true });
  return { attemptId, filters, view, page, card, loading, error, reload, apply, reset };
}
