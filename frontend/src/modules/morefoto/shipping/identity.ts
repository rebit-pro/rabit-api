import { currentLines } from '../settlement/rules.ts';
import type { SaleOrder } from '../settlement/types.js';
import type { PrintRow, ProductionState } from '../production/types.js';
export function rowSignature(rows: PrintRow[]): string {
  return JSON.stringify(rows.map((r) => [r.key, r.quantity, r.childCode]).sort((a, b) => String(a[0]).localeCompare(String(b[0]))));
}
export function orderSignature(order: SaleOrder): string {
  return JSON.stringify(
    currentLines(order)
      .filter((l) => l.product.kind === 'physical' && l.quantity > 0)
      .map((l) => [
        JSON.stringify([order.id, l.id, l.photoId, l.product.id, l.product.format || l.product.name, l.product.printCount]),
        l.quantity,
        l.childCode
      ])
      .sort((a, b) => String(a[0]).localeCompare(String(b[0])))
  );
}
export function lastTransfer(state: ProductionState, orderId: string) {
  for (const transfer of [...(state.transfers ?? [])].reverse()) {
    const rows = transfer.groups.flatMap((g) => g.rows).filter((r) => r.orderId === orderId);
    if (rows.length) return { transfer, rows };
  }
  return null;
}
export function pendingDelivery(rows: PrintRow[], state: ProductionState): PrintRow[] {
  const pending = new Set(
    [...new Set(rows.map((r) => r.orderId))].filter((id) => {
      const last = lastTransfer(state, id);
      return !last || rowSignature(last.rows) !== rowSignature(rows.filter((r) => r.orderId === id));
    })
  );
  return rows.filter((r) => pending.has(r.orderId));
}
