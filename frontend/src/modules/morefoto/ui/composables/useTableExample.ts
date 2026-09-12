import { computed, onScopeDispose, ref, watch } from 'vue';
import type { UiTableKind, UiTableSort } from '../table-types';
import { makeTableRows, tableColumns } from '../table-fixtures';
import { clampTablePage, sortTableRows } from '../table-values';
export function useTableExample(kind: UiTableKind) {
  const columns = tableColumns(kind);
  const allRows = ref(makeTableRows(kind));
  const search = ref('');
  const status = ref<string | null>(null);
  const from = ref('');
  const to = ref('');
  const selected = ref<string[]>([]);
  const page = ref(1);
  const pageSize = ref(10);
  const sort = ref<UiTableSort>({ key: 'name', direction: 'asc' });
  const mode = ref<'ready' | 'loading' | 'error'>('ready');
  const notice = ref('');
  const removeIds = ref<string[]>([]);
  const openedId = ref<string | null>(null);
  let timer: ReturnType<typeof setTimeout> | undefined;
  function stopTimer() {
    if (timer) clearTimeout(timer);
    timer = undefined;
  }
  onScopeDispose(stopTimer);
  const statusItems = Object.entries(columns.find((column) => column.type === 'status')?.statuses ?? {}).map(([value, item]) => ({
    value,
    title: item.label
  }));
  const dateError = computed(() =>
    from.value && to.value && from.value > to.value ? 'Дата начала должна быть не позже даты окончания.' : ''
  );
  const hasFilters = computed(() => !!(search.value.trim() || status.value || from.value || to.value));
  const filtered = computed(() =>
    dateError.value
      ? []
      : allRows.value.filter((row) => {
          const query = search.value.trim().toLocaleLowerCase('ru');
          return (
            (!query ||
              Object.values(row).some((value) =>
                String(value ?? '')
                  .toLocaleLowerCase('ru')
                  .includes(query)
              )) &&
            (!status.value || row.status === status.value) &&
            (!from.value || String(row.date) >= from.value) &&
            (!to.value || String(row.date) <= to.value)
          );
        })
  );
  const sorted = computed(() => sortTableRows(filtered.value, columns, sort.value));
  const rows = computed(() => sorted.value.slice((page.value - 1) * pageSize.value, page.value * pageSize.value));
  const opened = computed(() => allRows.value.find((row) => row.id === openedId.value));
  watch(
    [search, status, from, to],
    () => {
      page.value = 1;
      if (selected.value.length) {
        selected.value = [];
        notice.value = 'Выбор строк сброшен: изменились условия поиска.';
      }
    },
    { flush: 'sync' }
  );
  watch(
    [() => filtered.value.length, pageSize],
    () => {
      page.value = clampTablePage(page.value, filtered.value.length, pageSize.value);
    },
    { flush: 'sync' }
  );
  function clearFilters() {
    search.value = '';
    status.value = null;
    from.value = '';
    to.value = '';
    page.value = 1;
  }
  function setSort(next: UiTableSort) {
    sort.value = next;
    page.value = 1;
  }
  function setPageSize(next: number) {
    pageSize.value = next;
    page.value = 1;
  }
  function load() {
    stopTimer();
    mode.value = 'loading';
    timer = setTimeout(() => {
      mode.value = 'ready';
      timer = undefined;
    }, 650);
  }
  function fail() {
    stopTimer();
    mode.value = 'error';
  }
  function empty() {
    stopTimer();
    mode.value = 'ready';
    allRows.value = [];
    selected.value = [];
    notice.value = '';
  }
  function reset() {
    stopTimer();
    mode.value = 'ready';
    allRows.value = makeTableRows(kind);
    selected.value = [];
    clearFilters();
    sort.value = { key: 'name', direction: 'asc' };
    pageSize.value = 10;
    notice.value = '';
    removeIds.value = [];
    openedId.value = null;
  }
  function requestRemove(ids: string[]) {
    removeIds.value = ids.filter((id) => allRows.value.some((row) => row.id === id));
  }
  function remove() {
    const ids = removeIds.value;
    if (!ids.length) return;
    allRows.value = allRows.value.filter((row) => !ids.includes(row.id));
    selected.value = selected.value.filter((id) => !ids.includes(id));
    notice.value = 'Удалено записей в образце: ' + ids.length + '. Покупательские заказы не изменены.';
    removeIds.value = [];
  }
  return {
    columns,
    allRows,
    rows,
    filtered,
    search,
    status,
    statusItems,
    from,
    to,
    dateError,
    hasFilters,
    selected,
    page,
    pageSize,
    sort,
    mode,
    notice,
    removeIds,
    openedId,
    opened,
    clearFilters,
    setSort,
    setPageSize,
    load,
    fail,
    empty,
    reset,
    requestRemove,
    remove
  };
}
