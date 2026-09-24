import { computed, onMounted, onScopeDispose, shallowRef, watch } from 'vue';
import { structureApi, structureError } from './api';
import type { StructurePage, StructureScope } from './model';
import { useInstitutionName } from './useInstitutionName';
/** The institution search starts by itself once the typing pauses. */
const SEARCH_DELAY = 300;
export function useStructurePage(scope: StructureScope) {
  const snapshot = shallowRef<StructurePage | null>(null),
    loading = shallowRef(false),
    error = shallowRef(''),
    query = shallowRef(''),
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
      const result = await structureApi.list(scope, page, 25, query.value.trim());
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
    page: computed(() => snapshot.value?.meta.page ?? 1),
    pages: computed(() => Math.max(1, snapshot.value?.meta.totalPages ?? 1))
  };
}
