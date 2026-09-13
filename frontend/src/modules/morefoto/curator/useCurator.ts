import { onMounted, onScopeDispose, shallowRef } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { loadCurator } from './service';
import type { CuratorWorkspace } from './types';
export function useCurator() {
  const auth = useAuthStore(),
    data = shallowRef<CuratorWorkspace | null>(null),
    loading = shallowRef(true),
    error = shallowRef('');
  let run = 0,
    alive = true;
  async function reload() {
    const id = ++run;
    loading.value = true;
    error.value = '';
    try {
      const next = await loadCurator(auth.getAccessToken() ?? '');
      if (alive && id === run) data.value = next;
    } catch (e) {
      if (alive && id === run) {
        data.value = null;
        error.value = e instanceof Error ? e.message : 'Не удалось загрузить данные.';
      }
    } finally {
      if (alive && id === run) loading.value = false;
    }
  }
  let observed = '';
  function changed() {
    const next = ['organization:v1', 'orders:v1', 'production:v1', 'clock:now']
      .map((k) => localStorage.getItem('morefoto:demo:' + k))
      .join('|');
    if (next !== observed) {
      observed = next;
      void reload();
    }
  }
  onMounted(() => {
    changed();
    window.addEventListener('morefoto:demo:changed', changed);
    window.addEventListener('storage', changed);
  });
  onScopeDispose(() => {
    alive = false;
    window.removeEventListener('morefoto:demo:changed', changed);
    window.removeEventListener('storage', changed);
  });
  return { data, loading, error, reload };
}
