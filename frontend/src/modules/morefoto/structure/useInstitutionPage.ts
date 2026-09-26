import { onMounted, onScopeDispose, shallowRef } from 'vue';
import { structureApi, structureError, structureRemovalError } from './api';
import { runEach, type BulkResult } from '../ui/removal';
import type { InstitutionDetail } from './model';

/** The server maximum of one list: the widgets sort, filter and page shoots and groups themselves (#92 DEC-06). */
export const INSTITUTION_PAGE_SIZE = 100;

export function useInstitutionPage(institutionId: string) {
  const snapshot = shallowRef<InstitutionDetail | null>(null),
    loading = shallowRef(false),
    busy = shallowRef(false),
    error = shallowRef('');
  let generation = 0,
    alive = true;

  async function reload(): Promise<void> {
    const request = ++generation;
    loading.value = true;
    error.value = '';
    try {
      const result = await structureApi.institution(institutionId, { shootsPage: 1, groupsPage: 1 }, INSTITUTION_PAGE_SIZE);
      if (!alive || request !== generation) return;
      snapshot.value = result;
    } catch (cause) {
      if (alive && request === generation) {
        snapshot.value = null;
        error.value = structureError(cause);
      }
    } finally {
      if (alive && request === generation) loading.value = false;
    }
  }
  /** Shoots or groups one after another; a refused one keeps its reason and the rest go on (#92 DEC-07). */
  async function remove(kind: 'shoot' | 'group', ids: string[]): Promise<BulkResult> {
    const items: { id: string; name: string }[] = (kind === 'shoot' ? snapshot.value?.shoots.items : snapshot.value?.groups.items) ?? [];
    busy.value = true;
    try {
      const result = await runEach(
        items.filter((item) => ids.includes(item.id)),
        (item) => item.name,
        (item) => structureApi.remove(kind, item.id),
        structureRemovalError
      );
      if (alive) await reload();
      return result;
    } finally {
      busy.value = false;
    }
  }
  onMounted(() => {
    void reload();
  });
  onScopeDispose(() => {
    alive = false;
    generation++;
  });
  return { snapshot, loading, busy, error, reload, remove };
}
