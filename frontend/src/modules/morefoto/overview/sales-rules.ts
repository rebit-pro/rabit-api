import { plural } from '../../../components/viz/measures.ts';
import { money } from '../commerce/money.ts';
import type { GroupSales } from './sales-api';

/** The four counters of the «Продажи» block in the order a teacher reads them. */
export function salesFigures(sales: GroupSales): { key: string; label: string; value: number }[] {
  return [
    { key: 'orders', label: 'Оплаченных заказов', value: sales.paidOrders },
    { key: 'families', label: 'Купивших семей', value: sales.families },
    { key: 'prints', label: 'Печатных фото', value: sales.prints },
    { key: 'files', label: 'Цифровых файлов', value: sales.digitalFiles }
  ];
}

/** E9-DEC-04: a bought «all photos of the child» set has no frame count in the order, so it is named apart. */
export function bundleNote(bundles: number): string {
  return bundles > 0 ? `и ${bundles} ${plural(bundles, ['комплект', 'комплекта', 'комплектов'])} «все фото ребёнка»` : '';
}

export function paidTotalText(sales: GroupSales): string {
  return money(sales.paidTotal);
}

export function printsText(prints: number): string {
  return `${new Intl.NumberFormat('ru-RU').format(prints)} шт.`;
}

export const salesLoadError = 'Не удалось загрузить продажи. Проверьте соединение и повторите.';
