import { computed, shallowRef, type ComputedRef } from 'vue';
import type { UiTableColumn, UiTableRow, UiTableSort } from '../ui/table-types';
import { clampTablePage, sortTableRows } from '../ui/table-values';

/** A report level is read whole from the server; sorting and pages stay in the browser. */
export function useLocalTable(rows: () => UiTableRow[], columns: UiTableColumn[], initial: UiTableSort) {
  const sort = shallowRef<UiTableSort>(initial);
  const page = shallowRef(1);
  const pageSize = shallowRef(50);
  const sorted = computed(() => sortTableRows(rows(), columns, sort.value));
  const visible: ComputedRef<UiTableRow[]> = computed(() => {
    const current = clampTablePage(page.value, sorted.value.length, pageSize.value);
    return sorted.value.slice((current - 1) * pageSize.value, current * pageSize.value);
  });
  return {
    sort,
    page,
    pageSize,
    visible,
    total: computed(() => sorted.value.length),
    order(next: UiTableSort) {
      sort.value = next;
      page.value = 1;
    },
    resize(size: number) {
      pageSize.value = size;
      page.value = 1;
    }
  };
}
