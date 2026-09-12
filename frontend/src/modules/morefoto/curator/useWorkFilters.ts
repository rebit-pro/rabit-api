import { computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { emptyFilters } from './rules';
import type { WorkFilters } from './types';
import type { UiTableSort } from '../ui/table-types';
export function useWorkFilters() {
  const route = useRoute(),
    router = useRouter();
  const value = (k: string) => (typeof route.query[k] === 'string' ? (route.query[k] as string) : '');
  const filters = computed(() => Object.fromEntries(Object.keys(emptyFilters()).map((k) => [k, value(k)])) as unknown as WorkFilters);
  const page = computed(() => Math.max(1, Number(value('page')) || 1));
  const size = computed(() => ([10, 25, 50].includes(Number(value('size'))) ? Number(value('size')) : 10));
  const sort = computed<UiTableSort>(() => ({
    key: value('sort') || 'createdAt',
    direction: value('direction') === 'asc' ? 'asc' : 'desc'
  }));
  function query(patch: Record<string, string | number | undefined>) {
    void router.replace({ query: { ...route.query, ...patch } });
  }
  function patch(next: Partial<WorkFilters>) {
    query({ ...Object.fromEntries(Object.entries(next).map(([k, v]) => [k, v || undefined])), page: undefined });
  }
  function reset() {
    void router.replace({ query: {} });
  }
  return {
    filters,
    page,
    size,
    sort,
    patch,
    reset,
    setPage: (page: number) => query({ page }),
    setSize: (size: number) => query({ size, page: 1 }),
    setSort: (sort: UiTableSort) => query({ sort: sort.key, direction: sort.direction, page: 1 })
  };
}
