import { isMockApiEnabled } from '@/mocks/config';
import { simulateRequest } from '../mocks/runtime';
import { readPhotos } from '../photos/repository';
import { getCatalog } from '../commerce/mocks/catalog';
import { handoffAccess, canReadRequest } from './scope';
import { calendarDays, currentGroupState, groupSentAt, preparationProblems, preparationSignature } from './rules';
import { staffRequestsApi } from './api';
import { linksApi, toLinkGroup } from './links-api';
import type { StaffRole } from '../types';
import type { HandoffWorkspace, LinkGroup } from './types';
export async function loadHandoff(token: string, requestId?: string): Promise<HandoffWorkspace> {
  if (!isMockApiEnabled) return loadLiveHandoff(requestId);
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

async function loadLiveHandoff(requestId?: string): Promise<HandoffWorkspace> {
  const first = await staffRequestsApi.list();
  const items = [...first.items];
  for (let page = 2; page <= first.meta.totalPages; page++) items.push(...(await staffRequestsApi.list(page)).items);
  if (requestId) {
    const detail = await staffRequestsApi.detail(requestId);
    const index = items.findIndex((item) => item.id === detail.id);
    if (index === -1) items.push(detail);
    else items[index] = detail;
  }
  const groups = first.scope.groups.map<LinkGroup>((group) => ({
    ...group,
    galleryToken: '',
    revision: 1,
    state: group.state === 'open' || group.state === 'closed' ? group.state : 'preparing',
    sentAt: null,
    closesAt: null,
    deliveryAt: null,
    signature: '',
    prepared: false,
    problems: [],
    history: [],
    photoCount: 0,
    childCount: 0
  }));
  return {
    role: first.scope.role,
    now: new Date().toISOString(),
    requestSummary: first.meta.summary?.byStatus,
    requests: items,
    photos: [],
    groups,
    scope: {
      institutions: first.scope.institutions.map((item) => ({ ...item, address: '' })),
      shoots: first.scope.shoots.map((item) => ({
        ...item,
        date: null,
        revision: 1
      })),
      groups
    }
  };
}

/** The links screen reads HND-01 in live mode; keys and history are requested per group only when needed. */
export async function loadLinks(token: string, role: StaffRole): Promise<HandoffWorkspace> {
  if (isMockApiEnabled) return loadHandoff(token);
  const first = await linksApi.list();
  const items = [...first.items];
  for (let page = 2; page <= first.meta.totalPages; page++) items.push(...(await linksApi.list(page)).items);
  const groups = items.map((item) => toLinkGroup(item));
  const institutions = new Map(
    items.map((item) => [item.institutionId, { id: item.institutionId, name: item.institutionName, address: '' }])
  );
  const shoots = new Map(
    items.map((item) => [
      item.shootId,
      { id: item.shootId, institutionId: item.institutionId, name: item.shootName, date: null, revision: 1 }
    ])
  );
  return {
    role,
    now: new Date().toISOString(),
    requests: [],
    photos: [],
    groups,
    scope: { institutions: [...institutions.values()], shoots: [...shoots.values()], groups }
  };
}
