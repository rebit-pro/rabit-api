import { simulateRequest } from '../mocks/runtime';
import { readPhotos } from '../photos/repository';
import { getCatalog } from '../commerce/mocks/catalog';
import { handoffAccess, canReadRequest } from './scope';
import { calendarDays, currentGroupState, groupSentAt, preparationProblems, preparationSignature } from './rules';
import type { HandoffWorkspace } from './types';
export async function loadHandoff(token: string): Promise<HandoffWorkspace> {
  await simulateRequest();
  const access = handoffAccess(token),
    { organization, scope, account, now } = access,
    photos = readPhotos();
  return {
    scope,
    role: account.role,
    now,
    photos: photos.photos.filter((p) => scope.groups.some((g) => g.id === p.groupId)),
    requests: (photos.staffRequests ?? []).filter((r) => canReadRequest(r, access)),
    groups: organization.groups
      .filter((g) => scope.groups.some((item) => item.id === g.id))
      .map((group) => {
        const catalog = getCatalog(group.id),
          signature = preparationSignature(group, photos, catalog),
          problems = preparationProblems(group, photos, catalog);
        const sentAt = groupSentAt(group),
          list = photos.photos.filter((p) => p.groupId === group.id);
        return {
          id: group.id,
          name: group.name,
          institutionId: group.institutionId,
          shootId: group.shootId,
          shootName: group.shootName,
          galleryToken: group.galleryToken,
          kind: group.kind,
          revision: group.revision,
          state: currentGroupState(group, now),
          sentAt,
          extensionClosesAt: group.extensions?.[group.extensions.length - 1]?.closesAt,
          closesAt: group.closesAt,
          deliveryAt: group.closesAt ? calendarDays(group.closesAt, 7) : null,
          signature,
          prepared: !!sentAt || (!problems.length && group.preparation?.signature === signature),
          problems,
          history: group.linkHistory ?? [],
          photoCount: list.length,
          childCount: new Set(list.map((p) => p.childCode).filter(Boolean)).size
        };
      })
  };
}
