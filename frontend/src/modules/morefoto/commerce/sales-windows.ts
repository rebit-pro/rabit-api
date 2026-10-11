import type { ProductKind } from './types.js';

/** E7: a group sells print and digital until print closes (T0+7), then digital only until T0+14. */
export type SalesState = 'preparing' | 'open' | 'digital' | 'closed';

export interface SalesLine {
  assignmentId: string;
  productId: string;
  quantity: number;
}

export function sellsKind(state: SalesState, kind: ProductKind): boolean {
  return state === 'open' || (state === 'digital' && kind !== 'physical');
}

export function sellsAnything(state: SalesState): boolean {
  return state === 'open' || state === 'digital';
}

/** Lines a buyer can still order; print lines of a stored cart drop out once print sales close. */
export function sellableLines<T extends SalesLine>(
  lines: T[],
  kinds: ReadonlyMap<string, ProductKind>,
  state: SalesState
): { kept: T[]; dropped: number } {
  const kept = lines.filter((line) => {
    const kind = kinds.get(line.productId);
    // An unknown product is left to the server check, which reports it as an invalid cart.
    return kind === undefined || sellsKind(state, kind);
  });

  return { kept, dropped: lines.length - kept.length };
}

/** Adds lines to a cart without duplicates: the same frame and product keeps the cart's own line. */
export function mergeCartLines(current: SalesLine[], added: SalesLine[]): SalesLine[] {
  const present = new Set(current.map((line) => line.assignmentId + ':' + line.productId));
  const merged = [...current];
  for (const line of added) {
    const id = line.assignmentId + ':' + line.productId;
    if (!present.has(id)) {
      present.add(id);
      merged.push({ assignmentId: line.assignmentId, productId: line.productId, quantity: line.quantity });
    }
  }

  return merged;
}

const quoteErrors: Record<string, string> = {
  PRINT_SALES_CLOSED: 'Приём заказов на печать завершён. Обновите страницу: печатные позиции уберутся, электронные фото останутся.',
  GALLERY_CLOSED: 'Приём заказов завершён. Выбор сохранён; новые заказы сейчас не принимаются.'
};

/** Text for a refused cart calculation that the buyer can act on; null leaves the generic message. */
export function quoteErrorMessage(code: string): string | null {
  return quoteErrors[code] ?? null;
}

export function salesStateTitle(state: SalesState): string {
  return {
    preparing: 'Готовим фотографии',
    open: 'Приём открыт',
    digital: 'Печать закрыта',
    closed: 'Приём завершён'
  }[state];
}

/** Whole days left before a deadline from the server's reference moment; null once it has passed. */
export function daysLeft(until: string | null, now: string): string | null {
  if (!until) return null;
  const left = Date.parse(until) - Date.parse(now);
  if (left <= 0) return null;
  const days = Math.floor(left / 86400000);
  if (days === 0) return 'меньше суток';
  const form = new Intl.PluralRules('ru').select(days);
  return days + ' ' + ({ one: 'день', few: 'дня', many: 'дней' }[form as 'one' | 'few' | 'many'] ?? 'дня');
}
