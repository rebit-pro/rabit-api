import { computed, onMounted, onScopeDispose, shallowRef } from 'vue';
import { structureApi, structureError } from './api';
import type { StructurePage, StructureScope } from './model';
export function useStructurePage(scope: StructureScope) {
  const snapshot = shallowRef<StructurePage | null>(null),
    loading = shallowRef(false),
    error = shallowRef(''),
    query = shallowRef('');
  let generation = 0,
    alive = true;
  async function reload(page = snapshot.value?.meta.page ?? 1): Promise<boolean> {
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
  onMounted(() => {
    void reload();
  });
  onScopeDispose(() => {
    alive = false;
    generation++;
  });
  return {
    snapshot,
    loading,
    error,
    query,
    reload,
    page: computed(() => snapshot.value?.meta.page ?? 1),
    pages: computed(() => Math.max(1, snapshot.value?.meta.totalPages ?? 1))
  };
}
