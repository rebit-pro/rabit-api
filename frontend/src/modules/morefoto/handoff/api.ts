import { isAxiosError } from 'axios';
import api from '@/api/http';
import type { StaffRole } from '../types';
import type { StaffCommand, StaffRequest } from './types';

interface StaffRequestScope {
  role: StaffRole;
  institutions: { id: string; name: string }[];
  shoots: { id: string; institutionId: string; name: string }[];
  groups: { id: string; institutionId: string; shootId: string; shootName: string; name: string; kind: 'regular'; state: string }[];
}
export interface StaffRequestPage {
  items: StaffRequest[];
  scope: StaffRequestScope;
  meta: { page: number; pageSize: number; total: number; totalPages: number };
}
interface MutationResult {
  id: string;
  revision: number;
  status: StaffRequest['status'];
}
const key = (value: string) => value.replace(/-/g, '');

export const staffRequestsApi = {
  async list(page = 1): Promise<StaffRequestPage> {
    const response = await api.get<{ data: { items: StaffRequest[]; scope: StaffRequestScope }; meta: StaffRequestPage['meta'] }>(
      '/api/v1/staff-requests',
      { params: { page, pageSize: 100 }, unwrapEnvelope: false }
    );
    return { items: response.data.data.items, scope: response.data.data.scope, meta: response.data.meta };
  },
  async detail(id: string): Promise<StaffRequest> {
    return (await api.get<StaffRequest>('/api/v1/staff-requests/' + encodeURIComponent(id))).data;
  },
  async save(command: StaffCommand): Promise<MutationResult> {
    const body = {
      institutionId: command.institutionId,
      shootId: command.shootId,
      rows: command.rows,
      comment: command.comment.trim(),
      ...(command.id === null ? {} : { revision: command.revision })
    };
    const config = { headers: { 'Idempotency-Key': key(command.requestId) } };
    return (
      await (command.id === null
        ? api.post<MutationResult>('/api/v1/staff-requests', body, config)
        : api.put<MutationResult>('/api/v1/staff-requests/' + encodeURIComponent(command.id), body, config))
    ).data;
  },
  async clarify(command: StaffCommand): Promise<MutationResult> {
    if (command.id === null || command.revision === null) throw new Error('Список для уточнения не выбран.');
    return (
      await api.post<MutationResult>(
        '/api/v1/staff-requests/' + encodeURIComponent(command.id) + '/clarifications',
        { revision: command.revision, comment: command.reason.trim(), confirmed: command.confirmed },
        { headers: { 'Idempotency-Key': key(command.requestId) } }
      )
    ).data;
  }
};

export function staffRequestError(cause: unknown): string {
  if (!isAxiosError(cause)) return cause instanceof Error ? cause.message : 'Не удалось сохранить список.';
  const code = (cause.response?.data as { error?: { code?: string } } | undefined)?.error?.code;
  if (code === 'REVISION_CONFLICT') return 'Список уже изменён. Загрузите актуальную версию и повторите действие.';
  if (code === 'IDEMPOTENCY_CONFLICT') return 'Эта попытка уже использована с другими данными. Закройте форму и откройте её снова.';
  if (code === 'CHILD_ALREADY_PENDING') return 'Этот ребёнок уже есть в списке на проверке.';
  if (code === 'CHILD_NOT_FOUND') return 'Код ребёнка или снимка не найден в выбранной группе.';
  if (code === 'INVALID_ROW') return 'Проверьте код ребёнка или снимка: например, A или A001. У снимка ровно три цифры.';
  if (code === 'GROUP_NOT_FOUND' || cause.response?.status === 404) return 'Группа или список больше не доступны в вашей области.';
  if (code === 'CONFIRMATION_REQUIRED') return 'Подтвердите запрос уточнения.';
  if (cause.response?.status === 403) return 'Недостаточно прав для этого действия.';
  if (cause.response?.status === 422) return 'Проверьте заполненные поля списка.';
  return 'Сервер не сохранил список. Проверьте соединение и повторите действие.';
}
