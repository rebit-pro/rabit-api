import { computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import type { DashboardFilters } from './types';
export function useDashboardFilters() {
  const route = useRoute(),
    router = useRouter();
  const value = (key: string) => (typeof route.query[key] === 'string' ? (route.query[key] as string) : '');
  const filters = computed<DashboardFilters>(() => ({
    q: value('q'),
    shoot: value('shoot'),
    state: value('state')
  }));
  function patch(next: Partial<DashboardFilters>) {
    void router.replace({
      query: {
        ...route.query,
        ...Object.fromEntries(Object.entries(next).map(([k, v]) => [k, v || undefined]))
      }
    });
  }
  function reset() {
    void router.replace({ query: {} });
  }
  return { filters, patch, reset };
}
