import { onMounted, onScopeDispose, shallowRef } from 'vue';
import { useRoute } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { getDemoNow } from '../mocks/clock';
import { loadHandoff } from './service';
import type { HandoffWorkspace } from './types';
export function useHandoff(load: (token: string, requestId?: string) => Promise<HandoffWorkspace> = loadHandoff) {
  const route = useRoute(),
    auth = useAuthStore(),
    data = shallowRef<HandoffWorkspace | null>(null),
    loading = shallowRef(true),
    error = shallowRef('');
  let run = 0,
    alive = true;
  async function reload() {
    const id = ++run;
    loading.value = true;
    error.value = '';
    try {
      const requestId = typeof route.params.requestId === 'string' ? route.params.requestId : undefined;
      const next = await load(auth.getAccessToken() ?? '', requestId);
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
  const events = ['morefoto:organization:changed', 'morefoto:photos:changed', 'morefoto:management:changed'];
  function storage(e: StorageEvent) {
    if (
      !e.key ||
      ['organization:v1', 'photos:v1', 'catalog:v1', 'group-conditions:v1', 'clock:now'].some((k) => e.key === 'morefoto:demo:' + k)
    )
      void reload();
  }
  function clockChanged() {
    if (data.value && data.value.now !== getDemoNow()) void reload();
  }
  onMounted(() => {
    void reload();
    events.forEach((e) => window.addEventListener(e, reload));
    window.addEventListener('storage', storage);
    window.addEventListener('morefoto:demo:changed', clockChanged);
  });
  onScopeDispose(() => {
    alive = false;
    events.forEach((e) => window.removeEventListener(e, reload));
    window.removeEventListener('storage', storage);
    window.removeEventListener('morefoto:demo:changed', clockChanged);
  });
  return { auth, data, loading, error, reload };
}
