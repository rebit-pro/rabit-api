import type { ScopeSnapshot, Group } from '../types.js';
import type { SaleOrder, FinancialTotals } from '../settlement/types.js';
import type { DashboardFilters, RequestSummary } from './types.js';
import { financials } from '../settlement/rules.ts';
export function groupFinancials(scope: ScopeSnapshot, orders: SaleOrder[]): Record<string, FinancialTotals> {
  return Object.fromEntries(scope.groups.map((g) => [g.id, financials(orders.filter((o) => o.groupId === g.id))]));
}
export function sumGroups(groups: Group[], totals: Record<string, FinancialTotals>): FinancialTotals {
  return [...new Set(groups.map((g) => g.id))].reduce(
    (sum, id) => {
      const row = totals[id];
      if (row) for (const key of ['paidCount', 'paid', 'refunded', 'pending', 'net'] as const) sum[key] += row[key];
      return sum;
    },
    { paidCount: 0, paid: 0, refunded: 0, pending: 0, net: 0 }
  );
}
export function filterGroups(groups: Group[], filters: DashboardFilters): Group[] {
  const q = filters.q.trim().toLocaleLowerCase('ru');
  return groups.filter(
    (g) =>
      (!filters.shoot || g.shootId === filters.shoot) &&
      (!filters.state || g.state === filters.state) &&
      (!q || g.name.toLocaleLowerCase('ru').includes(q))
  );
}
export function visibleRequests(requests: RequestSummary[], groups: Group[], shoot = ''): RequestSummary[] {
  const ids = new Set(groups.map((g) => g.id));
  return requests
    .filter((r) => (!shoot || r.shootId === shoot) && r.groupIds.some((id) => ids.has(id)))
    .slice()
    .sort((a, b) => Number(b.status === 'clarification') - Number(a.status === 'clarification') || b.createdAt.localeCompare(a.createdAt));
}
