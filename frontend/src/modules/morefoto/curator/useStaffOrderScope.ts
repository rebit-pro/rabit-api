import { computed, shallowRef, watch } from 'vue';
import { staffScopeOptions, type StaffScope } from '../orders/live/rules';
import { structureApi } from '../structure/api';
import type { Group, Institution, Shoot } from '../structure/model';

/** The largest page the Organization API returns. */
const pageSize = 100;

/**
 * Options of the order scope filters from the Organization API. The institution list and the institution card are
 * already limited to the staff member's area by the server, so a curator is offered only the assigned institutions.
 */
export function useStaffOrderScope(scope: StaffScope, active: () => boolean) {
  const institutions = shallowRef<Institution[]>([]);
  const shoots = shallowRef<Shoot[]>([]);
  const groups = shallowRef<Group[]>([]);
  const loading = shallowRef(false);
  const listFailed = shallowRef(false);
  const openFailed = shallowRef(false);
  let listed = false;
  let opened = '';
  let request = 0;
  // A single institution in scope needs no pick: its shoots and groups are offered at once.
  const institutionId = computed(() => scope.institutionId || (institutions.value.length === 1 ? (institutions.value[0]?.id ?? '') : ''));
  const options = computed(() =>
    staffScopeOptions({ institutions: institutions.value, shoots: shoots.value, groups: groups.value }, scope)
  );
  const error = computed(() =>
    listFailed.value || openFailed.value ? 'Не удалось загрузить учреждения, съёмки и группы. Заказы можно искать без них.' : ''
  );

  async function listInstitutions(): Promise<void> {
    listed = true;
    listFailed.value = false;
    try {
      const items: Institution[] = [];
      for (let page = 1, pages = 1; page <= pages; page++) {
        const result = await structureApi.list({ kind: 'institution' }, page, pageSize);
        items.push(...(result.items as Institution[]));
        pages = result.meta.totalPages;
      }
      institutions.value = items;
    } catch {
      listed = false;
      listFailed.value = true;
    }
  }
  async function openInstitution(id: string): Promise<void> {
    const current = ++request;
    opened = id;
    shoots.value = [];
    groups.value = [];
    openFailed.value = false;
    loading.value = id !== '';
    if (id === '') return;
    try {
      const nextShoots: Shoot[] = [];
      const nextGroups: Group[] = [];
      // Shoots and groups page independently; one request reads the same page of both.
      for (let page = 1, pages = 1; page <= pages; page++) {
        const detail = await structureApi.institution(id, { shootsPage: page, groupsPage: page }, pageSize);
        nextShoots.push(...detail.shoots.items);
        nextGroups.push(...detail.groups.items);
        pages = Math.max(detail.shoots.meta.totalPages, detail.groups.meta.totalPages);
      }
      if (current !== request) return;
      shoots.value = nextShoots;
      groups.value = nextGroups;
    } catch {
      if (current === request) openFailed.value = true;
    } finally {
      if (current === request) loading.value = false;
    }
  }
  watch(
    active,
    (visible) => {
      if (visible && !listed) void listInstitutions();
    },
    { immediate: true }
  );
  // The order card needs no options: they load once the list is shown and are kept for the way back.
  watch(
    () => (active() ? institutionId.value : opened),
    (id) => {
      if (id !== opened) void openInstitution(id);
    },
    { immediate: true }
  );
  return { institutionId, options, loading, error };
}
