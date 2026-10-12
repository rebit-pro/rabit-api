import type { UiTableSort } from './table-types';

/** Page sizes offered by `UiDataTable`; a size outside them in the URL falls back to the default. */
const PAGE_SIZES = [10, 25, 50];

/** Sort and page of a server-paged table, kept in the URL so a reload or a back link returns to the same view. */
export interface TableView {
  sort: UiTableSort;
  page: number;
  pageSize: number;
}

/** Reads the view from the route query; an unknown sort field or size gives the table's default. */
export function tableViewFromQuery(query: Record<string, unknown>, keys: readonly string[], fallback: UiTableSort): TableView {
  const text = (key: string) => (typeof query[key] === 'string' ? (query[key] as string) : '');
  const page = Number.parseInt(text('page'), 10);
  const size = Number.parseInt(text('pageSize'), 10);
  return {
    sort: keys.includes(text('sort')) ? { key: text('sort'), direction: text('direction') === 'asc' ? 'asc' : 'desc' } : fallback,
    page: Number.isInteger(page) && page > 0 ? page : 1,
    pageSize: PAGE_SIZES.includes(size) ? size : 25
  };
}

/** Query part of the view; defaults are left out to keep links short. */
export function tableViewQuery(view: TableView, fallback: UiTableSort): Record<string, string> {
  const query: Record<string, string> = {};
  if (view.sort.key !== fallback.key || view.sort.direction !== fallback.direction) {
    query.sort = view.sort.key;
    query.direction = view.sort.direction;
  }
  if (view.page > 1) query.page = String(view.page);
  if (view.pageSize !== 25) query.pageSize = String(view.pageSize);
  return query;
}

/** API parameters of the view: the server orders the whole filter, not the page (#92 DEC-05). */
export function tableViewParams(view: TableView): Record<string, string | number> {
  return { page: view.page, pageSize: view.pageSize, sort: view.sort.key, direction: view.sort.direction };
}
