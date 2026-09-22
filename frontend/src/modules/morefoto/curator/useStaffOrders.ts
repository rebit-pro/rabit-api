import { computed, reactive, shallowRef, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { apiProblem, liveOrdersApi } from '../orders/live/api';
import { staffFiltersFromQuery, staffOrderError } from '../orders/live/rules';
import type { StaffOrderCard, StaffOrderPage } from '../orders/live/types';

export function useStaffOrders() {
  const route = useRoute();
  const router = useRouter();
  const orderId = computed(() => (route.name === 'WorkOrder' ? String(route.params.orderId ?? '') : ''));
  const filters = reactive(staffFiltersFromQuery(route.query));
  const page = shallowRef<StaffOrderPage | null>(null);
  const card = shallowRef<StaffOrderCard | null>(null);
  const loading = shallowRef(false);
  const error = shallowRef('');
  let request = 0;
  async function reload() {
    const id = ++request;
    loading.value = true;
    error.value = '';
    try {
      if (orderId.value) {
        card.value = null;
        const result = await liveOrdersApi.detail(orderId.value);
        if (id === request) card.value = result;
      } else {
        Object.assign(filters, staffFiltersFromQuery(route.query));
        const result = await liveOrdersApi.search(filters);
        if (id === request) page.value = result;
      }
    } catch (cause) {
      if (id === request) error.value = staffOrderError(apiProblem(cause));
    } finally {
      if (id === request) loading.value = false;
    }
  }
  function apply(pageNumber = 1) {
    const query: Record<string, string> = {};
    for (const key of ['q', 'paymentStatus', 'productionStatus', 'dateFrom', 'dateTo'] as const) {
      if (filters[key].trim() !== '') query[key] = filters[key].trim();
    }
    if (pageNumber > 1) query.page = String(pageNumber);
    void router.replace({ query });
  }
  function reset() {
    Object.assign(filters, { q: '', paymentStatus: '', productionStatus: '', dateFrom: '', dateTo: '' });
    apply();
  }
  watch(() => [route.name, route.params.orderId, route.fullPath], reload, { immediate: true });
  return { orderId, filters, page, card, loading, error, reload, apply, reset };
}
