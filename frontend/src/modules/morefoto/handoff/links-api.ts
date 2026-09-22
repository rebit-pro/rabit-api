import { isAxiosError } from 'axios';
import api from '@/api/http';
import { serverMoment } from './rules';
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
interface LinkPage {
  items: LiveLinkItem[];
  meta: { page: number; pageSize: number; total: number; totalPages: number };
}
const key = (value: string) => value.replace(/-/g, '');
const path = (groupId: string) => '/api/v1/groups/' + encodeURIComponent(groupId);

export const linksApi = {
  async list(page = 1): Promise<LinkPage> {
    const response = await api.get<{ data: { items: LiveLinkItem[] }; meta: LinkPage['meta'] }>('/api/v1/group-links', {
      params: { page, pageSize: 100 },
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

const messages: Record<string, string> = {
  REVISION_CONFLICT: 'Ссылку уже изменили. Обновите страницу и повторите действие.',
  SIGNATURE_CONFLICT: 'Фотографии, условия или списки изменились после открытия формы. Обновите страницу и проверьте заново.',
  LINK_NOT_READY: 'Группа ещё не готова: устраните проблемы, указанные в карточке.',
  LINK_NOT_PREPARED: 'Подборка или условия изменились. Организатор должен проверить ссылку перед передачей.',
  LINK_ALREADY_SENT: 'Приём уже запускался. Дату можно исправить отдельно.',
  LINK_NOT_SENT: 'Передача ссылки ещё не отмечена.',
  SENT_AT_IN_FUTURE: 'Дата передачи не может быть позже текущего времени.',
  SENT_AT_BEFORE_LINK: 'Ссылку не могли передать раньше, чем её выдали после проверки.',
  SENT_AT_UNCHANGED: 'Новая дата совпадает с записанной.',
  IDEMPOTENCY_CONFLICT: 'Эта попытка уже использована с другими данными. Закройте форму и откройте её снова.',
  GROUP_NOT_FOUND: 'Группа больше не доступна в вашей области.',
  FORBIDDEN: 'Для этого действия недостаточно прав.'
};

export function linkError(cause: unknown): string {
  if (!isAxiosError(cause)) return cause instanceof Error ? cause.message : 'Не удалось сохранить ссылку.';
  const code = (cause.response?.data as { error?: { code?: string } } | undefined)?.error?.code ?? '';
  if (messages[code]) return messages[code];
  if (cause.response?.status === 403) return messages.FORBIDDEN!;
  if (cause.response?.status === 404) return messages.GROUP_NOT_FOUND!;
  if (cause.response?.status === 422) return 'Проверьте заполненные поля формы.';
  return 'Сервер не сохранил изменение. Проверьте соединение и повторите действие.';
}
