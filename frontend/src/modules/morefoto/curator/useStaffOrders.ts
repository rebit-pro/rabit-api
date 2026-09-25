import { computed, reactive, shallowRef, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { apiProblem, liveOrdersApi } from '../orders/live/api';
import {
  staffFilterKeys,
  staffFilterQuery,
  staffFiltersFromQuery,
  staffOrderError,
  staffScopePatch,
  type StaffScopeLevel
} from '../orders/live/rules';
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
  // Applying always starts from the first page unless the pager asks for another one.
  function apply(pageNumber = 1) {
    void router.replace({ query: staffFilterQuery(filters, pageNumber) });
  }
  function reset() {
    for (const key of staffFilterKeys) filters[key] = '';
    apply();
  }
  // Only a user's pick drops the dependent levels; filters restored from the URL keep all three.
  function selectScope(level: StaffScopeLevel, value: string | null) {
    Object.assign(filters, staffScopePatch(level, value ?? ''));
  }
  watch(() => [route.name, route.params.orderId, route.fullPath], reload, { immediate: true });
  return { orderId, filters, page, card, loading, error, reload, apply, reset, selectScope };
}
