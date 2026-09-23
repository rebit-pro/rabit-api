import { onMounted, shallowRef, watch } from 'vue';
import { useRoute } from 'vue-router';
import { isMockApiEnabled } from '@/mocks/config';
import { linksApi } from '../handoff/links-api';
import { staffRequestsApi } from '../handoff/api';
import type { StaffRole } from '../types';

const REFRESH_MS = 60_000;

/**
 * Badges of the sidebar for the organizer and the curator: groups whose link waits for a check and staff lists
 * waiting for review. Two one-row requests at most once a minute; a failure only hides the badges.
 */
export function useNavigationCounters(role: () => StaffRole | null) {
  const counters = shallowRef<Record<string, number>>({});
  const route = useRoute();
  let loadedAt = 0;

  async function load(force = false): Promise<void> {
    const current = role();
    if (isMockApiEnabled || ('organizer' !== current && 'curator' !== current)) {
      counters.value = {};
      return;
    }
    if (!force && Date.now() - loadedAt < REFRESH_MS) return;
    loadedAt = Date.now();
    try {
      const [links, requests] = await Promise.all([linksApi.list(1, { state: 'preparing', pageSize: 1 }), staffRequestsApi.list(1, 1)]);
      const summary = links.meta.summary;
      counters.value = {
        '/cabinet/links': summary ? Math.max(0, summary.byState.preparing - summary.prepared) : 0,
        '/cabinet/staff-requests': requests.meta.summary?.byStatus.submitted ?? 0
      };
    } catch {
      counters.value = {};
    }
  }

  onMounted(() => void load(true));
  watch(
    () => route.path,
    () => void load()
  );
  watch(role, () => void load(true));
  return counters;
}
