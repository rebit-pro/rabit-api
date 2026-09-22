import type { CartQuote } from '../../commerce/types.js';
import type { BuyerErrors } from '../types.js';
import type { ApiProblem, LiveOrderQuote, StaffOrderFilters } from './types.js';

export type CheckoutOutcome =
  | { kind: 'unknown' }
  | { kind: 'field'; errors: BuyerErrors }
  | { kind: 'recalculate'; message: string }
  | { kind: 'message'; message: string };

const fieldCodes: Record<string, [keyof BuyerErrors, string]> = {
  INVALID_BUYER_NAME: ['name', 'Укажите имя: от 2 до 100 символов.'],
  INVALID_BUYER_PHONE: ['phone', 'Укажите телефон: от 10 до 15 цифр, например +7 900 123-45-67.'],
  INVALID_BUYER_EMAIL: ['email', 'Укажите email в формате name@example.ru.'],
  INVALID_BUYER_COMMENT: ['comment', 'Комментарий — не более 1000 символов.'],
  REVIEW_REQUIRED: ['reviewed', 'Подтвердите, что проверили состав заказа.'],
  RECEIPT_CHANNEL_UNAVAILABLE: ['receiptChannel', 'Этот канал чека сейчас не подключён.']
};

/** Classifies a failed order submission; only an unanswered request may be repeated with the same key. */
export function checkoutOutcome(problem: ApiProblem): CheckoutOutcome {
  if (problem.network) return { kind: 'unknown' };
  const field = fieldCodes[problem.code];
  if (field) return { kind: 'field', errors: { [field[0]]: field[1] } };
  switch (problem.code) {
    case 'PRICE_CHANGED':
      return { kind: 'recalculate', message: 'Цена изменилась. Проверьте новый итог и подтвердите заказ ещё раз.' };
    case 'QUOTE_STALE':
    case 'QUOTE_EXPIRED':
      return { kind: 'recalculate', message: 'Расчёт устарел. Мы обновили итог — проверьте его и подтвердите заказ ещё раз.' };
    case 'QUOTE_ALREADY_USED':
      return {
        kind: 'recalculate',
        message: 'По этому расчёту заказ уже оформлен в другой вкладке. Проверьте корзину и подтвердите новый заказ.'
      };
    case 'GALLERY_CLOSED':
      return { kind: 'message', message: 'Приём заказов закрыт. Новый заказ оформить уже нельзя.' };
    case 'GALLERY_NOT_READY':
    case 'GALLERY_NOT_FOUND':
      return { kind: 'message', message: 'Ссылка на группу недействительна или ещё не открыта.' };
    case 'PURCHASE_DISABLED':
      return { kind: 'message', message: 'Оформление заказов пока недоступно.' };
    case 'INVALID_CART':
    case 'DUPLICATE_CART_LINE':
    case 'DIGITAL_ALREADY_IN_BUNDLE':
    case 'STAFF_ELIGIBILITY_REQUIRED':
      return { kind: 'message', message: 'Состав корзины больше недоступен. Вернитесь в корзину и уточните выбор.' };
    default:
      return { kind: 'message', message: 'Сервер не создал заказ. Заполнение сохранено — попробуйте ещё раз.' };
  }
}

/** Order lines have no image URL by contract; the composition shows frame codes unless a trusted source is given. */
export function orderQuoteAsCart(quote: LiveOrderQuote, thumb: (photoId: string) => string = () => ''): CartQuote {
  return {
    ...quote,
    lines: quote.lines.map((line) => ({
      ...line,
      photo: line.photo === null ? null : { ...line.photo, thumbSrc: thumb(line.photo.id), previewSrc: '' }
    }))
  };
}

export function staffOrderParams(filters: StaffOrderFilters): Record<string, string | number> {
  const params: Record<string, string | number> = { page: filters.page, pageSize: filters.pageSize };
  for (const key of ['q', 'paymentStatus', 'productionStatus', 'dateFrom', 'dateTo'] as const) {
    const value = filters[key].trim();
    if (value !== '') params[key] = value;
  }
  return params;
}

export function staffFiltersFromQuery(query: Record<string, unknown>): StaffOrderFilters {
  const text = (key: string) => (typeof query[key] === 'string' ? (query[key] as string) : '');
  const page = Number.parseInt(text('page'), 10);
  return {
    q: text('q'),
    paymentStatus: text('paymentStatus'),
    productionStatus: text('productionStatus'),
    dateFrom: text('dateFrom'),
    dateTo: text('dateTo'),
    page: Number.isInteger(page) && page > 0 ? page : 1,
    pageSize: 25
  };
}

export function staffOrderError(problem: ApiProblem): string {
  if (problem.network) return 'Не удалось связаться с сервером. Проверьте соединение и повторите.';
  if (problem.code === 'FORBIDDEN' || problem.status === 403) return 'Заказы доступны организатору и куратору своей области.';
  if (problem.code === 'ORDER_NOT_FOUND' || problem.status === 404) return 'Заказ не найден в вашей области.';
  if (problem.code === 'ACCESS_CHANGED') return 'Ваши права изменились во время загрузки. Обновите страницу.';
  if (problem.code === 'INVALID_FILTER' || problem.code === 'INVALID_PAGE') return 'Проверьте значения фильтров.';
  return 'Сервер не вернул заказы. Повторите попытку.';
}

export function newRequestId(random: () => string = () => crypto.randomUUID()): string {
  return random().replace(/-/g, '');
}
