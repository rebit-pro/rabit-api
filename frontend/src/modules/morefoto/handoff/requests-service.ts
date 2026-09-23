import { isMockApiEnabled } from '@/mocks/config';
import { staffRequestsApi, staffRequestError } from './api';
import { DemoError } from '../mocks/service';
import { simulateRequest } from '../mocks/runtime';
import { readDemo } from '../mocks/storage';
import { readPhotos, writePhotos } from '../photos/repository';
import { photoCode } from '../photos/rules';
import { canReadRequest, conflict, handoffAccess, handoffLock, HandoffValidationError, requireReview } from './scope';
import { requestRows, reviewRequest } from './rules';
import type { StaffCommand, StaffRequest } from './types';
import type { OrderSnapshot } from '../orders/types';

export async function saveStaffRequest(token: string, command: StaffCommand): Promise<void> {
  if (!isMockApiEnabled) {
    if (command.action === 'submit') {
      const errors: Record<string, string> = {};
      for (const row of command.rows) {
        if (!/^[A-Z]{1,3}(?:[0-9]{3})?$/.test(row.code.trim().toUpperCase())) {
          errors['code:' + row.id] = 'Укажите код ребёнка или снимка: например, A или A001. У снимка ровно три цифры.';
        }
      }
      if (Object.keys(errors).length > 0) throw new HandoffValidationError(errors);
    }
    if (command.action === 'confirm' && !command.confirmed)
      throw new HandoffValidationError({ confirmed: 'Подтвердите полный набор и перенос.' });
    try {
      if (command.action === 'submit') await staffRequestsApi.save(command);
      else if (command.action === 'clarify') await staffRequestsApi.clarify(command);
      else await staffRequestsApi.transfer(command);
      return;
    } catch (cause) {
      throw new Error(staffRequestError(cause, command.action));
    }
  }
  handoffAccess(token);
  await simulateRequest();
  return handoffLock(() => {
    const access = handoffAccess(token),
      { account, organization, scope, now } = access,
      photos = readPhotos();
    const requests = photos.staffRequests ?? [],
      signature = JSON.stringify(command);
    const completed = photos.staffOperations?.find((op) => op.actorId === account.id && op.requestId === command.requestId);
    if (completed) {
      if (completed.signature !== signature) conflict();
      return;
    }
    if (!command.requestId) throw new DemoError(400, 'Некорректный запрос.');
    const previous = requests.find((r) => r.id === command.id);
    if (command.id && !previous) throw new DemoError(404, 'Список не найден.');
    if (previous && !canReadRequest(previous, access)) throw new DemoError(403, 'Список недоступен.');
    if (previous?.status === 'transferred' && command.action === 'confirm') {
      requireReview(token, previous);
      return;
    }
    if ((previous?.revision ?? null) !== command.revision) conflict();
    if (previous?.status === 'transferred') throw new Error('Список уже проверен и перенесён.');
    let request: StaffRequest;
    if (command.action === 'submit') {
      if (!['teacher', 'organizer'].includes(account.role) || (previous && previous.createdBy !== account.id))
        throw new DemoError(403, 'Передать список может его автор — ответственный группы или организатор.');
      const shoot = organization.shoots.find(
        (s) => s.id === command.shootId && s.institutionId === command.institutionId && scope.shoots.some((item) => item.id === s.id)
      );
      if (!shoot) throw new DemoError(403, 'Съёмка недоступна.');
      if (previous && (previous.shootId !== shoot.id || previous.institutionId !== shoot.institutionId))
        throw new Error('Съёмку отправленного списка нельзя заменить.');
      const allowed = organization.groups.filter((g) => scope.groups.some((item) => item.id === g.id));
      const { errors, resolved } = requestRows(command.rows, allowed, photos.photos, shoot.id, requests, command.id);
      if (command.comment.length > 500) errors.comment = 'Комментарий: не более 500 символов.';
      if (Object.keys(errors).length) throw new HandoffValidationError(errors);
      request = {
        id: previous?.id ?? 'request-' + crypto.randomUUID(),
        institutionId: shoot.institutionId,
        shootId: shoot.id,
        createdBy: account.id,
        createdByName: previous?.createdByName ?? account.name,
        createdAt: previous?.createdAt ?? now,
        revision: (previous?.revision ?? 0) + 1,
        status: 'submitted',
        rows: resolved,
        comment: command.comment.trim(),
        history: [
          ...(previous?.history ?? []),
          { kind: 'submitted', actorName: account.name, actorId: account.id, at: now, comment: command.comment.trim() }
        ]
      };
    } else {
      if (!previous) throw new Error('Сначала передайте список куратору.');
      requireReview(token, previous);
      request = { ...previous, revision: previous.revision + 1 };
      if (command.action === 'clarify') {
        if (command.reason.trim().length < 5 || command.reason.length > 500)
          throw new HandoffValidationError({ reason: 'Укажите, что нужно уточнить: от 5 до 500 символов.' });
        request.status = 'clarification';
        request.history = [
          ...request.history,
          { kind: 'clarification', actorName: account.name, actorId: account.id, at: now, comment: command.reason.trim() }
        ];
      } else {
        if (previous.status !== 'submitted') throw new Error('Дождитесь уточнённого списка от автора.');
        if (!command.confirmed) throw new HandoffValidationError({ confirmed: 'Подтвердите полный набор и перенос.' });
        const preview = reviewRequest(previous, organization.groups, photos, readDemo<OrderSnapshot[]>('orders:v1', []));
        if (preview.signature !== command.signature) conflict();
        request.results = preview.bundles.map((bundle) => {
          bundle.photos.forEach((photo, index) => {
            // Stable IDs, storage references and originalGroupId preserve historical orders and delivery.
            photo.groupId = preview.targetGroupId;
            photo.childCode = bundle.targetCode;
            photo.sequence = index + 1;
            photo.code = photoCode(bundle.targetCode, index + 1);
            photo.revision++;
          });
          if (bundle.photos.some((p) => p.id === photos.covers[bundle.row.groupId])) delete photos.covers[bundle.row.groupId];
          return {
            rowId: bundle.row.id,
            fromGroupId: bundle.row.groupId,
            fromChildCode: bundle.row.childCode,
            targetGroupId: preview.targetGroupId,
            targetChildCode: bundle.targetCode,
            photoIds: bundle.photos.map((p) => p.id)
          };
        });
        if (!photos.covers[preview.targetGroupId]) photos.covers[preview.targetGroupId] = preview.bundles[0]?.photos[0]?.id ?? '';
        request.status = 'transferred';
        request.history = [
          ...request.history,
          {
            kind: 'transferred',
            actorName: account.name,
            actorId: account.id,
            at: now,
            comment: 'Полные наборы перенесены в папку сотрудников. История заказов сохранена.'
          }
        ];
      }
    }
    photos.staffRequests = previous ? requests.map((r) => (r.id === previous.id ? request : r)) : [...requests, request];
    photos.staffOperations = [...(photos.staffOperations ?? []), { requestId: command.requestId, actorId: account.id, signature }];
    writePhotos(photos);
  });
}
