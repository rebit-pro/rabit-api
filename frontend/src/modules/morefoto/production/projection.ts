import type { OrderSnapshot } from '../orders/types.js';
import type { ProductionState } from './types.js';
import { lastTransfer, orderSignature, rowSignature } from '../shipping/identity.ts';
import { settlement, financials } from '../settlement/rules.ts';
// Ready and transferred are projections of the same atomic production ledger.
export function projectProduction(orders: OrderSnapshot[], state: ProductionState): OrderSnapshot[] {
  return orders.map((order) => {
    const jobs = state.jobs.filter((j) => j.versions.some((v) => v.plan.rows.some((r) => r.orderId === order.id)));
    if (!jobs.length) return order;
    const result = { ...order };
    delete result.physicalDelivery;
    const signature = orderSignature(order),
      last = lastTransfer(state, order.id);
    if (last && rowSignature(last.rows) === signature)
      return {
        ...result,
        productionStatus: 'delivered',
        physicalDelivery: { transferredAt: last.transfer.at, number: last.transfer.number }
      };
    const s = settlement(order),
      ready = jobs
        .map((j) => j.versions.slice(-1)[0])
        .find(
          (v) =>
            v?.ready &&
            v.plan.rows.some((r) => r.orderId === order.id) &&
            rowSignature(v.plan.rows.filter((r) => r.orderId === order.id)) === signature
        );
    if (
      ready?.ready &&
      order.paymentStatus === 'paid' &&
      financials([order]).net > 0 &&
      !s.hold &&
      !s.refunds.some((r) => r.status === 'pending') &&
      (!s.needsReprint || s.reprintApproved)
    )
      return { ...result, productionStatus: 'ready', physicalDelivery: { readyAt: ready.ready.at } };
    // Preserve historical R14/legacy terminal states if R15 has never managed this order.
    if (
      ['ready', 'delivered'].includes(order.productionStatus) &&
      !jobs.some((j) => j.versions.some((v) => v.ready)) &&
      !last &&
      !order.physicalDelivery
    )
      return order;
    const printing = jobs.some((j) => j.versions.some((v) => v.startedAt && v.plan.rows.some((r) => r.orderId === order.id)));
    const queued = jobs.some((j) => j.versions.slice(-1)[0]?.plan.rows.some((r) => r.orderId === order.id));
    return { ...result, productionStatus: printing ? 'printing' : queued ? 'queued' : 'not-started' };
  });
}
