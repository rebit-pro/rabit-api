import { DemoError } from '../mocks/service';
import { simulateRequest } from '../mocks/runtime';
import { writeOrganization } from '../organization/repository';
import { readPhotos } from '../photos/repository';
import { getCatalog } from '../commerce/mocks/catalog';
import { closingAfterCorrection, groupSentAt, parseTransmission, preparationProblems, preparationSignature } from './rules';
import { conflict, handoffLock, HandoffValidationError, requireGroup } from './scope';
import type { LinkCommand, LinkEvent } from './types';
export async function saveLink(token: string, command: LinkCommand): Promise<void> {
  requireGroup(token, command.groupId);
  await simulateRequest();
  return handoffLock(() => {
    const { account, organization, group, now } = requireGroup(token, command.groupId);
    if (
      account.role === 'head' ||
      (command.action !== 'transmit' && account.role !== 'organizer' && !(command.action === 'correct' && account.role === 'curator'))
    )
      throw new DemoError(403, 'Для этого действия недостаточно прав.');
    if (!command.requestId) throw new DemoError(400, 'Некорректный запрос.');
    const previousSentAt = groupSentAt(group);
    // Repeated reporting is harmless even with a different request or stale form.
    if (command.action === 'transmit' && previousSentAt) return;
    const completed = group.linkOperations?.find((op) => op.requestId === command.requestId && op.actorId === account.id),
      signature = JSON.stringify(command);
    if (completed) {
      if (completed.signature !== signature) conflict();
      return;
    }
    if (command.revision !== group.revision) conflict();
    const photos = readPhotos(),
      catalog = getCatalog(group.id),
      currentSignature = preparationSignature(group, photos, catalog);
    const errors: Record<string, string> = {};
    let event: LinkEvent;
    if (command.action === 'prepare') {
      if (previousSentAt) throw new DemoError(409, 'Приём уже запускался. Дату можно исправить отдельно.');
      const problems = preparationProblems(group, photos, catalog);
      if (problems.length) throw new Error(problems.join(' '));
      if (command.signature !== currentSignature) conflict();
      if (!command.photosReviewed) errors.photosReviewed = 'Подтвердите проверку фотографий.';
      if (!command.conditionsReviewed) errors.conditionsReviewed = 'Подтвердите проверку демонстрационных условий.';
      if (!command.staffReviewed) errors.staffReviewed = 'Подтвердите проверку сотрудников и ответственных.';
      if (Object.keys(errors).length) throw new HandoffValidationError(errors);
      group.preparation = { at: now, actorId: account.id, signature: currentSignature };
      event = { kind: 'prepared', at: now, actorId: account.id };
    } else {
      const sentAt = parseTransmission(command.sentAt, now);
      if (!sentAt) errors.sentAt = 'Укажите существующую дату и время не позже демонстрационного текущего времени (МСК).';
      if (!command.confirmed) errors.confirmed = 'Подтвердите факт передачи и показанные сроки.';
      if (command.action === 'correct' && command.reason.trim().length < 5)
        errors.reason = 'Укажите причину исправления, не менее 5 символов.';
      if (command.reason.length > 500) errors.reason = 'Причина: не более 500 символов.';
      if (Object.keys(errors).length) throw new HandoffValidationError(errors);
      if (command.action === 'transmit') {
        if (!group.preparation || group.preparation.signature !== currentSignature)
          throw new Error('Подборка или условия изменились. Организатор должен проверить ссылку перед передачей.');
        const problems = preparationProblems(group, photos, catalog);
        if (problems.length) throw new Error(problems.join(' '));
      } else if (!previousSentAt) throw new Error('Передача ссылки ещё не отмечена.');
      const closesAt = closingAfterCorrection(sentAt!, group.extensions?.[group.extensions.length - 1]?.closesAt);
      event = {
        kind: command.action === 'transmit' ? 'transmitted' : 'corrected',
        actorId: account.id,
        at: now,
        sentAt: sentAt!,
        closesAt,
        ...(previousSentAt ? { previousSentAt, previousClosesAt: group.closesAt ?? undefined, reason: command.reason.trim() } : {})
      };
      group.sentAt = sentAt!;
      group.closesAt = closesAt;
      group.state = Date.parse(closesAt) <= Date.parse(now) ? 'closed' : 'open';
    }
    event.actorName = account.name;
    group.linkHistory = [...(group.linkHistory ?? []), event];
    group.revision++;
    group.linkOperations = [...(group.linkOperations ?? []), { requestId: command.requestId, actorId: account.id, signature }];
    writeOrganization(organization);
  });
}
