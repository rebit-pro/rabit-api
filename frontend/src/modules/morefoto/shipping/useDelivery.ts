import { onMounted, onScopeDispose, shallowRef } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { loadDelivery } from './service';
import type { DeliveryView } from './types';
export function useDelivery() {
  const auth = useAuthStore(),
    data = shallowRef<DeliveryView | null>(null),
    loading = shallowRef(true),
    error = shallowRef('');
  let run = 0,
    alive = true,
    observed = '';
  async function reload() {
    const id = ++run;
    loading.value = true;
    error.value = '';
    try {
      const next = await loadDelivery(auth.getAccessToken() ?? '');
      if (alive && id === run) data.value = next;
    } catch (e) {
      if (alive && id === run) {
        data.value = null;
        error.value = e instanceof Error ? e.message : 'Не удалось загрузить доставку.';
      }
    } finally {
      if (alive && id === run) loading.value = false;
    }
  }
  function changed() {
    const next = ['organization:v1', 'orders:v1', 'photos:v1', 'production:v1', 'clock:now']
      .map((k) => localStorage.getItem('morefoto:demo:' + k))
      .join('|');
    if (next !== observed) {
      observed = next;
      void reload();
    }
  }
  onMounted(() => {
    changed();
    window.addEventListener('storage', changed);
    window.addEventListener('morefoto:demo:changed', changed);
  });
  onScopeDispose(() => {
    alive = false;
    window.removeEventListener('storage', changed);
    window.removeEventListener('morefoto:demo:changed', changed);
  });
  return { data, loading, error, reload };
}
