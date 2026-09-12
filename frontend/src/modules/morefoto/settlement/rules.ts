import type { CartQuoteLine } from '../commerce/types.js';
import type { GalleryPhoto } from '../gallery/types.js';
import type { SaleOrder, SaleCommand, SaleErrors, SettlementState, FinancialTotals } from './types.js';
export function settlement(order: SaleOrder): SettlementState {
  return (
    order.settlement ?? {
      revision: 0,
      operations: [],
      corrections: [],
      refunds: [],
      decisions: [],
      recoveries: [],
      preparedFiles: [],
      hold: false,
      needsReprint: false
    }
  );
}
export function saleVersion(order: SaleOrder): string {
  return JSON.stringify([settlement(order).revision, order.paymentStatus, order.paidAt, order.productionStatus]);
}
export function currentBuyer(order: Pick<SaleOrder, 'buyer' | 'settlement'>) {
  let buyer = order.buyer;
  for (const c of order.settlement?.corrections ?? []) if (c.buyerAfter) buyer = c.buyerAfter;
  return buyer;
}
export function currentLines(order: SaleOrder): CartQuoteLine[] {
  const changed = settlement(order).corrections.filter((c) => c.kind === 'line');
  return order.quote.lines.map((line) => changed.filter((c) => c.after?.id === line.id).slice(-1)[0]?.after ?? line);
}
export function eligiblePhotos(order: SaleOrder): GalleryPhoto[] {
  const changes = settlement(order).corrections.filter((c) => c.kind === 'line');
  if (!changes.length) return order.digitalPhotos;
  const photos = [
    ...order.digitalPhotos,
    ...changes.flatMap((c) => (c.after?.photo && c.after.product.kind === 'digital' ? [c.after.photo] : []))
  ];
  const lines = currentLines(order);
  return photos
    .filter((p, i) => photos.findIndex((v) => v.id === p.id) === i)
    .filter(
      (p) =>
        order.quote.gifts.some((code) => p.code.startsWith(code + '-')) ||
        lines.some(
          (l) =>
            l.quantity > 0 &&
            ((l.product.kind === 'digital' && l.photo?.id === p.id) ||
              (l.product.kind === 'bundle' && p.code.startsWith(l.childCode + '-')))
        )
    );
}
export function availablePhotos(order: SaleOrder): GalleryPhoto[] {
  const refunds = settlement(order).refunds.filter((r) => r.status === 'confirmed');
  if (refunds.some((r) => r.fileIds === null)) return [];
  const blocked = new Set(refunds.flatMap((r) => r.fileIds ?? []));
  return eligiblePhotos(order).filter((p) => !blocked.has(p.id));
}
export function lateDecision(order: SaleOrder) {
  return settlement(order).decisions.slice(-1)[0]?.decision;
}
export function financials(orders: SaleOrder[]): FinancialTotals {
  return orders.reduce(
    (total, o) => {
      if (o.paymentStatus !== 'paid') return total;
      const r = settlement(o).refunds;
      const refunded = r.filter((v) => v.status === 'confirmed').reduce((n, v) => n + v.amount, 0),
        pending = r.filter((v) => v.status === 'pending').reduce((n, v) => n + v.amount, 0);
      return {
        paidCount: total.paidCount + 1,
        paid: total.paid + o.quote.total,
        refunded: total.refunded + refunded,
        pending: total.pending + pending,
        net: total.net + o.quote.total - refunded
      };
    },
    { paidCount: 0, paid: 0, refunded: 0, pending: 0, net: 0 }
  );
}
export function remaining(order: SaleOrder) {
  const totals = financials([order]);
  return Math.max(0, totals.paid - totals.refunded - totals.pending);
}
export function lineBalances(order: SaleOrder) {
  const active = settlement(order).refunds.filter((r) => r.status !== 'failed');
  return Object.fromEntries(
    order.quote.lines.map((l) => [l.id, Math.max(0, l.total - active.reduce((n, r) => n + (r.allocations[l.id] ?? 0), 0))])
  );
}
export function parseMoney(value: string): number | null {
  if (!/^\d+(?:[.,]\d{1,2})?$/.test(value.trim())) return null;
  const [whole, part = ''] = value.trim().replace(',', '.').split('.');
  const amount = Number(whole) * 100 + Number(part.padEnd(2, '0'));
  return Number.isSafeInteger(amount) && amount > 0 ? amount : null;
}
export function refundAmount(order: SaleOrder, c: SaleCommand) {
  if (c.action === 'fulfilment') return remaining(order);
  const balances = lineBalances(order);
  return c.mode === 'lines' ? [...new Set(c.lineIds)].reduce((n, id) => n + (balances[id] ?? 0), 0) : (parseMoney(c.amount) ?? 0);
}
export function allocateRefund(order: SaleOrder, c: SaleCommand): Record<string, number> {
  const balances = lineBalances(order);
  let left = refundAmount(order, c);
  const allocations: Record<string, number> = {};
  for (const line of order.quote.lines) {
    if (c.action !== 'fulfilment' && c.mode === 'lines' && !c.lineIds.includes(line.id)) continue;
    const take = Math.min(left, balances[line.id] ?? 0);
    if (take) {
      allocations[line.id] = take;
      left -= take;
    }
  }
  if (left) throw new Error('Остаток позиций не совпадает с суммой заказа. Нужна проверка.');
  return allocations;
}
export const productionStarted = (o: SaleOrder) => ['printing', 'ready', 'delivered'].includes(o.productionStatus);
export function saleErrors(order: SaleOrder, c: SaleCommand): SaleErrors {
  const e: SaleErrors = {};
  if (c.reason.trim().length < 5 || c.reason.trim().length > 500) e.reason = 'Укажите причину: от 5 до 500 символов.';
  if (!c.confirmed) e.confirmed = 'Подтвердите последствия операции.';
  if (['contacts', 'recover'].includes(c.action)) {
    if (c.email.trim().length > 254 || !/^([^\s@]+)@([^\s@]+)\.([^\s@]+)$/.test(c.email.trim()))
      e.email = 'Укажите email в формате name@example.ru.';
    if (!c.verified) e.verified = 'Подтвердите проверку заказа и контакта по обращению родителя.';
  }
  if (c.action === 'contacts') {
    if (c.name.trim().length < 2 || c.name.trim().length > 100) e.name = 'Имя: от 2 до 100 символов.';
    if (!/^[+\d\s().-]+$/.test(c.phone) || c.phone.replace(/\D/g, '').length < 10 || c.phone.replace(/\D/g, '').length > 15)
      e.phone = 'Укажите телефон: от 10 до 15 цифр.';
  }
  if (c.action === 'line') {
    const line = currentLines(order).find((l) => l.id === c.lineId);
    const quantity = Number(c.quantity);
    if (!line) e.lineId = 'Выберите позицию заказа.';
    else if (!/^\d+$/.test(c.quantity) || !Number.isSafeInteger(quantity) || quantity < 0 || quantity > line.quantity)
      e.quantity = 'Количество от 0 до текущего. Увеличение оформляется отдельным заказом.';
  }
  if (c.action === 'refund' || (c.action === 'fulfilment' && c.decision === 'refund')) {
    const amount = refundAmount(order, c);
    if (!amount || amount > remaining(order)) e.amount = 'Сумма должна быть больше нуля и не превышать доступный остаток.';
    if (c.action === 'refund' && !['amount', 'lines'].includes(c.mode)) e.mode = 'Выберите сумму или позиции.';
    if (c.action === 'refund' && c.mode === 'lines' && (!c.lineIds.length || c.lineIds.some((id) => !((lineBalances(order)[id] ?? 0) > 0))))
      e.lineIds = 'Выберите позиции с доступным остатком.';
    if (c.action === 'refund' && (!['keep', 'selected', 'all'].includes(c.files) || (c.files === 'selected' && c.mode !== 'lines')))
      e.files = 'Выберите влияние на файлы; выбранные файлы доступны только при возврате позиций.';
    if (c.action === 'refund' && (!['keep', 'hold'].includes(c.production) || (c.production === 'hold' && productionStarted(order))))
      e.production = 'Печать уже начата: требуется отдельное согласование, статус сохраняется.';
  }
  if (['result', 'recover'].includes(c.action) && !['confirmed', 'failed'].includes(c.result))
    e.result = 'Выберите результат демонстрации.';
  if (c.action === 'fulfilment' && !['fulfil', 'refund'].includes(c.decision)) e.decision = 'Выберите решение.';
  return e;
}
export function settlementLabel(order: SaleOrder): string {
  const f = financials([order]);
  if (f.pending) return 'Возврат обрабатывается';
  if (f.refunded === f.paid && f.paid > 0) return 'Полный возврат';
  if (f.refunded) return 'Частичный возврат';
  if (order.latePayment)
    return lateDecision(order) === 'fulfil'
      ? 'Поздняя: исполнить'
      : lateDecision(order) === 'refund'
        ? 'Поздняя: вернуть'
        : 'Поздняя: нужна проверка';
  return 'Без возврата';
}
