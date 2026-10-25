import { computed, onScopeDispose, shallowRef, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { reportsApi, type SalesReport, type SalesReportFilter } from './api';
import {
  descendFilter,
  keepsDescending,
  pdfError,
  pdfFileName,
  reportCrumbs,
  reportFilter,
  reportLoadError,
  rowFilter,
  type ReportRole
} from './rules';

const PATH = '/cabinet/reports';

/** The query of a report address; empty filters are dropped so «/cabinet/reports» stays the entry point. */
export function reportQuery(filter: SalesReportFilter): Record<string, string> {
  return Object.fromEntries(Object.entries(filter).filter((entry): entry is [string, string] => typeof entry[1] === 'string'));
}

export function reportHref(filter: SalesReportFilter): string {
  const query = new URLSearchParams(reportQuery(filter)).toString();
  return query ? PATH + '?' + query : PATH;
}

/**
 * E10: the report of the level in the address. Entering the section without filters lets the head and the teacher
 * skip single-row levels; every later step is an explicit address, so «Back» and breadcrumbs work as links.
 */
export function useSalesReport() {
  const route = useRoute();
  const router = useRouter();
  const auth = useAuthStore();
  const role = computed(() => (auth.user?.role ?? null) as ReportRole | null);
  const report = shallowRef<SalesReport | null>(null);
  const loading = shallowRef(false);
  const error = shallowRef('');
  const downloading = shallowRef(false);
  const downloadError = shallowRef('');
  const filter = computed(() => reportFilter(route.query));
  const crumbs = computed(() =>
    report.value
      ? reportCrumbs(report.value, role.value).map((crumb) => ({
          title: crumb.title,
          to: crumb.filter ? reportHref(crumb.filter) : undefined
        }))
      : []
  );
  // The address the descent replaced last: only this step continues it, any other address is the user's choice.
  let descending = false;
  let descentKey = '';
  let request = 0;
  // A report that answers after the page is gone must neither render nor send the user back to the section.
  onScopeDispose(() => {
    request++;
  });

  async function load(): Promise<void> {
    const current = ++request;
    loading.value = true;
    error.value = '';
    try {
      const next = await reportsApi.report(filter.value);
      if (current !== request) return;
      const down = descending ? descendFilter(next, role.value) : null;
      if (down) {
        descentKey = JSON.stringify(reportFilter(reportQuery(down)));
        await router.replace({ path: PATH, query: reportQuery(down) });
        return;
      }
      descending = false;
      report.value = next;
    } catch {
      if (current === request) {
        descending = false;
        report.value = null;
        error.value = reportLoadError;
      }
    } finally {
      if (current === request) loading.value = false;
    }
  }

  function rowHref(rowId: string): string {
    return report.value ? reportHref(rowFilter(report.value, rowId)) : PATH;
  }

  async function download(): Promise<void> {
    if (!report.value || downloading.value) return;
    downloading.value = true;
    downloadError.value = '';
    try {
      const url = URL.createObjectURL(await reportsApi.pdf(filter.value));
      const anchor = document.createElement('a');
      anchor.href = url;
      anchor.download = pdfFileName(report.value.level, new Date().toISOString());
      anchor.click();
      setTimeout(() => URL.revokeObjectURL(url), 1000);
    } catch {
      downloadError.value = pdfError;
    } finally {
      downloading.value = false;
    }
  }

  watch(
    () => JSON.stringify(filter.value),
    (key) => {
      descending = keepsDescending(key, descending, descentKey, role.value);
      void load();
    },
    { immediate: true }
  );

  return { report, loading, error, crumbs, downloading, downloadError, load, rowHref, download };
}
