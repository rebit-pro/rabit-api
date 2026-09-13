import { currentBuyer, currentLines, saleVersion, remaining } from './rules';
import type { SaleCommand, SaleOrder } from './types';
export function saleCommand(order: SaleOrder, action: SaleCommand['action'], refundId = '', supportId = ''): SaleCommand {
  const buyer = currentBuyer(order),
    line = currentLines(order).find((l) => l.quantity > 0);
  return {
    kind: 'settlement',
    action,
    requestId: crypto.randomUUID(),
    orderId: order.id,
    version: saleVersion(order),
    supportId,
    reason: '',
    confirmed: false,
    verified: false,
    name: buyer.name,
    email: buyer.email,
    phone: buyer.phone,
    lineId: line?.id ?? '',
    photoId: line?.photoId ?? '',
    quantity: String(line?.quantity ?? 0),
    mode: 'amount',
    amount: (remaining(order) / 100).toFixed(2),
    lineIds: [],
    files: 'keep',
    production: 'keep',
    refundId,
    result: 'confirmed',
    decision: 'fulfil'
  };
}
