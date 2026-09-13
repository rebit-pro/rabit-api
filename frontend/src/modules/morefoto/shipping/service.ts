import { transferSummary } from './projection';
import { handoffAccess, handoffLock } from '../handoff/scope';
import { DemoError } from '../mocks/service';
import { simulateRequest } from '../mocks/runtime';
import { writeDemo, readDemo } from '../mocks/storage';
import { readOrders, withOrderLock, randomKey } from '../orders/services/orders';
import { readProduction, productionKey } from '../production/service';
import { readPhotos } from '../photos/repository';
import { buildPlan } from '../production/rules';
import { parseTransmission } from '../handoff/rules';
import { deliveryErrors, inspectDelivery } from './rules';
import type { DeliveryView, TransferBatch, TransferGroup, DeliveryCommand, DeliveryErrors } from './types';
export class DeliveryValidationError extends Error {
  constructor(public errors: DeliveryErrors) {
    super('Проверьте выделенные поля.');
  }
}
function access(token: string) {
  const a = handoffAccess(token),
    state = readProduction(),
    orders = readOrders(),
    photos = readPhotos();
  const groups = a.organization.groups
    .filter((g) => a.scope.groups.some((s) => s.id === g.id))
    .map((group) => {
      const job =
        state.jobs.find((j) => j.groupId === group.id && j.institutionId === group.institutionId && j.shootId === group.shootId) ?? null;
      const item = {
        group,
        job,
        institutionName: a.organization.institutions.find((i) => i.id === group.institutionId)!.name,
        shootName: a.organization.shoots.find((s) => s.id === group.shootId)?.name ?? group.shootName,
        plan: buildPlan({ group, organization: a.organization, photos, orders, state, now: a.now })
      };
      return inspectDelivery(item, state, a.now);
    });
  const batches = new Map<string, { view: TransferBatch; groups: TransferGroup[] }>();
  for (const group of groups)
    if (group.transferGroup) {
      const g = group.view,
        id = JSON.stringify([g.institutionId, g.shootId]);
      if (!batches.has(id))
        batches.set(id, {
          view: {
            id,
            institutionId: g.institutionId,
            institutionName: g.institutionName,
            shootId: g.shootId,
            shootName: g.shootName,
            address: a.organization.institutions.find((i) => i.id === g.institutionId)!.address,
            signature: '',
            groups: [],
            packs: 0,
            prints: 0,
            earliestAt: group.transferGroup.readyAt
          },
          groups: []
        });
      const batch = batches.get(id)!;
      batch.groups.push(group.transferGroup);
      batch.view.groups.push({ id: g.id, name: g.name, kind: g.kind, packs: g.packs, prints: g.prints, deadline: g.deadline });
      batch.view.packs += g.packs;
      batch.view.prints += g.prints;
      if (group.transferGroup.readyAt > batch.view.earliestAt) batch.view.earliestAt = group.transferGroup.readyAt;
    }
  for (const batch of batches.values())
    batch.view.signature = JSON.stringify([
      batch.view.institutionId,
      batch.view.shootId,
      batch.view.address,
      batch.groups,
      groups.filter((g) => batch.groups.some((t) => t.groupId === g.view.id)).map((g) => g.view.signature)
    ]);
  return { ...a, state, groups, batches };
}
export async function loadDelivery(token: string): Promise<DeliveryView> {
  await simulateRequest();
  const a = access(token),
    editable = a.account.role === 'organizer',
    staff = ['organizer', 'curator'].includes(a.account.role);
  const allowed = new Set(a.scope.groups.map((g) => g.id));
  return {
    editable,
    staff,
    actorId: a.account.id,
    actorName: a.account.name,
    now: a.now,
    groups: a.groups.map((g) => ({
      ...g.view,
      signature: editable ? g.view.signature : '',
      canReady: editable && g.view.canReady,
      canUnready: editable && g.view.canUnready
    })),
    batches: staff ? [...a.batches.values()].map((b) => ({ ...b.view, signature: editable ? b.view.signature : '' })) : [],
    transfers: (a.state.transfers ?? [])
      .filter((t) => a.scope.institutions.some((i) => i.id === t.institutionId) && t.groups.some((g) => allowed.has(g.groupId)))
      .map((t) => transferSummary(t, allowed, staff))
      .reverse()
  };
}
export async function saveDelivery(token: string, command: DeliveryCommand): Promise<void> {
  await simulateRequest();
  return handoffLock(() =>
    withOrderLock(() => {
      const a = access(token);
      if (a.account.role !== 'organizer') throw new DemoError(403, 'Готовность и передачу отмечает организатор.');
      if (!['ready', 'unready', 'transfer'].includes(command.kind) || !command.id || command.id.length > 128)
        throw new DemoError(422, 'Некорректное действие.');
      const signature = JSON.stringify(command),
        operations = a.state.deliveryOperations ?? [],
        prior = operations.find((o) => o.id === command.id);
      if (prior) {
        if (prior.signature !== signature || prior.actorId !== a.account.id)
          throw new DemoError(409, 'Ключ повторного действия не совпадает.');
        return;
      }
      const g = a.groups.find((g) => g.view.id === command.targetId),
        batch = a.batches.get(command.targetId);
      let earliest = a.now;
      if (command.kind === 'transfer') {
        if (!batch || batch.view.signature !== command.signature)
          throw new DemoError(409, 'Готовые пакеты изменились. Загрузите актуальные данные.');
        earliest = batch.view.earliestAt;
      } else {
        if (!g || g.view.signature !== command.signature)
          throw new DemoError(409, 'Версия или состав изменились. Загрузите актуальные данные.');
        if (command.kind === 'ready' && !g.view.canReady) throw new DemoError(422, g.view.problem || 'Готовность уже учтена.');
        if (command.kind === 'unready' && !g.view.canUnready)
          throw new DemoError(422, 'Переданную продукцию нельзя вернуть в комплектацию этой отметкой.');
        earliest =
          Object.values(g.version?.packages ?? {})
            .map((p) => p.at)
            .sort()
            .slice(-1)[0] ?? a.now;
      }
      const errors = deliveryErrors(command, a.now, earliest);
      if (Object.keys(errors).length) throw new DeliveryValidationError(errors);
      const at = parseTransmission(command.date, a.now)!,
        comment = command.comment.trim(),
        responsible = command.responsible.trim();
      if (command.kind === 'transfer') {
        const transfers = a.state.transfers ?? [];
        transfers.push({
          id: randomKey(),
          number: 'ПД-' + String(transfers.length + 1).padStart(4, '0'),
          institutionId: batch!.view.institutionId,
          institutionName: batch!.view.institutionName,
          shootId: batch!.view.shootId,
          shootName: batch!.view.shootName,
          at,
          recordedAt: a.now,
          actor: a.account.name,
          responsible,
          receiver: command.receiver.trim(),
          comment,
          groups: batch!.groups
        });
        a.state.transfers = transfers;
        for (const item of batch!.groups) {
          const job = a.state.jobs.find((j) => j.id === item.jobId)!;
          job.revision++;
          job.history.push({
            at: a.now,
            actor: a.account.name,
            text: `Передано в учреждение: ${transfers[transfers.length - 1]!.number}, версия ${item.version}. ${comment}`
          });
        }
      } else {
        if (command.kind === 'ready') g!.version!.ready = { at, actor: a.account.name, responsible, comment };
        else delete g!.version!.ready;
        g!.job!.revision++;
        g!.job!.history.push({
          at: a.now,
          actor: a.account.name,
          text: `Версия ${g!.version!.number}: ${command.kind === 'ready' ? 'готовность подтверждена' : 'готовность снята'}. ${comment}`
        });
      }
      a.state.deliveryOperations = [...operations, { id: command.id, signature, actorId: a.account.id }];
      writeDemo(productionKey, a.state);
      if (readDemo('delivery:lose-response-once', false)) {
        writeDemo('delivery:lose-response-once', false);
        throw new DemoError(503, 'Ответ потерян. Повторите то же действие: результат уже сохранён.');
      }
    })
  );
}
