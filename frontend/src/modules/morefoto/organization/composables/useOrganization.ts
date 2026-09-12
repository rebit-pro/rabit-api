import { onScopeDispose, shallowRef, watch } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { loadOrganization } from '../service';
import { organizationChangedEvent, organizationKey } from '../repository';
import type { OrganizationSnapshot } from '../types';

export function useOrganization() {
  const auth = useAuthStore();
  const data = shallowRef<OrganizationSnapshot | null>(null);
  const loading = shallowRef(false);
  const error = shallowRef('');
  let request = 0;
  async function reload() {
    const ticket = ++request;
    const token = auth.getAccessToken();
    error.value = '';
    if (!token) {
      data.value = null;
      loading.value = false;
      return;
    }
    loading.value = true;
    try {
      const snapshot = await loadOrganization(token);
      if (ticket === request) data.value = snapshot;
    } catch (reason) {
      if (ticket === request) {
        data.value = null;
        error.value = reason instanceof Error ? reason.message : 'Не удалось загрузить учреждения.';
      }
    } finally {
      if (ticket === request) loading.value = false;
    }
  }
  watch(
    () => auth.token,
    () => {
      data.value = null;
      void reload();
    },
    { immediate: true }
  );
  const changed = () => {
    void reload();
  };
  const storage = (event: StorageEvent) => {
    if (event.key === null || event.key === 'morefoto:demo:' + organizationKey) changed();
  };
  window.addEventListener(organizationChangedEvent, changed);
  window.addEventListener('storage', storage);
  onScopeDispose(() => {
    request++;
    window.removeEventListener(organizationChangedEvent, changed);
    window.removeEventListener('storage', storage);
  });
  return { data, loading, error, reload };
}
