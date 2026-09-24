import { computed, onMounted, onScopeDispose, reactive, shallowRef } from 'vue';
import { staffApi, staffError } from './api';
import type { StaffFilters, StaffPage } from './model';
export function useStaffManagement() {
  const snapshot = shallowRef<StaffPage | null>(null);
  const loading = shallowRef(false);
  const error = shallowRef('');
  const filters = reactive<StaffFilters>({ q: '', role: null, accountStatus: null });
  let generation = 0;
  let alive = true;
  async function reload(page = snapshot.value?.meta.page ?? 1): Promise<boolean> {
    const request = ++generation;
    loading.value = true;
    error.value = '';
    try {
      const result = await staffApi.list({ ...filters, q: filters.q.trim() }, page);
      if (!alive || request !== generation) return false;
      snapshot.value = result;
      return true;
    } catch (cause) {
      if (alive && request === generation) error.value = staffError(cause);
      return false;
    } finally {
      if (alive && request === generation) loading.value = false;
    }
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
    reload,
    page: computed(() => snapshot.value?.meta.page ?? 1),
    pages: computed(() => Math.max(1, snapshot.value?.meta.totalPages ?? 1))
  };
}
