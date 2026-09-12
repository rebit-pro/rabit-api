import { DemoError } from '../mocks/service';
import { simulateRequest } from '../mocks/runtime';
import { handoffAccess, conflict } from '../handoff/scope';
import { readOrders, saveOrder, withOrderLock } from '../orders/services/orders';
import { writeOrganization } from '../organization/repository';
import { groupSentAt } from '../handoff/rules';
import { orderPeriod } from './period';
import { orderInScope, caseErrors, parseExtension } from './rules';
import type { CaseCommand, CuratorWorkspace, StaffOrder } from './types';
function curator(token: string) {
  const context = handoffAccess(token);
  if (!['organizer', 'curator'].includes(context.account.role))
    throw new DemoError(403, 'Заказы и обращения доступны организатору и куратору своей области.');
  return context;
}
export async function loadCurator(token: string): Promise<CuratorWorkspace> {
  await simulateRequest();
  const { account, organization, scope, now } = curator(token);
  const orders: StaffOrder[] = readOrders()
    .filter((o) => orderInScope(o, organization, account))
    .map((order) => {
      const { accessKey, requestId, galleryToken, ...visible } = order;
      void accessKey;
      void requestId;
      void galleryToken;
      const group = organization.groups.find((g) => g.id === order.groupId)!;
      return { ...visible, institutionId: group.institutionId, shootId: group.shootId, period: orderPeriod(group.id, organization, now) };
    });
  return { scope, orders, cases: orders.flatMap((order) => (order.supportRequests ?? []).map((request) => ({ order, request }))), now };
}
export class CaseValidationError extends Error {
  constructor(public errors: Record<string, string>) {
    super('Проверьте выделенные поля.');
  }
}
export async function saveCase(token: string, command: CaseCommand): Promise<void> {
  curator(token);
  await simulateRequest();
  const commit = () =>
    withOrderLock(() => {
      const { organization, account, now } = curator(token),
        order = readOrders().find((o) => o.id === command.orderId);
      if (!order || !orderInScope(order, organization, account)) throw new DemoError(403, 'Заказ недоступен в вашей области.');
      const request = order.supportRequests?.find((r) => r.id === command.supportId);
      if (!request) throw new DemoError(404, 'Обращение недоступно.');
      const group = organization.groups.find((g) => g.id === order.groupId)!,
        signature = JSON.stringify(command);
      if (!command.requestId || !['reply', 'extend'].includes(command.action)) throw new DemoError(400, 'Некорректное действие.');
      const previous =
        command.action === 'reply'
          ? request.history?.find((e) => e.requestId === command.requestId)
          : group.extensions?.find((e) => e.id === command.requestId);
      if (previous) {
        if (previous.signature !== signature) conflict();
        return;
      }
      if ((request.revision ?? 1) !== command.revision) conflict();
      const errors = caseErrors(command, now, group.closesAt);
      if (Object.keys(errors).length) throw new CaseValidationError(errors);
      if (command.action === 'reply') {
        const updated = {
          ...request,
          status: command.status,
          revision: (request.revision ?? 1) + 1,
          history: [
            ...(request.history ?? []),
            {
              requestId: command.requestId,
              signature,
              actorId: account.id,
              actorName: account.name,
              at: now,
              status: command.status,
              comment: command.comment.trim()
            }
          ]
        };
        saveOrder({ ...order, supportRequests: order.supportRequests!.map((r) => (r.id === request.id ? updated : r)) });
      } else {
        if (group.revision !== command.groupRevision) conflict();
        if (request.status === 'resolved') throw new Error('Сначала верните обращение в работу.');
        const sentAt = groupSentAt(group);
        if (!sentAt || group.state === 'preparing') throw new Error('Ссылка ещё не передана. Сначала отметьте фактическую передачу.');
        const closesAt = parseExtension(command.closesAt, now, group.closesAt)!;
        group.extensions = [
          ...(group.extensions ?? []),
          {
            id: command.requestId,
            actorId: account.id,
            actorName: account.name,
            at: now,
            orderId: order.id,
            supportId: request.id,
            previousClosesAt: group.closesAt!,
            closesAt,
            reason: command.reason.trim(),
            signature
          }
        ];
        group.sentAt = sentAt;
        group.closesAt = closesAt;
        group.state = 'open';
        group.revision++;
        writeOrganization(organization);
      }
    });
  return navigator.locks ? navigator.locks.request('morefoto:organization:write', commit) : commit();
}
