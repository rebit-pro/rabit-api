import api from '@/api/http';
import { loadStorefront } from '../../commerce/services/storefront';
import { calendarDays, groupSentAt } from '../../handoff/rules';
import { formatMoment } from '../../handoff/display';
import { getDemoNow } from '../../mocks/clock';
import { isMockApiEnabled } from '@/mocks/config';
import { readOrganization, getStaffOptions } from '../../organization/repository';
import { simulateRequest } from '../../mocks/runtime';
import { groupChildren } from '../../photos/repository';
import { getGalleryScenario } from '../mocks/state';
import type { GallerySnapshot } from '../types';

export class GalleryUnavailableError extends Error {}

export function resolveDemoGallery(token: string): GallerySnapshot {
  if (!isMockApiEnabled) throw new Error('Галерея пока недоступна. Попробуйте открыть ссылку позже.');
  const organization = readOrganization();
  const group = organization.groups.find((item) => item.galleryToken === token);
  const institution = organization.institutions.find((item) => item.id === group?.institutionId);
  if (!group || !institution) throw new GalleryUnavailableError('Ссылка недействительна');
  const scenario = getGalleryScenario();
  const baseState = scenario === 'preparing' || scenario === 'closed' ? scenario : group.state;
  const referenceNow = scenario === 'closed' ? '2026-09-13T12:00:00+03:00' : getDemoNow();
  const closesAt = baseState === 'preparing' ? null : (group.closesAt ?? '2026-09-12T18:00:00+03:00');
  const state = baseState === 'open' && closesAt && Date.parse(referenceNow) >= Date.parse(closesAt) ? 'closed' : baseState;
  return {
    groupId: group.id,
    institutionName: institution.name,
    groupName: group.name,
    shootName: group.shootName,
    audience: group.kind,
    state,
    sentAt: state === 'preparing' ? null : groupSentAt(group),
    closesAt,
    referenceNow,
    delivery:
      group.sentAt && closesAt
        ? 'Фотографии передадим в учреждение до ' + formatMoment(calendarDays(closesAt, 7)) + '.'
        : 'Фотографии передадим в учреждение после завершения приёма заказов.',
    curator: getStaffOptions().find((item) => item.id === institution.curatorId)?.name ?? 'Куратор MoreFoto',
    children: state === 'preparing' || scenario === 'empty' ? [] : groupChildren(group.id)
  };
}

export async function loadGallery(token: string): Promise<GallerySnapshot> {
  if (isMockApiEnabled) {
    await simulateRequest();
    return resolveDemoGallery(token);
  }
  try {
    const { data } = await api.get<GallerySnapshot>('/api/v1/public/galleries/' + encodeURIComponent(token));
    await loadStorefront(token, data);
    return data;
  } catch (cause) {
    if (cause && typeof cause === 'object' && 'response' in cause && (cause.response as { status?: number })?.status === 404)
      throw new GalleryUnavailableError('Ссылка недействительна');
    throw new Error('Не удалось загрузить галерею. Попробуйте ещё раз.');
  }
}
