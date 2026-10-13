import { computed, onMounted, onScopeDispose, shallowRef, watch } from 'vue';
import { structureApi, structureError } from './api';
import type { StructurePage, StructureScope } from './model';
import type { UiTableSort } from '../ui/table-types';
import { useInstitutionName } from './useInstitutionName';
/** The institution search starts by itself once the typing pauses. */
const SEARCH_DELAY = 300;
/** `sort` is sent only for lists the server orders (the cabinet-wide shoots); null keeps the server's own order. */
export function useStructurePage(scope: StructureScope, initialSort: UiTableSort | null = null) {
  const snapshot = shallowRef<StructurePage | null>(null),
    loading = shallowRef(false),
    error = shallowRef(''),
    query = shallowRef(''),
    pageSize = shallowRef(25),
    sort = shallowRef<UiTableSort | null>(initialSort),
    institutionName = useInstitutionName(scope.kind === 'group' ? scope.institutionId : undefined);
  let generation = 0,
    alive = true,
    searchTimer = 0;
  async function reload(page = snapshot.value?.meta.page ?? 1): Promise<boolean> {
    window.clearTimeout(searchTimer);
    const request = ++generation;
    loading.value = true;
    error.value = '';
    try {
      const result = await structureApi.list(scope, page, pageSize.value, query.value.trim(), sort.value);
      if (!alive || request !== generation) return false;
      snapshot.value = result;
      return true;
    } catch (cause) {
      if (alive && request === generation) error.value = structureError(cause);
      return false;
    } finally {
      if (alive && request === generation) loading.value = false;
    }
  }
  watch(query, () => {
    window.clearTimeout(searchTimer);
    searchTimer = window.setTimeout(() => void reload(1), SEARCH_DELAY);
  });
  /** A new page size starts from the first page, as in every table of the cabinet. */
  function resize(size: number): void {
    pageSize.value = size;
    void reload(1);
  }
  function order(next: UiTableSort): void {
    sort.value = next;
    void reload(1);
  }
  onMounted(() => {
    void reload();
  });
  onScopeDispose(() => {
    alive = false;
    generation++;
    window.clearTimeout(searchTimer);
  });
  return {
    snapshot,
    loading,
    error,
    query,
    institutionName,
    reload,
    pageSize,
    resize,
    sort,
    order,
    page: computed(() => snapshot.value?.meta.page ?? 1),
    pages: computed(() => Math.max(1, snapshot.value?.meta.totalPages ?? 1))
  };
}
