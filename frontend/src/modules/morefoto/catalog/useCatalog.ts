import { computed, onMounted, onScopeDispose, shallowRef } from 'vue';
import { catalogApi, catalogError, type CatalogPage } from './api';

export function useCatalog() {
  const snapshot = shallowRef<CatalogPage | null>(null);
  const page = shallowRef(1);
  const loading = shallowRef(false);
  const error = shallowRef('');
  let generation = 0;
  let alive = true;
  async function reload(targetPage = page.value): Promise<boolean> {
    const request = ++generation;
    loading.value = true;
    error.value = '';
    try {
      const result = await catalogApi.list(targetPage);
      if (!alive || request !== generation) return false;
      snapshot.value = result;
      page.value = result.meta.page;
      return true;
    } catch (cause) {
      if (alive && request === generation) error.value = catalogError(cause);
      return false;
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
  const pages = computed(() => Math.max(1, Math.ceil((snapshot.value?.meta.total ?? 0) / (snapshot.value?.meta.pageSize ?? 25))));
  return { snapshot, page, pages, loading, error, reload };
}
