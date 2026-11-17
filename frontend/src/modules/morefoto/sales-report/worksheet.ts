export interface ReportSelection {
  view?: string;
  worksheetMode?: string;
  archiveType?: string;
  archiveId?: string;
}

export interface WorksheetVersion {
  type: 'group' | 'institution';
  id: string;
  institutionId: string;
  groupIds: string[];
  number: number;
  kind: 'full' | 'supplement';
  status: 'pending' | 'ready' | 'failed' | 'empty' | 'expired';
  layoutVersion: number;
  createdAt: string;
  expiresAt: string | null;
  files: number;
  prints: number;
}

export interface WorksheetFile {
  filename: string | null;
  path: string | null;
  printsInFile: number | null;
  code: string | null;
  childCode: string | null;
}

export interface WorksheetLine {
  id: string;
  kind: 'physical' | 'digital' | 'bundle';
  columnKey: string;
  productName: string;
  format: string;
  quantity: number;
  printCount: number;
  prints: number;
  unitPrice: number;
  total: number;
  discount: number;
  coveredByGift: boolean;
  selected: boolean;
  files: WorksheetFile[];
}

export interface WorksheetOrder {
  id: string;
  number: string;
  firstName: string | null;
  lastName: string | null;
  childCodes: string[];
  ambiguous: boolean;
  paymentStatus: string;
  latePayment: boolean;
  total: number;
  includedTotal: number;
  discount: number;
  electronicTotal: number;
  giftCodes: string[];
  lines: WorksheetLine[];
}

export interface WorksheetGroup {
  id: string;
  name: string;
  institutionName: string;
  shootName: string;
  orders: WorksheetOrder[];
  deferredOrders: WorksheetOrder[];
  outsideOrders: WorksheetOrder[];
  total: number;
  prints: number;
}

export interface WorksheetColumn {
  key: string;
  name: string;
  format: string;
}

export interface Worksheet {
  mode: 'preview' | 'final';
  archive: WorksheetVersion | null;
  finalAllowed: boolean;
  versions: WorksheetVersion[];
  groups: WorksheetGroup[];
  columns: WorksheetColumn[];
  total: number;
  electronicTotal: number;
  prints: number;
  generatedAt: string;
}

/** Invalid explicit selections reach server validation instead of silently selecting another version. */
export function reportSelection(query: Record<string, unknown>): ReportSelection {
  const selection: ReportSelection = {};
  for (const key of ['view', 'worksheetMode', 'archiveType', 'archiveId'] as const) {
    const value = query[key];
    if (value !== undefined && value !== null) selection[key] = typeof value === 'string' ? value : '';
  }
  return selection;
}

export function worksheetSourceKey(selection: ReportSelection): string {
  return selection.worksheetMode === 'final' ? (selection.archiveType ?? '') + ':' + (selection.archiveId ?? '') : 'preview';
}

export function worksheetSourceSelection(key: string): ReportSelection | null {
  if (key === 'preview') return { view: 'worksheet', worksheetMode: 'preview' };
  const match = /^(group|institution):([a-f0-9-]{36})$/.exec(key);
  return match
    ? {
        view: 'worksheet',
        worksheetMode: 'final',
        archiveType: match[1]!,
        archiveId: match[2]!
      }
    : null;
}

const moments = new Intl.DateTimeFormat('ru-RU', {
  timeZone: 'Europe/Moscow',
  dateStyle: 'short',
  timeStyle: 'short'
});
export const worksheetMoment = (value: string) => moments.format(new Date(value));

const statuses = {
  pending: 'Собирается',
  ready: 'Готов',
  failed: 'Ошибка сборки',
  empty: 'Нет печати',
  expired: 'Срок хранения истёк'
};
export function worksheetSources(report: Worksheet, now = Date.now()) {
  return [
    {
      title: 'Предварительная — текущие покупки',
      value: 'preview',
      props: { disabled: false }
    },
    ...report.versions.map((version) => {
      const expired = version.expiresAt !== null && new Date(version.expiresAt).getTime() <= now;
      const state = expired ? 'expired' : version.status;
      return {
        title:
          (version.type === 'institution' ? 'Учреждение' : 'Группа') +
          ' · № ' +
          version.number +
          ' · ' +
          (version.kind === 'full' ? 'Полный выпуск' : 'Дополнение') +
          ' · ' +
          worksheetMoment(version.createdAt) +
          ' · ' +
          version.id.slice(0, 8) +
          ' · ' +
          statuses[state],
        value: version.type + ':' + version.id,
        props: { disabled: state !== 'ready' || version.layoutVersion !== 2 }
      };
    })
  ];
}

export function worksheetOrderName(order: WorksheetOrder): string {
  return order.firstName !== null && order.lastName !== null ? order.lastName + ' ' + order.firstName : 'Не указано';
}

export function worksheetCells(order: WorksheetOrder, includeAll = false): Record<string, WorksheetLine[]> {
  const cells: Record<string, WorksheetLine[]> = {};
  for (const line of order.lines) {
    if (line.kind === 'physical' && (line.selected || includeAll)) (cells[line.columnKey] ??= []).push(line);
  }
  return cells;
}

export function worksheetColumns(group: WorksheetGroup, columns: WorksheetColumn[], includeAll = false): WorksheetColumn[] {
  const keys = new Set<string>();
  for (const order of group.orders)
    for (const line of order.lines) if (line.kind === 'physical' && (line.selected || includeAll)) keys.add(line.columnKey);
  return columns.filter((column) => keys.has(column.key));
}

export function worksheetError(code: string | null): string | null {
  const messages: Record<string, string> = {
    INVALID_WORKSHEET_SELECTION: 'Выберите предварительную ведомость или конкретную сохранённую версию.',
    WORKSHEET_AREA_MISMATCH: 'Эта версия относится к другой области. Откройте соответствующее учреждение или группу.',
    WORKSHEET_ARCHIVE_NOT_READY: 'Файл выбранной версии недоступен. Проверьте состояние архива или восстановите его в разделе печати.',
    WORKSHEET_LEGACY_LAYOUT: 'У этой старой версии не сохранены исходные имена файлов. Доступны прежняя сводка и CSV архива.',
    WORKSHEET_SOURCE_MISSING: 'Сохранённый состав версии неполон. Ведомость не сформирована.',
    WORKSHEET_SNAPSHOT_MISMATCH: 'Снимки покупок не совпадают с сохранённым заданием. Ведомость не сформирована.',
    WORKSHEET_TOO_LARGE: 'В выбранной области больше 10 000 позиций или 50 000 файлов. Выберите учреждение или группу.',
    VERSION_NOT_FOUND: 'Выбранная версия не найдена или недоступна.',
    ARCHIVE_NOT_FOUND: 'Выбранный архив не найден или недоступен.'
  };
  return code ? (messages[code] ?? null) : null;
}

/** Публичные коды Media различают одноимённые исходники без внутренних ID и серверных путей. */
export function worksheetFileContext(file: WorksheetFile): string | null {
  return file.code ? 'Кадр ' + file.code + (file.childCode ? ' · ребёнок ' + file.childCode : '') : null;
}
