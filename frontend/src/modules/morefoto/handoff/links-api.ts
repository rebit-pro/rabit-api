import { isAxiosError } from 'axios';
import api from '@/api/http';
import { serverMoment } from './rules';
import { linkErrorText } from './link-errors';
import type { LinkCommand, LinkEvent, LinkGroup } from './types';

export interface LiveLinkItem {
  groupId: string;
  name: string;
  kind: 'regular' | 'staff';
  institutionId: string;
  institutionName: string;
  shootId: string;
  shootName: string;
  revision: number;
  signature: string;
  prepared: boolean;
  problems: string[];
  state: LinkGroup['state'];
  timezone: string;
  sentAt: string | null;
  closesAt: string | null;
  deliveryAt: string | null;
  photoCount: number;
  childCount: number;
  /** Curator of the group's institution, whom the teacher asks about the group (INF-11); only in the list. */
  curatorName?: string | null;
}
interface LiveLinkEvent {
  kind: LinkEvent['kind'];
  actorId: number;
  actorName: string;
  at: string;
  sentAt: string | null;
  closesAt: string | null;
  deliveryAt: string | null;
  previousSentAt: string | null;
  previousClosesAt: string | null;
  previousDeliveryAt: string | null;
  reason: string | null;
}
export interface LiveLinkDetail extends LiveLinkItem {
  galleryToken: string | null;
  referenceNow: string;
  history: LiveLinkEvent[];
}
/** U5: counters of the whole visible scope, independent of the state filter and paging. */
export interface LinkCounters {
  referenceNow: string;
  byState: { preparing: number; open: number; closed: number };
  closingSoon: number;
  prepared: number;
}
export interface LinkPage {
  items: LiveLinkItem[];
  meta: { page: number; pageSize: number; total: number; totalPages: number; summary?: LinkCounters };
}
const key = (value: string) => value.replace(/-/g, '');
const path = (groupId: string) => '/api/v1/groups/' + encodeURIComponent(groupId);

export const linksApi = {
  async list(page = 1, filters: { state?: LiveLinkItem['state']; pageSize?: number } = {}): Promise<LinkPage> {
    const response = await api.get<{ data: { items: LiveLinkItem[] }; meta: LinkPage['meta'] }>('/api/v1/group-links', {
      params: { page, pageSize: filters.pageSize ?? 100, ...(filters.state ? { state: filters.state } : {}) },
      unwrapEnvelope: false
    });
    return { items: response.data.data.items, meta: response.data.meta };
  },
  async detail(groupId: string): Promise<LiveLinkDetail> {
    return (await api.get<LiveLinkDetail>(path(groupId) + '/link')).data;
  },
  async save(command: LinkCommand): Promise<void> {
    const config = { headers: { 'Idempotency-Key': key(command.requestId) } };
    const base = { revision: command.revision, signature: command.signature };
    if (command.action === 'prepare') {
      await api.post(
        path(command.groupId) + '/link-preparations',
        {
          ...base,
          photosReviewed: command.photosReviewed,
          conditionsReviewed: command.conditionsReviewed,
          staffReviewed: command.staffReviewed,
          confirmed: true
        },
        config
      );
    } else if (command.action === 'transmit') {
      await api.post(
        path(command.groupId) + '/link-transmissions',
        { ...base, sentAt: serverMoment(command.sentAt), confirmed: command.confirmed },
        config
      );
    } else {
      await api.post(
        path(command.groupId) + '/link-date-corrections',
        { ...base, sentAt: serverMoment(command.sentAt), confirmed: command.confirmed, reason: command.reason.trim() },
        config
      );
    }
  }
};

export function toLinkGroup(item: LiveLinkItem, detail?: LiveLinkDetail): LinkGroup {
  return {
    id: item.groupId,
    name: item.name,
    institutionId: item.institutionId,
    shootId: item.shootId,
    shootName: item.shootName,
    galleryToken: detail?.galleryToken ?? '',
    kind: item.kind,
    revision: item.revision,
    state: item.state,
    sentAt: item.sentAt,
    closesAt: item.closesAt,
    deliveryAt: item.deliveryAt,
    signature: item.signature,
    prepared: item.prepared,
    problems: item.problems,
    history: (detail?.history ?? []).map((event) => ({
      kind: event.kind,
      actorId: event.actorId,
      actorName: event.actorName,
      at: event.at,
      sentAt: event.sentAt ?? undefined,
      closesAt: event.closesAt ?? undefined,
      previousSentAt: event.previousSentAt ?? undefined,
      previousClosesAt: event.previousClosesAt ?? undefined,
      reason: event.reason ?? undefined
    })),
    photoCount: item.photoCount,
    childCount: item.childCount
  };
}

export function linkError(cause: unknown): string {
  if (!isAxiosError(cause)) return cause instanceof Error ? cause.message : 'Не удалось сохранить ссылку.';
  return linkErrorText({
    network: !cause.response,
    status: cause.response?.status,
    code: (cause.response?.data as { error?: { code?: string } } | undefined)?.error?.code
  });
}
