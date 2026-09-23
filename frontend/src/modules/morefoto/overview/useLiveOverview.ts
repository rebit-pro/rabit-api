import { onMounted, shallowRef } from 'vue';
import { linksApi, type LinkCounters, type LiveLinkItem } from '../handoff/links-api';
import { staffRequestsApi, type StaffRequestPage } from '../handoff/api';
import type { StaffRole } from '../types';

type RequestCounts = NonNullable<StaffRequestPage['meta']['summary']>['byStatus'];

/** Organizer, curator and head watch the whole scope; a teacher works with the own groups. */
export type LiveOverview =
  | { kind: 'scope'; counters: LinkCounters; waiting: number; queue: LiveLinkItem[] }
  | { kind: 'teacher'; referenceNow: string; groups: LiveLinkItem[]; requests: RequestCounts | null };

const EMPTY: LinkCounters = { referenceNow: '', byState: { preparing: 0, open: 0, closed: 0 }, closingSoon: 0, prepared: 0 };

/**
 * The overview reads only server summaries (U5): one request for the scope, two for a teacher. Nothing is counted
 * from loaded pages.
 */
export function useLiveOverview(role: () => StaffRole | null) {
  const overview = shallowRef<LiveOverview | null>(null);
  const loading = shallowRef(false);
  const error = shallowRef('');

  async function load(): Promise<void> {
    loading.value = true;
    error.value = '';
    try {
      if ('teacher' === role()) {
        const [links, requests] = await Promise.all([linksApi.list(1, { pageSize: 100 }), staffRequestsApi.list(1, 1)]);
        overview.value = {
          kind: 'teacher',
          referenceNow: links.meta.summary?.referenceNow || new Date().toISOString(),
          groups: links.items,
          requests: requests.meta.summary?.byStatus ?? null
        };
      } else {
        // The preparing page is the queue; its summary still covers every state of the scope.
        const page = await linksApi.list(1, { state: 'preparing', pageSize: 5 });
        const counters = page.meta.summary ?? EMPTY;
        overview.value = {
          kind: 'scope',
          counters,
          waiting: Math.max(0, counters.byState.preparing - counters.prepared),
          queue: page.items
        };
      }
    } catch {
      error.value = 'Не удалось загрузить обзор. Проверьте соединение и повторите.';
    } finally {
      loading.value = false;
    }
  }

  onMounted(load);
  return { overview, loading, error, reload: load };
}
