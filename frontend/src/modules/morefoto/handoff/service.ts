import { isMockApiEnabled } from '@/mocks/config';
import { simulateRequest } from '../mocks/runtime';
import { readPhotos } from '../photos/repository';
import { getCatalog } from '../commerce/mocks/catalog';
import { handoffAccess, canReadRequest } from './scope';
import { calendarDays, currentGroupState, groupSentAt, preparationProblems, preparationSignature } from './rules';
import { isAxiosError } from 'axios';
import { staffRequestsApi, type StaffRequestPage } from './api';
import { linksApi, toLinkGroup } from './links-api';
import type { StaffRole } from '../types';
import type { HandoffWorkspace, LinkGroup, StaffRequest } from './types';
/** One page of the live staff request list: server filters and a bounded page size (#26). */
export interface StaffRequestQuery {
  page: number;
  status: StaffRequest['status'] | null;
  shootId: string | null;
}
export const STAFF_REQUEST_PAGE_SIZE = 20;
const FIRST_PAGE: StaffRequestQuery = { page: 1, status: null, shootId: null };

export async function loadHandoff(token: string, requestId?: string, query: StaffRequestQuery = FIRST_PAGE): Promise<HandoffWorkspace> {
  if (!isMockApiEnabled) return loadLiveHandoff(requestId, query);
  await simulateRequest();
  const access = handoffAccess(token),
    { organization, scope, account, now } = access,
    photos = readPhotos();
  return {
    scope,
    role: account.role,
    now,
    photos: photos.photos.filter((p) => scope.groups.some((g) => g.id === p.groupId)),
    // Newest first, as the live list is ordered by the server.
    requests: (photos.staffRequests ?? []).filter((r) => canReadRequest(r, access)).reverse(),
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

async function loadLiveHandoff(requestId: string | undefined, query: StaffRequestQuery): Promise<HandoffWorkspace> {
  // The card reads HND-08 and only the scope from a one-item HND-06 page; other pages stay on the server.
  if (requestId) {
    const [scope, detail] = await Promise.all([staffRequestsApi.list(1, 1), staffRequestsApi.detail(requestId).catch(missing)]);
    return liveWorkspace(scope, detail ? [detail] : []);
  }
  const page = await staffRequestsApi.list(query.page, STAFF_REQUEST_PAGE_SIZE, { status: query.status, shootId: query.shootId });
  return {
    ...liveWorkspace(page, page.items),
    requestPage: { page: page.meta.page, totalPages: page.meta.totalPages, total: page.meta.total }
  };
}

/** A request outside the actor's scope answers 404: the screen says it is unavailable instead of failing the load. */
function missing(cause: unknown): null {
  if (isAxiosError(cause) && cause.response?.status === 404) return null;
  throw cause;
}

function liveWorkspace(first: StaffRequestPage, requests: StaffRequest[]): HandoffWorkspace {
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
    requests,
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
