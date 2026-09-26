import { shallowRef } from 'vue';
import { legalApi } from './api';
import type { LegalCatalog } from './types';

// One catalog per page load: footers, forms and pages share the same current versions and seller.
const catalog = shallowRef<LegalCatalog | null>(null);
const failed = shallowRef(false);
let loading: Promise<void> | null = null;

export function useLegalCatalog() {
  function load(force = false): Promise<void> {
    if (loading && !force) return loading;
    failed.value = false;
    loading = legalApi
      .catalog()
      .then((value) => {
        catalog.value = value;
      })
      .catch(() => {
        failed.value = true;
        loading = null;
      });
    return loading;
  }
  if (!catalog.value && !loading) void load();
  return { catalog, failed, reload: () => load(true) };
}
