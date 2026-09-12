import { computed, onMounted, onScopeDispose, shallowRef } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { getCatalog } from '../commerce/mocks/catalog';
import { loadOrganization } from '../organization/service';
import { organizationChangedEvent } from '../organization/repository';
import type { OrganizationSnapshot } from '../organization/types';
import { managementChangedEvent } from './service';
export function useManagement() {
  const auth = useAuthStore(),
    data = shallowRef<OrganizationSnapshot | null>(null),
    loading = shallowRef(true),
    error = shallowRef(''),
    version = shallowRef(0);
  let run = 0,
    alive = true;
  async function reload() {
    const current = ++run;
    loading.value = true;
    error.value = '';
    try {
      const value = await loadOrganization(auth.getAccessToken() ?? '');
      if (alive && current === run) {
        data.value = value;
        version.value++;
      }
    } catch (cause) {
      if (alive && current === run) error.value = cause instanceof Error ? cause.message : 'Не удалось загрузить данные.';
    } finally {
      if (alive && current === run) loading.value = false;
    }
  }
  function storage(e: StorageEvent) {
    if (!e.key || ['morefoto:demo:catalog:v1', 'morefoto:demo:group-conditions:v1', 'morefoto:demo:organization:v1'].includes(e.key))
      void reload();
  }
  onMounted(() => {
    void reload();
    window.addEventListener(managementChangedEvent, reload);
    window.addEventListener(organizationChangedEvent, reload);
    window.addEventListener('storage', storage);
  });
  onScopeDispose(() => {
    alive = false;
    window.removeEventListener(managementChangedEvent, reload);
    window.removeEventListener(organizationChangedEvent, reload);
    window.removeEventListener('storage', storage);
  });
  const catalog = computed(() => {
    void version.value;
    return getCatalog();
  });
  return { data, loading, error, reload, catalog, version };
}
