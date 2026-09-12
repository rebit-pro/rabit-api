import type { ScopeSnapshot } from '../types';
import type { OrganizationState, StaffOption } from '../organization/types';
import type { DashboardData } from './types';
import { readPhotos } from '../photos/repository';
import { getCatalog } from '../commerce/mocks/catalog';
import { calendarDays, groupSentAt, preparationProblems, preparationSignature } from '../handoff/rules';
// This projection returns no photos, child codes, parent details or order references.
export function dashboardSnapshot(scope: ScopeSnapshot, organization: OrganizationState, actor: StaffOption, now: string): DashboardData {
  const photos = readPhotos(),
    allowed = new Set(scope.groups.map((g) => g.id));
  return {
    now,
    groups: Object.fromEntries(
      organization.groups
        .filter((g) => allowed.has(g.id))
        .map((g) => {
          const sentAt = groupSentAt(g),
            catalog = getCatalog(g.id);
          const ready =
            !sentAt &&
            !preparationProblems(g, photos, catalog).length &&
            g.preparation?.signature === preparationSignature(g, photos, catalog);
          return [
            g.id,
            {
              sentAt,
              deliveryAt: g.closesAt ? calendarDays(g.closesAt, 7) : null,
              linkState: sentAt ? 'sent' : ready ? 'ready' : 'checking'
            }
          ];
        })
    ),
    requests:
      actor.role === 'teacher'
        ? (photos.staffRequests ?? [])
            .filter(
              (r) =>
                r.createdBy === actor.id &&
                scope.institutions.some((i) => i.id === r.institutionId) &&
                scope.shoots.some((s) => s.id === r.shootId) &&
                r.rows.length > 0 &&
                r.rows.every((row) => allowed.has(row.groupId))
            )
            .map((r) => ({
              id: r.id,
              institutionId: r.institutionId,
              shootId: r.shootId,
              groupIds: [...new Set(r.rows.map((row) => row.groupId))],
              status: r.status,
              createdAt: r.createdAt
            }))
        : [],
    curators: Object.fromEntries(
      organization.institutions
        .filter((i) => scope.institutions.some((s) => s.id === i.id))
        .flatMap((i) => {
          const curator = organization.users.find((u) => u.id === i.curatorId && u.active && u.role === 'curator');
          return curator ? [[i.id, { name: curator.name, email: curator.email }]] : [];
        })
    )
  };
}
