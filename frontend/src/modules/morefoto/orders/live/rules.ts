import type { CartQuote } from '../../commerce/types.js';
import type { Group, Institution, Shoot } from '../../structure/model.js';
import type { BuyerErrors } from '../types.js';
import type { ApiProblem, CreatedOrder, LiveOrderQuote, StaffOrderFilters } from './types.js';

export type CheckoutOutcome =
  | { kind: 'unknown'; message: string }
  | { kind: 'field'; errors: BuyerErrors }
  | { kind: 'recalculate'; message: string }
  | { kind: 'message'; message: string };

const fieldCodes: Record<string, [keyof BuyerErrors, string]> = {
  INVALID_BUYER_NAME: ['name', 'Укажите имя: от 2 до 100 символов.'],
  INVALID_BUYER_PHONE: ['phone', 'Укажите телефон: от 10 до 15 цифр, например +7 900 123-45-67.'],
  INVALID_BUYER_EMAIL: ['email', 'Укажите email в формате name@example.ru.'],
  INVALID_BUYER_COMMENT: ['comment', 'Комментарий — не более 1000 символов.'],
  REVIEW_REQUIRED: ['reviewed', 'Подтвердите, что проверили состав заказа.'],
  RECEIPT_CHANNEL_UNAVAILABLE: ['receiptChannel', 'Этот канал чека сейчас не подключён.'],
  // The server rolls the order back, so the key stays unused; the documents are reloaded for a fresh acceptance.
  CONSENT_REQUIRED: ['consent', 'Документы обновились. Откройте их и подтвердите согласие и оферту ещё раз.']
};

/**
 * Codes the server answers only after it found no order under the Idempotency-Key. INVALID_CART may also come from
 * the format check, which rejects the same body on every attempt alike.
 */
const unusedKeyCodes = new Set([
  ...Object.keys(fieldCodes),
  'GALLERY_CLOSED',
  'PRICE_CHANGED',
  'QUOTE_STALE',
  'QUOTE_EXPIRED',
  'QUOTE_ALREADY_USED',
  'INVALID_CART',
  'DUPLICATE_CART_LINE',
  'DIGITAL_ALREADY_IN_BUNDLE',
  'STAFF_ELIGIBILITY_REQUIRED'
]);

/**
 * Classifies a failed order submission. No answer, 5xx or an unexpected failure may hide a committed order, so the
 * same body is repeated with the same key. A 4xx to a first attempt proves its fresh key unused; while recovering,
 * only a code answered after the key lookup does, and PURCHASE_DISABLED, 408/429 or an unknown 4xx keep the attempt.
 */
export function checkoutOutcome(problem: ApiProblem, recovering: boolean): CheckoutOutcome {
  if (problem.network || problem.status === null || problem.status >= 500) {
    return {
      kind: 'unknown',
      message:
        'Результат отправки не подтверждён. Нажмите «Повторить отправку»: если заказ уже создан, откроется он же, второй заказ не появится.'
    };
  }
  if (recovering && !unusedKeyCodes.has(problem.code)) {
    const reason = problem.code === 'PURCHASE_DISABLED' ? 'Оформление заказов сейчас недоступно.' : 'Сервер пока не принял повтор.';
    return { kind: 'unknown', message: reason + ' Прошлая отправка сохранена — повторите её позже.' };
  }
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
      return { kind: 'message', message: 'Сервер отклонил заказ. Заполнение сохранено — проверьте данные и попробуйте ещё раз.' };
  }
}

/** What the checkout is sending now: nothing, a fresh attempt from the form or the stored unconfirmed attempt. */
export type CheckoutSubmission = 'idle' | 'first' | 'recovery';

/**
 * An unconfirmed attempt is recovered on its own screen, also while it is being repeated: the form or an empty cart
 * must not flash in between. A first submission stores its attempt before the request and still stays on the form.
 */
export function showsCheckoutRecovery(pending: boolean, submission: CheckoutSubmission): boolean {
  return pending && submission !== 'first';
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

/** Text filters of the staff order list, kept in the URL so that a card link and the way back preserve them. */
export const staffFilterKeys = [
  'q',
  'institutionId',
  'shootId',
  'groupId',
  'paymentStatus',
  'productionStatus',
  'dateFrom',
  'dateTo'
] as const;
export type StaffScopeLevel = 'institutionId' | 'shootId' | 'groupId';
export type StaffScope = Pick<StaffOrderFilters, StaffScopeLevel>;
export interface StaffScopeOption {
  title: string;
  value: string;
}
export interface StaffScopeSource {
  institutions: Pick<Institution, 'id' | 'name'>[];
  shoots: Pick<Shoot, 'id' | 'name'>[];
  groups: Pick<Group, 'id' | 'name' | 'shootId'>[];
}

export function staffOrderParams(filters: StaffOrderFilters): Record<string, string | number> {
  const params: Record<string, string | number> = { page: filters.page, pageSize: filters.pageSize };
  for (const key of staffFilterKeys) {
    const value = filters[key].trim();
    if (value !== '') params[key] = value;
  }
  return params;
}

export function staffFilterQuery(filters: StaffOrderFilters, page = 1): Record<string, string> {
  const query: Record<string, string> = {};
  for (const key of staffFilterKeys) {
    const value = filters[key].trim();
    if (value !== '') query[key] = value;
  }
  if (page > 1) query.page = String(page);
  return query;
}

export function hasStaffFilters(filters: StaffOrderFilters): boolean {
  return staffFilterKeys.some((key) => filters[key] !== '');
}

export function staffFiltersFromQuery(query: Record<string, unknown>): StaffOrderFilters {
  const text = (key: string) => (typeof query[key] === 'string' ? (query[key] as string) : '');
  const page = Number.parseInt(text('page'), 10);
  return {
    q: text('q'),
    institutionId: text('institutionId'),
    shootId: text('shootId'),
    groupId: text('groupId'),
    paymentStatus: text('paymentStatus'),
    productionStatus: text('productionStatus'),
    dateFrom: text('dateFrom'),
    dateTo: text('dateTo'),
    page: Number.isInteger(page) && page > 0 ? page : 1,
    pageSize: 25
  };
}

/** A new parent drops the levels below it: a shoot or group of another institution would narrow the list to nothing. */
export function staffScopePatch(level: StaffScopeLevel, value: string): Partial<StaffScope> {
  if (level === 'institutionId') return { institutionId: value, shootId: '', groupId: '' };
  if (level === 'shootId') return { shootId: value, groupId: '' };
  return { groupId: value };
}

/**
 * Options of the three scope lists. Groups follow the chosen shoot; without one a group name carries its shoot, since
 * shoots of one institution often repeat group names. A value from a link that is not among the loaded options stays
 * selectable under a neutral title: the server still decides whether it is in the staff member's area.
 */
export function staffScopeOptions(source: StaffScopeSource, scope: StaffScope): Record<StaffScopeLevel, StaffScopeOption[]> {
  const shootNames = new Map(source.shoots.map((shoot) => [shoot.id, shoot.name]));
  const groups = scope.shootId === '' ? source.groups : source.groups.filter((group) => group.shootId === scope.shootId);
  return {
    institutionId: scopeList(
      'Все учреждения',
      source.institutions.map((institution) => ({ title: institution.name, value: institution.id })),
      scope.institutionId
    ),
    shootId: scopeList(
      'Все съёмки',
      source.shoots.map((shoot) => ({ title: shoot.name, value: shoot.id })),
      scope.shootId
    ),
    groupId: scopeList(
      'Все группы',
      groups.map((group) => {
        const shoot = scope.shootId === '' ? shootNames.get(group.shootId) : undefined;
        return { title: shoot === undefined ? group.name : group.name + ' · ' + shoot, value: group.id };
      }),
      scope.groupId
    )
  };
}

/** Loading state of one source of the scope options: the institution list or the card of one institution. */
export type StaffScopeLoad = 'idle' | 'loading' | 'ready' | 'failed';

/**
 * Sources an explicit retry loads again. Only a failed source is repeated: one still loading is never started twice,
 * so repeated clicks send no parallel requests, and a loaded one keeps its options.
 */
export function staffScopeRetry(list: StaffScopeLoad, card: StaffScopeLoad): { list: boolean; card: boolean } {
  return { list: list === 'failed', card: card === 'failed' };
}

function scopeList(any: string, items: StaffScopeOption[], selected: string): StaffScopeOption[] {
  const linked = selected !== '' && !items.some((item) => item.value === selected);
  return [{ title: any, value: '' }, ...(linked ? [{ title: 'Выбрано по ссылке', value: selected }] : []), ...items];
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

/** A success status alone is not proof: a proxy page without the order must keep the attempt for a safe repeat. */
export function isCreatedOrder(value: unknown): value is CreatedOrder {
  if (!value || typeof value !== 'object') return false;
  const order = value as Record<string, unknown>;
  return (
    typeof order.id === 'string' &&
    typeof order.number === 'string' &&
    typeof order.accessKey === 'string' &&
    /^[a-f0-9]{64}$/.test(order.accessKey)
  );
}
