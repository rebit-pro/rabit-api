import type { ApiProblem } from './types';
import type { AttemptStatus, PaymentAttempt, PaymentFilters, PaymentMethod, StaffPaymentFact } from './payment-types';

export const paymentMethodLabels: Record<PaymentMethod, string> = { sbp: 'СБП', bank_card: 'Банковская карта' };
export const attemptStatusLabels: Record<AttemptStatus, string> = {
  unknown: 'Проверяется',
  pending: 'Ожидает оплаты',
  succeeded: 'Оплачено',
  canceled: 'Не оплачено'
};
export const confirmationLabels: Record<StaffPaymentFact['confirmedBy'], string> = {
  start: 'при создании',
  return: 'после возврата покупателя',
  notification: 'по уведомлению ЮKassa',
  reconcile: 'фоновой сверкой'
};

/** A final status ends the wait on the return page; unknown and pending keep being checked by the server. */
export function isFinal(attempt: Pick<PaymentAttempt, 'status'>): boolean {
  return attempt.status === 'succeeded' || attempt.status === 'canceled';
}

const STORAGE_PREFIX = 'morefoto:payment:';

/**
 * The personal order key is never sent to the provider (G1-DEC-04). The return page finds it by the attempt ID on
 * this device; a bank app may reopen the page in a new tab, so the key stays until the final status.
 */
export function rememberOrderKey(storage: Pick<Storage, 'setItem'>, attemptId: string, orderKey: string): void {
  try {
    storage.setItem(STORAGE_PREFIX + attemptId, orderKey);
  } catch {
    // Private mode: the buyer returns through the personal link instead.
  }
}
export function recallOrderKey(storage: Pick<Storage, 'getItem'>, attemptId: string): string | null {
  try {
    const key = storage.getItem(STORAGE_PREFIX + attemptId);
    return key && /^[a-f0-9]{64}$/.test(key) ? key : null;
  } catch {
    return null;
  }
}
export function forgetOrderKey(storage: Pick<Storage, 'removeItem'>, attemptId: string): void {
  try {
    storage.removeItem(STORAGE_PREFIX + attemptId);
  } catch {
    // Nothing to clean up.
  }
}

/** Polls the server result: every 3 s during the first minute, then every 10 s; stops after 5 minutes. */
export function nextPollDelay(elapsedMs: number): number | null {
  if (elapsedMs >= 300000) return null;
  return elapsedMs < 60000 ? 3000 : 10000;
}

export function paymentError(problem: ApiProblem): string {
  if (problem.network) return 'Не удалось связаться с сервером. Проверьте соединение и повторите.';
  switch (problem.code) {
    case 'ORDER_NOT_FOUND':
      return 'Ссылка на заказ недействительна или её срок истёк.';
    case 'PAYMENT_ATTEMPT_NOT_FOUND':
      return 'Эта оплата не найдена для вашего заказа.';
    case 'PAYMENT_DISABLED':
      return 'Оплата сейчас недоступна. Заказ сохранён — вернитесь к нему позже по личной ссылке.';
    case 'PAYMENT_METHOD_UNAVAILABLE':
      return 'Этот способ оплаты сейчас недоступен. Выберите другой.';
    case 'PAYMENT_CLOSED':
      return 'Приём заказов группы завершён, оплатить заказ уже нельзя.';
    case 'ORDER_ALREADY_PAID':
      return 'Заказ уже оплачен.';
    case 'QUOTE_CHANGED':
    case 'ATTEMPT_CONFLICT':
      return 'Состояние оплаты изменилось. Мы обновили данные — проверьте сумму и повторите.';
    default:
      return 'Не удалось начать оплату. Повторите попытку.';
  }
}

export function staffPaymentError(problem: ApiProblem): string {
  if (problem.network) return 'Не удалось связаться с сервером. Проверьте соединение и повторите.';
  if (problem.code === 'FORBIDDEN' || problem.status === 403) return 'Платежи доступны организатору и куратору своей области.';
  if (problem.code === 'PAYMENT_ATTEMPT_NOT_FOUND' || problem.status === 404) return 'Платёж не найден в вашей области.';
  if (problem.code === 'ACCESS_CHANGED') return 'Ваши права изменились во время загрузки. Обновите страницу.';
  if (problem.code === 'INVALID_FILTER' || problem.code === 'INVALID_PAGE') return 'Проверьте значения фильтров.';
  return 'Сервер не вернул платежи. Повторите попытку.';
}

export function paymentParams(filters: PaymentFilters): Record<string, string | number> {
  const params: Record<string, string | number> = { page: filters.page, pageSize: filters.pageSize };
  for (const key of ['status', 'orderNumber', 'dateFrom', 'dateTo', 'late'] as const) {
    const value = filters[key].trim();
    if (value !== '') params[key] = value;
  }
  return params;
}

export function paymentFiltersFromQuery(query: Record<string, unknown>): PaymentFilters {
  const text = (key: string) => (typeof query[key] === 'string' ? (query[key] as string) : '');
  const page = Number.parseInt(text('page'), 10);
  const late = text('late');
  return {
    status: text('status') in attemptStatusLabels ? text('status') : '',
    orderNumber: text('orderNumber'),
    dateFrom: text('dateFrom'),
    dateTo: text('dateTo'),
    late: late === 'true' || late === 'false' ? late : '',
    page: Number.isInteger(page) && page > 0 ? page : 1,
    pageSize: 25
  };
}
