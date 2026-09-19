import { isAxiosError } from 'axios';
import api from '@/api/http';
import type { AssignmentOptions, StaffDetail, StaffDraft, StaffFilters, StaffMutationResult, StaffPage } from './model';

export const staffApi = {
  async list(filters: StaffFilters, page = 1): Promise<StaffPage> {
    const result = await api.get<{ data: { items: StaffPage['items'] }; meta: StaffPage['meta'] }>('/api/v1/users', {
      params: {
        page,
        pageSize: 25,
        ...(filters.q ? { q: filters.q } : {}),
        ...(filters.role ? { role: filters.role } : {}),
        ...(filters.active === null ? {} : { active: filters.active })
      },
      unwrapEnvelope: false
    });
    return { items: result.data.data.items, meta: result.data.meta };
  },
  async detail(id: number): Promise<StaffDetail> {
    return (await api.get<StaffDetail>('/api/v1/users/' + id)).data;
  },
  async options(): Promise<AssignmentOptions> {
    return (await api.get<AssignmentOptions>('/api/v1/users/assignment-options')).data;
  },
  async save(draft: StaffDraft): Promise<StaffMutationResult> {
    const body = {
      name: draft.name.trim(),
      email: draft.email.trim(),
      role: draft.role,
      active: draft.active,
      institutionIds: draft.institutionIds,
      groupIds: draft.groupIds,
      replaceAssignments: draft.replaceAssignments,
      assignmentSignature: draft.assignmentSignature,
      ...(draft.reason.trim() ? { reason: draft.reason.trim() } : {}),
      ...(draft.id === null ? {} : { revision: draft.revision })
    };
    const config = { headers: { 'Idempotency-Key': draft.requestId } };
    return (await (draft.id === null ? api.post('/api/v1/users', body, config) : api.patch('/api/v1/users/' + draft.id, body, config)))
      .data;
  }
};
export function staffError(cause: unknown): string {
  if (!isAxiosError(cause)) return cause instanceof Error ? cause.message : 'Не удалось выполнить запрос.';
  const code = (cause.response?.data as { error?: { code?: string } } | undefined)?.error?.code;
  if (code === 'LAST_ORGANIZER') return 'Нельзя отключить или понизить последнего активного организатора.';
  if (code === 'ASSIGNMENT_OCCUPIED') return 'На выбранных местах уже есть ответственные. Подтвердите замену и укажите причину.';
  if (code === 'ASSIGNMENTS_CHANGED' || code === 'STAFF_VERSION_CONFLICT')
    return 'Данные изменились. Загрузите актуальную версию и повторите действие.';
  if (code === 'EMAIL_OCCUPIED') return 'Этот email уже связан с другим сотрудником.';
  switch (cause.response?.status) {
    case 401:
      return 'Сессия завершена. Войдите снова.';
    case 403:
      return 'Недостаточно прав для управления сотрудниками.';
    case 404:
      return 'Сотрудник не найден.';
    case 422:
      return 'Проверьте имя, email, роль и область доступа.';
    default:
      return 'Сервер не ответил. Проверьте соединение и повторите запрос.';
  }
}
