import { plural } from '../../../components/viz/measures.ts';
import type { StatusTone } from '../../../components/status/tones.ts';
import type { ChildSalesStatus, SalesReport, SalesReportFilter, SalesReportLevel, SalesTotals } from './api.ts';

export type ReportRole = 'organizer' | 'curator' | 'head' | 'teacher';

/** E10-DEC-06: what the report says about a child of the group; the text carries the meaning, the tone helps. */
export const CHILD_STATUS: Readonly<Record<ChildSalesStatus, { label: string; tone: StatusTone }>> = {
  paid: { label: 'Оплатил', tone: 'success' },
  late: { label: 'Поздняя оплата', tone: 'warning' },
  unpaid: { label: 'Заказ без оплаты', tone: 'danger' },
  none: { label: 'Нет заказа', tone: 'neutral' }
};

const LEVEL_KIND: Readonly<Record<SalesReportLevel, string>> = {
  all: 'ОТЧЁТ О ПРОДАЖАХ',
  institution: 'УЧРЕЖДЕНИЕ',
  shoot: 'СЪЁМКА',
  group: 'ГРУППА'
};

const ROWS_TITLE: Readonly<Record<Exclude<SalesReportLevel, 'group'>, string>> = {
  all: 'Учреждения',
  institution: 'Съёмки',
  shoot: 'Группы'
};

const number = new Intl.NumberFormat('ru-RU');
const day = new Intl.DateTimeFormat('en-CA', { timeZone: 'Europe/Moscow', year: 'numeric', month: '2-digit', day: '2-digit' });

/** The query keeps only known UUID filters: a hand-edited address cannot send anything else to the server. */
export function reportFilter(query: Record<string, unknown>): SalesReportFilter {
  const filter: SalesReportFilter = {};
  for (const key of ['institutionId', 'shootId', 'groupId'] as const) {
    const value = query[key];
    if (typeof value === 'string' && /^[a-f0-9-]{36}$/.test(value)) filter[key] = value;
  }
  return filter;
}

export function levelKind(level: SalesReportLevel): string {
  return LEVEL_KIND[level];
}

export function reportTitle(report: SalesReport): string {
  if ('group' === report.level) return report.group?.name ?? '';
  if ('shoot' === report.level) return report.shoot?.name ?? '';
  if ('institution' === report.level) return report.institution?.name ?? '';
  return 'Все учреждения';
}

export function rowsTitle(level: SalesReportLevel): string {
  return 'group' === level ? 'Дети группы' : ROWS_TITLE[level];
}

/** The filter that opens a row of the level one step down; the server re-checks every ancestor. */
export function rowFilter(report: SalesReport, rowId: string): SalesReportFilter {
  if ('all' === report.level) return { institutionId: rowId };
  if ('institution' === report.level) return { institutionId: report.institution?.id, shootId: rowId };
  return { institutionId: report.institution?.id, shootId: report.shoot?.id, groupId: rowId };
}

export interface ReportCrumb {
  title: string;
  filter?: SalesReportFilter;
}

/**
 * Breadcrumbs up to the role's own top: «Все учреждения» only for the organizer and the curator, who see several
 * institutions; the head and the teacher start from their institution.
 */
export function reportCrumbs(report: SalesReport, role: ReportRole | null): ReportCrumb[] {
  const crumbs: ReportCrumb[] = [];
  if ('organizer' === role || 'curator' === role) crumbs.push({ title: 'Все учреждения', filter: {} });
  if (report.institution) crumbs.push({ title: report.institution.name, filter: { institutionId: report.institution.id } });
  if (report.shoot) crumbs.push({ title: report.shoot.name, filter: { institutionId: report.institution?.id, shootId: report.shoot.id } });
  if (report.group) crumbs.push({ title: report.group.name });
  return crumbs;
}

/**
 * The head and the teacher entering the section skip levels with a single row: the head lands on the institution,
 * the teacher goes down to their only group. Returns null when the current level is where they stay.
 */
export function descendFilter(report: SalesReport, role: ReportRole | null): SalesReportFilter | null {
  const only = 1 === report.rows.length ? report.rows[0] : undefined;
  if (!only || 'group' === report.level) return null;
  if ('head' === role && 'all' === report.level) return rowFilter(report, only.id);
  if ('teacher' === role) return rowFilter(report, only.id);
  return null;
}

/**
 * Whether the report of a new address keeps descending: entering the section without filters starts the descent for
 * the head and the teacher, and only the address the descent replaced itself continues it.
 */
export function keepsDescending(key: string, descending: boolean, descentKey: string, role: ReportRole | null): boolean {
  return '{}' === key ? 'head' === role || 'teacher' === role : descending && key === descentKey;
}

export interface ReportFigure {
  key: string;
  value: string;
  label: string;
}

/** The summary tiles in the order of the PDF: money first, then orders, families, prints and files. */
export function reportFigures(totals: SalesTotals): ReportFigure[] {
  return [
    { key: 'total', value: money(totals.paidTotal), label: 'оплачено родителями' },
    {
      key: 'orders',
      value: number.format(totals.paidOrders),
      label: plural(totals.paidOrders, ['оплаченный заказ', 'оплаченных заказа', 'оплаченных заказов'])
    },
    {
      key: 'families',
      value: number.format(totals.orderedFamilies) + ' / ' + number.format(totals.paidFamilies),
      label: 'заказали / оплатили, из ' + number.format(totals.children) + ' ' + plural(totals.children, ['ребёнка', 'детей', 'детей'])
    },
    {
      key: 'prints',
      value: number.format(totals.prints),
      label: plural(totals.prints, ['печатное фото', 'печатных фото', 'печатных фото'])
    },
    {
      key: 'files',
      value: number.format(totals.digitalFiles),
      label:
        plural(totals.digitalFiles, ['цифровой файл', 'цифровых файла', 'цифровых файлов']) +
        (totals.bundles > 0
          ? ` и ${number.format(totals.bundles)} ${plural(totals.bundles, ['комплект', 'комплекта', 'комплектов'])} «все фото»`
          : '')
    }
  ];
}

/** «1 из 2» when a child has orders that are not paid yet. */
export function childOrdersText(paidOrders: number, orders: number): string {
  return orders > paidOrders ? `${number.format(paidOrders)} из ${number.format(orders)}` : number.format(paidOrders);
}

/** Roubles without kopecks when there are none, otherwise both digits — the same as the PDF: «1 234,50 ₽». */
export function money(kopecks: number): string {
  const digits = 0 === kopecks % 100 ? 0 : 2;
  return new Intl.NumberFormat('ru-RU', {
    style: 'currency',
    currency: 'RUB',
    maximumFractionDigits: digits,
    minimumFractionDigits: digits
  }).format(kopecks / 100);
}

/** The same name the server gives: «morefoto-otchet-group-2026-10-24.pdf», the date in Moscow time. */
export function pdfFileName(level: SalesReportLevel, generatedAt: string): string {
  return `morefoto-otchet-${level}-${day.format(new Date(generatedAt))}.pdf`;
}

export const reportLoadError = 'Не удалось загрузить отчёт. Проверьте соединение и повторите.';
export const pdfError = 'Не удалось сформировать PDF. Повторите через минуту.';
