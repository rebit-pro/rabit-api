import { currentBuyer, settlementLabel } from '../settlement/rules.ts';
import type { OrganizationState } from '../organization/types.js';
import type { OrderSnapshot } from '../orders/types.js';
import type { CaseCommand, CaseErrors, WorkFilters, StaffOrder, CaseItem } from './types.js';
import { moscowInput } from '../handoff/rules.ts';
export const supportStatuses = { received: 'Новое', 'in-progress': 'В работе', resolved: 'Решено' };
export const emptyFilters = (): WorkFilters => ({
  query: '',
  institution: '',
  shoot: '',
  group: '',
  payment: '',
  production: '',
  dateFrom: '',
  dateTo: '',
  status: '',
  topic: '',
  settlement: '',
  late: ''
});
export function orderInScope(
  order: Pick<OrderSnapshot, 'groupId'>,
  state: OrganizationState,
  actor: { id: number; role: string }
): boolean {
  const group = state.groups.find((g) => g.id === order.groupId),
    institution = state.institutions.find((i) => i.id === group?.institutionId);
  return !!institution && (actor.role === 'organizer' || (actor.role === 'curator' && institution.curatorId === actor.id));
}
export function parseExtension(value: string, now: string, previous: string | null): string | null {
  if (!/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/.test(value) || !previous) return null;
  const time = Date.parse(value + ':00+03:00');
  if (!Number.isFinite(time) || time <= Date.parse(now) || time <= Date.parse(previous)) return null;
  const result = new Date(time).toISOString();
  return moscowInput(result) === value ? result : null;
}
export function caseErrors(command: CaseCommand, now: string, previous: string | null): CaseErrors {
  const errors: CaseErrors = {};
  if (command.action === 'extend') {
    if (!parseExtension(command.closesAt, now, previous))
      errors.closesAt = 'Новая дата должна существовать и быть позже текущего срока и текущего времени (МСК).';
    if (command.reason.trim().length < 5 || command.reason.trim().length > 500) errors.reason = 'Укажите причину: от 5 до 500 символов.';
    if (!command.confirmed) errors.confirmed = 'Подтвердите изменение сроков для всей группы.';
  } else {
    if (!['in-progress', 'resolved'].includes(command.status)) errors.status = 'Выберите состояние обращения.';
    if (command.comment.trim().length < 5 || command.comment.trim().length > 2000)
      errors.comment = 'Напишите ответ родителю: от 5 до 2000 символов.';
  }
  return errors;
}
export function matchesOrder(order: StaffOrder, f: WorkFilters): boolean {
  const date = moscowInput(order.createdAt).slice(0, 10);
  const hay = [
    order.number,
    currentBuyer(order).name,
    currentBuyer(order).email,
    currentBuyer(order).phone,
    order.groupName,
    ...order.quote.lines.flatMap((l) => [l.childCode, l.photo?.code ?? ''])
  ]
    .join(' ')
    .toLocaleLowerCase('ru');
  return (
    (!f.query || hay.includes(f.query.trim().toLocaleLowerCase('ru'))) &&
    (!f.institution || order.institutionId === f.institution) &&
    (!f.shoot || order.shootId === f.shoot) &&
    (!f.group || order.groupId === f.group) &&
    (!f.payment || order.paymentStatus === f.payment) &&
    (!f.production || order.productionStatus === f.production) &&
    (!f.dateFrom || date >= f.dateFrom) &&
    (!f.dateTo || date <= f.dateTo) &&
    (!f.settlement || settlementLabel(order) === f.settlement) &&
    (!f.late || !!order.latePayment)
  );
}
export function matchesCase(item: CaseItem, f: WorkFilters): boolean {
  return (
    matchesOrder({ ...item.order, createdAt: item.request.createdAt }, { ...f, query: '' }) &&
    (!f.status || item.request.status === f.status) &&
    (!f.topic || item.request.topic === f.topic) &&
    (!f.query ||
      [item.request.number, item.request.message, item.request.photoCode ?? '', item.order.number, item.request.replyEmail]
        .join(' ')
        .toLocaleLowerCase('ru')
        .includes(f.query.trim().toLocaleLowerCase('ru')))
  );
}
