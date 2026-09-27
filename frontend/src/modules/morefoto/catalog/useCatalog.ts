import { computed, onMounted, onScopeDispose, shallowRef } from 'vue';
import { catalogActionError, catalogApi, catalogError, type CatalogPage, type CatalogProduct } from './api';
import { runEach, type BulkResult } from '../ui/removal';

/** The server maximum of one page: the catalogue is dozens of rows, so the table sorts and filters them itself (#92 DEC-06). */
export const CATALOG_PAGE_SIZE = 100;
const requestKey = () => crypto.randomUUID().replace(/-/g, '');

export function useCatalog() {
  const snapshot = shallowRef<CatalogPage | null>(null);
  const loading = shallowRef(false);
  const busy = shallowRef(false);
  const error = shallowRef('');
  const selected = shallowRef<string[]>([]);
  let generation = 0;
  let alive = true;
  async function reload(): Promise<boolean> {
    const request = ++generation;
    loading.value = true;
    error.value = '';
    try {
      const result = await catalogApi.list(1, CATALOG_PAGE_SIZE);
      if (!alive || request !== generation) return false;
      snapshot.value = result;
      const ids = new Set(result.data.items.map((product) => product.id));
      selected.value = selected.value.filter((id) => ids.has(id));
      return true;
    } catch (cause) {
      if (alive && request === generation) error.value = catalogError(cause);
      return false;
    } finally {
      if (alive && request === generation) loading.value = false;
    }
  }
  function products(ids: string[]): CatalogProduct[] {
    return (snapshot.value?.data.items ?? []).filter((product) => ids.includes(product.id));
  }
  async function bulk(targets: CatalogProduct[], action: (product: CatalogProduct) => Promise<void>): Promise<BulkResult> {
    busy.value = true;
    try {
      const result = await runEach(targets, (product) => product.name, action, catalogActionError);
      if (alive) await reload();
      return result;
    } finally {
      busy.value = false;
    }
  }
  /** Products already bought stay: the server refuses them one by one and the rest are removed. */
  function remove(ids: string[]): Promise<BulkResult> {
    return bulk(products(ids), (product) => catalogApi.remove(product.id));
  }
  /**
   * Existing PATCH saves one after another: each needs the current catalogue revision and returns the next one.
   * Products already in the target state are skipped, so a repeated click changes nothing.
   */
  function setActive(ids: string[], active: boolean): Promise<BulkResult> {
    let revision = snapshot.value?.data.revision ?? 0;
    return bulk(
      products(ids).filter((product) => product.active !== active),
      async ({ id, ...product }) => {
        revision = (await catalogApi.save({ id, key: requestKey(), body: { ...product, active, revision } })).revision;
      }
    );
  }
  onMounted(() => {
    void reload();
  });
  onScopeDispose(() => {
    alive = false;
    generation++;
  });
  /** More products than one page: the table has the first page only and says so. */
  const truncated = computed(() => (snapshot.value?.meta.total ?? 0) > (snapshot.value?.data.items.length ?? 0));
  return { snapshot, loading, busy, error, selected, truncated, reload, products, remove, setActive };
}
