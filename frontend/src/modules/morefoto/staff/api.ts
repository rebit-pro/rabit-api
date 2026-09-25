import { isAxiosError } from 'axios';
import api from '@/api/http';
import type {
  AssignmentOptions,
  StaffDetail,
  StaffDraft,
  StaffFilters,
  StaffInvitation,
  StaffMutationResult,
  StaffPage,
  StaffSort
} from './model';

export const staffApi = {
  async list(filters: StaffFilters, page: number, pageSize: number, sort: StaffSort): Promise<StaffPage> {
    const result = await api.get<{ data: { items: StaffPage['items'] }; meta: StaffPage['meta'] }>('/api/v1/users', {
      params: {
        page,
        pageSize,
        sort: sort.key,
        direction: sort.direction,
        ...(filters.q ? { q: filters.q } : {}),
        ...(filters.role ? { role: filters.role } : {}),
        ...(filters.accountStatus ? { accountStatus: filters.accountStatus } : {})
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
  /** Removes the staff member from the cabinet; an already removed one counts as done. */
  async remove(id: number): Promise<void> {
    try {
      await api.delete('/api/v1/users/' + id);
    } catch (cause) {
      if (!isAxiosError(cause) || cause.response?.status !== 404) throw cause;
    }
  },
  async resendInvitation(id: number): Promise<StaffInvitation> {
    return (await api.post<StaffInvitation>('/api/v1/users/' + id + '/invitations')).data;
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
  if (code === 'LAST_ORGANIZER') return 'Нельзя удалить, отключить или понизить последнего активного организатора.';
  if (code === 'CANNOT_ARCHIVE_SELF') return 'Свою учётку удалить нельзя.';
  if (code === 'ASSIGNMENT_OCCUPIED') return 'На выбранных местах уже есть ответственные. Подтвердите замену и укажите причину.';
  if (code === 'ASSIGNMENTS_CHANGED' || code === 'STAFF_VERSION_CONFLICT')
    return 'Данные изменились. Загрузите актуальную версию и повторите действие.';
  if (code === 'EMAIL_OCCUPIED') return 'Этот email уже связан с другим сотрудником.';
  if (code === 'RATE_LIMITED') return 'Приглашение только что отправлено. Повторить можно через минуту.';
  if (code === 'INVITATION_NOT_AVAILABLE') return 'Приглашение не нужно: сотрудник уже задал пароль или его доступ отключён.';
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
