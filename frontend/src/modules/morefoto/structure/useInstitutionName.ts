import { onMounted, onScopeDispose, shallowRef } from 'vue';
import { structureApi } from './api';
/**
 * Name of the institution for the breadcrumbs of its shoot pages: one light request (pages of one row). Without it
 * the breadcrumbs fall back to a plain word; the page itself reports access and loading errors.
 */
export function useInstitutionName(institutionId: string | undefined) {
  const name = shallowRef('');
  let alive = true;
  onMounted(async () => {
    if (!institutionId) return;
    try {
      const institution = await structureApi.institution(institutionId, { shootsPage: 1, groupsPage: 1 }, 1);
      if (alive) name.value = institution.name;
    } catch {
      /* The breadcrumbs keep the plain word. */
    }
  });
  onScopeDispose(() => {
    alive = false;
  });
  return name;
}
