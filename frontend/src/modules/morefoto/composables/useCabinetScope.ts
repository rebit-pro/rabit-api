import { onScopeDispose, shallowRef, watch } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { loadCabinetScope } from '../services/cabinet';
import type { ScopeSnapshot } from '../types';
export function useCabinetScope() {
  const auth = useAuthStore(),
    scope = shallowRef<ScopeSnapshot | null>(null),
    loading = shallowRef(false),
    error = shallowRef('');
  let requestId = 0;
  async function reload(): Promise<void> {
    const current = ++requestId;
    scope.value = null;
    error.value = '';
    loading.value = false;
    const token = auth.getAccessToken();
    if (!token) return;
    loading.value = true;
    try {
      const data = await loadCabinetScope(token);
      if (current === requestId) scope.value = data;
    } catch (reason) {
      if (current === requestId) error.value = reason instanceof Error ? reason.message : 'Не удалось загрузить данные.';
    } finally {
      if (current === requestId) loading.value = false;
    }
  }
  const keys = ['organization:v1', 'orders:v1', 'photos:v1', 'catalog:v1', 'group-conditions:v1', 'clock:now'].map(
    (k) => 'morefoto:demo:' + k
  );
  let observed = keys.map((k) => localStorage.getItem(k));
  function changed() {
    const values = keys.map((k) => localStorage.getItem(k));
    if (values.some((v, i) => v !== observed[i])) {
      observed = values;
      void reload();
    }
  }
  function storage(event: StorageEvent) {
    if (event.key === null || keys.includes(event.key)) changed();
  }
  watch(() => auth.token, reload, { immediate: true });
  window.addEventListener('morefoto:demo:changed', changed);
  window.addEventListener('storage', storage);
  onScopeDispose(() => {
    window.removeEventListener('morefoto:demo:changed', changed);
    window.removeEventListener('storage', storage);
    requestId += 1;
  });
  return { scope, loading, error, reload };
}
