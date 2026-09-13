import { readOrganization } from '../organization/repository';
import { getDemoNow } from '../mocks/clock';
import { calendarDays, currentGroupState, groupSentAt } from '../handoff/rules';
import type { OrganizationState } from '../organization/types';
import type { OrderPeriod } from './types';
export function orderPeriod(groupId: string, organization: OrganizationState = readOrganization(), now = getDemoNow()): OrderPeriod {
  const group = organization.groups.find((g) => g.id === groupId),
    institution = organization.institutions.find((i) => i.id === group?.institutionId);
  return {
    groupId,
    revision: group?.revision ?? 0,
    sentAt: group ? groupSentAt(group) : null,
    closesAt: group?.closesAt ?? null,
    deliveryAt: group?.closesAt ? calendarDays(group.closesAt, 7) : null,
    extensions: group?.extensions ?? [],
    curator: organization.users.find((u) => u.id === institution?.curatorId && u.active)?.name ?? 'MoreFoto',
    state: group ? currentGroupState(group, now) : 'preparing',
    now
  };
}
