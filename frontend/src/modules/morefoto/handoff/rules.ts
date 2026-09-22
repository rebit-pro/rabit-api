import type { Catalog } from '../commerce/types.js';
import type { ManagedGroup } from '../organization/types.js';
import type { ManagedPhoto, PhotoState } from '../photos/types.js';
import type { HandoffErrors, LinkCommand, RequestRow, SubmittedRow, StaffRequest, ReviewBundle, RequestPreview } from './types.js';
import { nextChildCode } from '../photos/rules.ts';

export function moscowInput(value: string): string {
  const date = new Date(Date.parse(value) + 3 * 3600000);
  return Number.isFinite(date.getTime()) ? date.toISOString().slice(0, 16) : '';
}
export function parseTransmission(value: string, now: string): string | null {
  if (!/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/.test(value)) return null;
  const time = Date.parse(value + ':00+03:00');
  if (!Number.isFinite(time) || time > Date.parse(now) || moscowInput(new Date(time).toISOString()) !== value) return null;
  return new Date(time).toISOString();
}
/** Moscow form input `YYYY-MM-DDTHH:mm` as the server moment with an explicit offset. */
export function serverMoment(value: string): string {
  return value + ':00+03:00';
}
/** Client checks before a live link command; the server repeats every rule and owns the final answer. */
export function liveLinkErrors(command: LinkCommand, now: string): HandoffErrors {
  const errors: HandoffErrors = {};
  if (command.action === 'prepare') {
    if (!command.photosReviewed) errors.photosReviewed = 'Подтвердите проверку фотографий.';
    if (!command.conditionsReviewed) errors.conditionsReviewed = 'Подтвердите проверку продукции, цен и условий.';
    if (!command.staffReviewed) errors.staffReviewed = 'Подтвердите проверку сотрудников и ответственных.';
    return errors;
  }
  if (!parseTransmission(command.sentAt, now)) errors.sentAt = 'Укажите существующую дату и время не позже текущего (МСК).';
  if (!command.confirmed) errors.confirmed = 'Подтвердите факт передачи и показанные сроки.';
  const reason = command.reason.trim().length;
  if (command.action === 'correct' && (reason < 5 || reason > 500)) errors.reason = 'Укажите причину исправления: от 5 до 500 символов.';
  return errors;
}
export function calendarDays(value: string, days: number): string {
  return new Date(Date.parse(value) + days * 86400000).toISOString();
}
export function groupSentAt(group: Pick<ManagedGroup, 'state' | 'closesAt' | 'sentAt'>): string | null {
  return group.sentAt ?? (group.state !== 'preparing' && group.closesAt ? calendarDays(group.closesAt, -7) : null);
}
export function currentGroupState(group: Pick<ManagedGroup, 'state' | 'closesAt'>, now: string) {
  return group.state === 'open' && group.closesAt && Date.parse(group.closesAt) <= Date.parse(now) ? 'closed' : group.state;
}
export function preparationSignature(group: ManagedGroup, photos: PhotoState, catalog: Catalog): string {
  return JSON.stringify({
    group: [group.id, group.institutionId, group.shootId, group.name, group.teacherId],
    photos: photos.photos.filter((p) => p.groupId === group.id).map((p) => [p.id, p.revision, p.childCode, p.code]),
    cover: photos.covers[group.id] ?? null,
    catalog: [catalog.revision, catalog.conditionsRevision ?? 0],
    pending: (photos.staffRequests ?? [])
      .filter((r) => r.status !== 'transferred' && r.rows.some((row) => row.groupId === group.id))
      .map((r) => [r.id, r.revision])
  });
}
export function preparationProblems(group: ManagedGroup, photos: PhotoState, catalog: Catalog): string[] {
  const list = photos.photos.filter((p) => p.groupId === group.id),
    problems: string[] = [];
  if (!list.length) problems.push('В группе ещё нет фотографий.');
  if (list.some((p) => !p.childCode)) problems.push('Распределите все фотографии по детям.');
  if (!catalog.products.some((p) => p.active && Number.isSafeInteger(p.price) && p.price >= 0))
    problems.push('Нет доступной продукции с корректной ценой.');
  if ((photos.staffRequests ?? []).some((r) => r.status !== 'transferred' && r.rows.some((row) => row.groupId === group.id)))
    problems.push('Сначала завершите проверку списков сотрудников этой группы.');
  return problems;
}
export function resolveCode(photos: ManagedPhoto[], groupId: string, input: string): { photos: ManagedPhoto[]; childCode: string } | null {
  const code = input.trim().toUpperCase();
  const candidates = photos.filter((p) => p.groupId === groupId && p.childCode && (p.childCode === code || p.code === code));
  const children = [...new Set(candidates.map((p) => p.childCode!))];
  if (children.length !== 1) return null;
  return { childCode: children[0]!, photos: photos.filter((p) => p.groupId === groupId && p.childCode === children[0]) };
}
export function requestRows(
  rows: RequestRow[],
  groups: ManagedGroup[],
  photos: ManagedPhoto[],
  shootId: string,
  existing: StaffRequest[],
  id: string | null
): { errors: HandoffErrors; resolved: SubmittedRow[] } {
  const errors: HandoffErrors = {},
    resolved: SubmittedRow[] = [];
  if (!rows.length || rows.length > 30) errors.rows = 'Добавьте от 1 до 30 строк.';
  if (new Set(rows.map((r) => r.id)).size !== rows.length) errors.rows = 'Строки списка повторяются.';
  const chosen = new Set<string>();
  for (const row of rows) {
    const group = groups.find((g) => g.id === row.groupId && g.shootId === shootId && g.kind === 'regular');
    if (!group) {
      errors['group:' + row.id] = 'Выберите исходную группу этой съёмки.';
      continue;
    }
    const match = resolveCode(photos, row.groupId, row.code);
    if (!match) {
      errors['code:' + row.id] = 'Код не найден или неоднозначен. Укажите точный код ребёнка или снимка.';
      continue;
    }
    const ids = match.photos.map((p) => p.id);
    if (ids.some((value) => chosen.has(value))) errors['code:' + row.id] = 'Этот ребёнок уже включён в список.';
    if (
      existing.some(
        (r) => r.id !== id && r.status !== 'transferred' && r.rows.some((other) => other.photoIds.some((value) => ids.includes(value)))
      )
    )
      errors['code:' + row.id] = 'Этот ребёнок уже есть в списке на проверке.';
    ids.forEach((value) => chosen.add(value));
    resolved.push({ ...row, code: row.code.trim().toUpperCase(), childCode: match.childCode, photoIds: ids });
  }
  return { errors, resolved };
}
export function reviewRequest(
  request: StaffRequest,
  groups: Pick<ManagedGroup, 'id' | 'kind' | 'shootId' | 'institutionId'>[],
  state: PhotoState,
  orders: { groupId: string; quote: { lines: { childCode: string }[] } }[] = []
): RequestPreview {
  const target = groups.find((g) => g.kind === 'staff' && g.shootId === request.shootId && g.institutionId === request.institutionId);
  if (!target) throw new Error('В этой съёмке ещё нет папки сотрудников. Организатор должен создать её перед переносом.');
  const reserved = state.photos.map((p) => ({ ...p })),
    bundles: ReviewBundle[] = [];
  for (const row of request.rows) {
    if (
      !groups.some(
        (g) => g.id === row.groupId && g.kind === 'regular' && g.shootId === request.shootId && g.institutionId === request.institutionId
      )
    )
      throw new Error('Исходная группа больше не относится к этой съёмке. Запросите уточнение списка.');
    const current = state.photos.filter((p) => p.groupId === row.groupId && p.childCode === row.childCode);
    if (!current.length || row.photoIds.some((id) => !current.some((p) => p.id === id)))
      throw new Error('Исходный набор изменился или уже перенесён. Запросите уточнение списка.');
    const targetCode = nextChildCode(reserved, target.id);
    current.forEach((p) => reserved.push({ ...p, groupId: target.id, childCode: targetCode }));
    bundles.push({ row, photos: current, targetCode });
  }
  const signature = JSON.stringify([
    request.id,
    request.revision,
    target.id,
    bundles.map((b) => [b.row.id, b.targetCode, b.photos.map((p) => [p.id, p.revision, p.code])]),
    state.photos.filter((p) => p.groupId === target.id).map((p) => [p.id, p.revision, p.childCode])
  ]);
  const hasOrders = orders.some((o) =>
    request.rows.some((r) => r.groupId === o.groupId && o.quote.lines.some((l) => l.childCode === r.childCode))
  );
  return { targetGroupId: target.id, bundles, signature, hasOrders };
}

export function closingAfterCorrection(sentAt: string, extensionClosesAt?: string): string {
  const base = calendarDays(sentAt, 7);
  return extensionClosesAt && Date.parse(extensionClosesAt) > Date.parse(base) ? extensionClosesAt : base;
}
