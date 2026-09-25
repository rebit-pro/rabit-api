import { computed, onMounted, onScopeDispose, reactive, shallowRef } from 'vue';
import { staffApi, staffError } from './api';
import type { StaffFilters, StaffPage, StaffSort, StaffSummary } from './model';
export interface StaffRemoval {
  removed: number;
  failed: { name: string; reason: string }[];
}
export function useStaffManagement() {
  const snapshot = shallowRef<StaffPage | null>(null);
  const loading = shallowRef(false);
  const error = shallowRef('');
  const filters = reactive<StaffFilters>({ q: '', role: null, accountStatus: null });
  const sort = shallowRef<StaffSort>({ key: 'name', direction: 'asc' });
  const pageSize = shallowRef(25);
  const selected = shallowRef<string[]>([]);
  // Selection spans pages, so the names of rows seen earlier stay known for the confirmation.
  const seen = new Map<string, StaffSummary>();
  let generation = 0;
  let alive = true;
  async function reload(page = snapshot.value?.meta.page ?? 1): Promise<boolean> {
    const request = ++generation;
    loading.value = true;
    error.value = '';
    try {
      const result = await staffApi.list({ ...filters, q: filters.q.trim() }, page, pageSize.value, sort.value);
      if (!alive || request !== generation) return false;
      for (const item of result.items) seen.set(String(item.id), item);
      snapshot.value = result;
      return true;
    } catch (cause) {
      if (alive && request === generation) error.value = staffError(cause);
      return false;
    } finally {
      if (alive && request === generation) loading.value = false;
    }
  }
  function setSort(next: StaffSort): void {
    sort.value = next;
    void reload(1);
  }
  function setPageSize(size: number): void {
    pageSize.value = size;
    void reload(1);
  }
  function staff(ids: string[]): StaffSummary[] {
    return ids.flatMap((id) => seen.get(id) ?? []);
  }
  /** One request per person keeps each refusal attached to its name; the list is reloaded once at the end. */
  async function remove(ids: string[]): Promise<StaffRemoval> {
    const result: StaffRemoval = { removed: 0, failed: [] };
    for (const item of staff(ids)) {
      try {
        await staffApi.remove(item.id);
        result.removed++;
        seen.delete(String(item.id));
      } catch (cause) {
        result.failed.push({ name: item.name, reason: staffError(cause) });
      }
    }
    selected.value = selected.value.filter((id) => seen.has(id));
    await reload();
    const meta = snapshot.value?.meta;
    if (meta && !snapshot.value?.items.length && meta.page > 1) await reload(Math.max(1, meta.totalPages));
    return result;
  }
  onMounted(() => void reload());
  onScopeDispose(() => {
    alive = false;
    generation++;
  });
  return {
    snapshot,
    loading,
    error,
    filters,
    sort,
    pageSize,
    selected,
    reload,
    setSort,
    setPageSize,
    staff,
    remove,
    page: computed(() => snapshot.value?.meta.page ?? 1)
  };
}
