import type { UiTableColumn, UiTableKind, UiTableRow } from './table-types';
const institutionStatuses = {
  ready: { label: 'Готово к съёмке', tone: 'success' },
  preparing: { label: 'Подготовка', tone: 'info' },
  closed: { label: 'Приём завершён', tone: 'neutral' }
} as const;
const orderStatuses = {
  paid: { label: 'Оплачен', tone: 'success' },
  waiting: { label: 'Ожидает оплаты', tone: 'warning' },
  cancelled: { label: 'Отменён', tone: 'neutral' }
} as const;
export function tableColumns(kind: UiTableKind): UiTableColumn[] {
  return kind === 'institutions'
    ? [
        { key: 'name', label: 'Учреждение', sortable: true, primary: true, mobile: true, width: '26%' },
        { key: 'code', label: 'Код', sortable: true },
        { key: 'date', label: 'Дата съёмки', type: 'date', sortable: true, mobile: true },
        { key: 'count', label: 'Участников', type: 'number', sortable: true, mobile: true },
        { key: 'status', label: 'Статус', type: 'status', sortable: true, mobile: true, statuses: institutionStatuses },
        { key: 'contact', label: 'Ответственный' }
      ]
    : [
        { key: 'name', label: 'Заказ', sortable: true, primary: true, mobile: true },
        { key: 'institution', label: 'Учреждение', sortable: true },
        { key: 'date', label: 'Создан', type: 'date', sortable: true, mobile: true },
        { key: 'amount', label: 'Сумма', type: 'money', sortable: true, mobile: true },
        { key: 'status', label: 'Оплата', type: 'status', sortable: true, mobile: true, statuses: orderStatuses },
        { key: 'contact', label: 'Покупатель' }
      ];
}
export function makeTableRows(kind: UiTableKind): UiTableRow[] {
  return Array.from({ length: 50 }, (_, index): UiTableRow => {
    const n = index + 1,
      code = String(n).padStart(3, '0');
    const institution = (n % 2 ? 'Школа «Горизонт»' : 'Детский сад «Радуга»') + ' № ' + n;
    const date = new Date(Date.UTC(2026, 7, 1 + ((n * 7) % 60))).toISOString().slice(0, 10);
    return kind === 'institutions'
      ? {
          id: 'INS-' + code,
          name: n === 7 ? 'Образовательный центр «Большое путешествие», подготовительная и начальная школа № 7' : institution,
          code: '158-' + code,
          date,
          count: n * 3,
          status: ['ready', 'preparing', 'closed'][index % 3] ?? 'preparing',
          contact: n % 4 ? 'Тестовый сотрудник ' + n : null
        }
      : {
          id: 'ORDER-' + code,
          name: 'MF-DEMO-' + code,
          institution,
          date,
          amount: (80 + ((n * 137) % 50000)) * 100,
          status: ['paid', 'waiting', 'cancelled'][index % 3] ?? 'waiting',
          contact: n % 4 ? 'Тестовый покупатель ' + n : null
        };
  });
}
