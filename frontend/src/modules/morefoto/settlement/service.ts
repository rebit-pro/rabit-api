import { handoffAccess, conflict } from '../handoff/scope';
import { orderInScope } from '../curator/rules';
import { readOrders, saveOrder, withOrderLock } from '../orders/services/orders';
import { readPhotos } from '../photos/repository';
import { readDemo, writeDemo } from '../mocks/storage';
import { simulateRequest } from '../mocks/runtime';
import { DemoError } from '../mocks/service';
import { downloadAccess } from '../orders/delivery/rules';
import { phoneDigits } from '../ui/field-values';
import {
  settlement,
  saleVersion,
  saleErrors,
  currentBuyer,
  currentLines,
  eligiblePhotos,
  financials,
  remaining,
  refundAmount,
  allocateRefund,
  productionStarted
} from './rules';
import type { SaleOrder, SaleCommand, SaleErrors, ActorEvent, Refund } from './types';
function access(token: string, orderId: string) {
  const ctx = handoffAccess(token),
    order = readOrders().find((o) => o.id === orderId);
  if (!order || !orderInScope(order, ctx.organization, ctx.account)) throw new DemoError(403, 'Заказ недоступен в вашей области.');
  return { ...ctx, order };
}
export function correctionPhotos(order: SaleOrder, lineId: string) {
  const line = currentLines(order).find((l) => l.id === lineId);
  return readPhotos()
    .photos.filter((p) => (p.groupId === order.groupId || p.originalGroupId === order.groupId) && p.childCode === line?.childCode)
    .map(({ id, code, thumbSrc, previewSrc, width, height }) => ({ id, code, thumbSrc, previewSrc, width, height }));
}
export class SaleValidationError extends Error {
  constructor(public errors: SaleErrors) {
    super('Проверьте выделенные поля.');
  }
}
export async function saveSettlement(token: string, c: SaleCommand): Promise<void> {
  access(token, c.orderId);
  await simulateRequest();
  const commit = () =>
    withOrderLock(() => {
      const { order, account, now } = access(token, c.orderId),
        s = settlement(order),
        signature = JSON.stringify(c);
      if (
        c.kind !== 'settlement' ||
        !c.requestId ||
        !['contacts', 'line', 'refund', 'result', 'retry', 'fulfilment', 'recover'].includes(c.action)
      )
        throw new Error('Неизвестная операция. Обновите форму.');
      const prior = s.operations.find((v) => v.id === c.requestId);
      if (prior) {
        if (prior.signature !== signature) conflict();
        return;
      }
      if (c.version !== saleVersion(order)) conflict();
      if (order.paymentStatus !== 'paid' || !order.paidAt) throw new Error('Сопровождение доступно после подтверждённой оплаты.');
      if (c.supportId && !order.supportRequests?.some((r) => r.id === c.supportId)) throw new Error('Обращение не относится к заказу.');
      const errors = saleErrors(order, c);
      if (Object.keys(errors).length) throw new SaleValidationError(errors);
      const event: ActorEvent = {
        id: c.requestId,
        at: now,
        actorId: account.id,
        actorName: account.name,
        reason: c.reason.trim(),
        ...(c.supportId ? { supportId: c.supportId } : {})
      };
      const addRefund = () => {
        const allocations = allocateRefund(order, c);
        const ids =
          c.files === 'selected'
            ? eligiblePhotos(order)
                .filter((p) =>
                  currentLines(order).some(
                    (l) =>
                      c.lineIds.includes(l.id) &&
                      ((l.product.kind === 'digital' && l.photo?.id === p.id) ||
                        (l.product.kind === 'bundle' && p.code.startsWith(l.childCode + '-')))
                  )
                )
                .map((p) => p.id)
            : [];
        const refund: Refund = {
          ...event,
          amount: refundAmount(order, c),
          allocations,
          status: 'pending',
          fileIds: c.action === 'fulfilment' || c.files === 'all' ? null : ids,
          hold: c.action === 'fulfilment' ? !productionStarted(order) : c.production === 'hold',
          history: [{ at: now, actorName: account.name, status: 'pending', reason: event.reason }]
        };
        s.refunds.push(refund);
      };
      if (c.action === 'contacts' || c.action === 'recover') {
        const before = currentBuyer(order),
          after = {
            ...before,
            email: c.email.trim().toLowerCase(),
            ...(c.action === 'contacts' ? { name: c.name.trim(), phone: '+' + phoneDigits(c.phone) } : {})
          };
        if (c.action === 'recover' && downloadAccess(order, now).state !== 'available')
          throw new Error('Повторная выдача недоступна: проверьте решение по оплате, возврат и исходный месячный срок.');
        if (JSON.stringify(before) === JSON.stringify(after) && c.action === 'contacts') throw new Error('Контакты не изменились.');
        if (JSON.stringify(before) !== JSON.stringify(after))
          s.corrections.push({
            ...event,
            kind: 'contacts',
            buyerBefore: before,
            buyerAfter: after,
            production: order.productionStatus,
            needsReprint: false
          });
        if (c.action === 'recover') {
          const status = c.result === 'confirmed' ? 'sent' : 'failed';
          s.recoveries.push({ ...event, email: after.email, previousEmail: before.email, status });
          order.buyerDelivery = {
            ...(order.buyerDelivery ?? { receipt: { channel: order.buyer.receiptChannel, status: 'sent', at: order.paidAt } }),
            filesEmail: { channel: 'email', status, at: now }
          };
        }
      } else if (c.action === 'line') {
        if (s.refunds.some((r) => r.status === 'pending')) throw new Error('Дождитесь результата возврата перед изменением позиций.');
        const before = currentLines(order).find((l) => l.id === c.lineId)!;
        if (s.refunds.some((r) => r.status === 'confirmed' && (r.allocations[c.lineId] ?? 0) > 0))
          throw new Error('По позиции уже подтверждён возврат. Её оплаченный контекст сохраняется.');
        const photo = before.photo?.id === c.photoId ? before.photo : correctionPhotos(order, c.lineId).find((p) => p.id === c.photoId);
        if (before.product.kind !== 'bundle' && !photo)
          throw new SaleValidationError({ photoId: 'Выберите кадр того же ребёнка из исходной группы.' });
        const quantity = Number(c.quantity),
          after = {
            ...before,
            quantity,
            photo: before.product.kind === 'bundle' ? null : photo!,
            photoId: before.product.kind === 'bundle' ? null : photo!.id,
            total: before.coveredByGift ? 0 : before.unitPrice * quantity
          };
        if (before.quantity === after.quantity && before.photoId === after.photoId) throw new Error('Позиция не изменилась.');
        const started = productionStarted(order),
          physical = before.product.kind !== 'digital';
        s.corrections.push({
          ...event,
          kind: 'line',
          before,
          after,
          production: order.productionStatus,
          needsReprint: physical && started
        });
        if (physical) {
          if (started) {
            s.needsReprint = true;
            s.reprintApproved = false;
          } else s.hold = true;
        }
      } else if (c.action === 'refund') addRefund();
      else if (c.action === 'result' || c.action === 'retry') {
        const refund = s.refunds.find((r) => r.id === c.refundId);
        if (!refund) throw new Error('Возврат недоступен.');
        if (c.action === 'retry') {
          if (refund.status !== 'failed') throw new Error('Повтор доступен только после подтверждённой ошибки.');
          if (refund.amount > remaining(order)) throw new Error('Остаток изменился. Повтор этого возврата невозможен.');
          const balances = allocateRefund(order, { ...c, action: 'refund', mode: 'lines', lineIds: Object.keys(refund.allocations) });
          if (Object.entries(refund.allocations).some(([id, amount]) => (balances[id] ?? 0) < amount))
            throw new Error('Остаток выбранных позиций изменился. Оформите новый возврат.');
          refund.status = 'pending';
        } else {
          if (refund.status !== 'pending') throw new Error('Результат уже определён. Обновите заказ.');
          refund.status = c.result;
          if (c.result === 'confirmed') {
            refund.holdApplied = refund.hold && !productionStarted(order);
            if (refund.holdApplied) s.hold = true;
          }
        }
        refund.history.push({ at: now, actorName: account.name, status: refund.status, reason: event.reason });
      } else if (c.action === 'fulfilment') {
        if (!order.latePayment && !s.hold && !s.needsReprint) throw new Error('Нет ожидающего решения об исполнении.');
        if (c.decision === 'fulfil') {
          if (financials([order]).pending || remaining(order) <= 0)
            throw new Error('Нельзя согласовать исполнение при полном возврате или неизвестном результате возврата.');
          s.hold = false;
          if (s.needsReprint) s.reprintApproved = true;
        } else {
          if (!order.latePayment) throw new Error('Для обычного заказа используйте форму возврата.');
          if (s.refunds.some((r) => r.status === 'pending')) throw new Error('Дождитесь результата уже начатого возврата.');
          addRefund();
          if (!productionStarted(order)) s.hold = true;
        }
        s.decisions.push({ ...event, decision: c.decision });
      }
      s.revision++;
      s.operations.push({ id: c.requestId, signature });
      saveOrder({ ...order, settlement: s });
    });
  await (navigator.locks ? navigator.locks.request('morefoto:organization:write', commit) : commit());
  if (readDemo('settlement:lose-response-once', false)) {
    writeDemo('settlement:lose-response-once', false);
    throw new Error('Ответ не получен. Повторите операцию: сохранённое действие не продублируется.');
  }
}
