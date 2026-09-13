import type { UiTableColumn, UiTableRow, UiTableSort, UiTableValue } from './table-types.ts';
const collator = new Intl.Collator('ru', { numeric: true, sensitivity: 'base' });
export function tableCellText(value: UiTableValue | undefined, column: UiTableColumn): string {
  if (value === null || value === undefined || value === '') return 'Не указано';
  if (column.type === 'status') return column.statuses?.[String(value)]?.label ?? String(value);
  if (column.type === 'money' && typeof value === 'number')
    return new Intl.NumberFormat('ru-RU', { style: 'currency', currency: 'RUB', maximumFractionDigits: 2 }).format(value / 100);
  if (column.type === 'number' && typeof value === 'number') return new Intl.NumberFormat('ru-RU').format(value);
  if (column.type === 'date' && typeof value === 'string' && Number.isFinite(Date.parse(value)))
    return new Intl.DateTimeFormat('ru-RU', { timeZone: 'UTC' }).format(new Date(value));
  return String(value);
}
export function sortTableRows(rows: UiTableRow[], columns: UiTableColumn[], sort: UiTableSort): UiTableRow[] {
  const column = columns.find((item) => item.key === sort.key && item.sortable);
  if (!column) return [...rows];
  return [...rows].sort((a, b) => {
    const left = a[column.key],
      right = b[column.key];
    const missingLeft = left === null || left === undefined || left === '';
    const missingRight = right === null || right === undefined || right === '';
    if (missingLeft || missingRight) return missingLeft === missingRight ? collator.compare(a.id, b.id) : missingLeft ? 1 : -1;
    const result =
      column.type === 'number' || column.type === 'money'
        ? Number(left) - Number(right)
        : column.type === 'date'
          ? Date.parse(String(left)) - Date.parse(String(right))
          : collator.compare(tableCellText(left, column), tableCellText(right, column));
    return (sort.direction === 'asc' ? result : -result) || collator.compare(a.id, b.id);
  });
}
export function tablePageCount(total: number, size: number): number {
  return Math.max(1, Math.ceil(Math.max(0, total) / Math.max(1, size)));
}
export function clampTablePage(page: number, total: number, size: number): number {
  return Math.min(tablePageCount(total, size), Math.max(1, page));
}
