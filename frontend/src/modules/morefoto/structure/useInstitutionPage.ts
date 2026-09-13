import { onMounted, onScopeDispose, shallowRef } from 'vue';
import { structureApi, structureError } from './api';
import type { InstitutionDetail, InstitutionPages } from './model';

export function useInstitutionPage(institutionId: string) {
  const snapshot = shallowRef<InstitutionDetail | null>(null),
    loading = shallowRef(false),
    error = shallowRef('');
  let generation = 0,
    alive = true;
  // Keep the requested pages on failure so retry does not silently reset either list.
  let requestedPages: InstitutionPages = { shootsPage: 1, groupsPage: 1 };

  async function reload(change: Partial<InstitutionPages> = {}): Promise<void> {
    requestedPages = { ...requestedPages, ...change };
    const request = ++generation;
    loading.value = true;
    error.value = '';
    try {
      const result = await structureApi.institution(institutionId, requestedPages);
      if (!alive || request !== generation) return;
      snapshot.value = result;
      requestedPages = { shootsPage: result.shoots.meta.page, groupsPage: result.groups.meta.page };
    } catch (cause) {
      if (alive && request === generation) {
        snapshot.value = null;
        error.value = structureError(cause);
      }
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
  return { snapshot, loading, error, reload };
}
