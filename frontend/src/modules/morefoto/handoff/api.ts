import { isAxiosError } from 'axios';
import api from '@/api/http';
import type { StaffRole } from '../types';
import { staffTransferErrorText } from './rules';
import type { ServerTransferPreview, StaffCommand, StaffRequest } from './types';

interface StaffRequestScope {
  role: StaffRole;
  institutions: { id: string; name: string }[];
  shoots: { id: string; institutionId: string; name: string }[];
  groups: { id: string; institutionId: string; shootId: string; shootName: string; name: string; kind: 'regular'; state: string }[];
}
export interface StaffRequestPage {
  items: StaffRequest[];
  scope: StaffRequestScope;
  meta: {
    page: number;
    pageSize: number;
    total: number;
    totalPages: number;
    /** U5: every visible request without the status filter. */
    summary?: { byStatus: Record<'submitted' | 'clarification' | 'transferred', number> };
  };
}
export interface StaffRequestFilters {
  status?: StaffRequest['status'] | null;
  shootId?: string | null;
}
interface MutationResult {
  id: string;
  revision: number;
  status: StaffRequest['status'];
}
interface TransferResult extends MutationResult {
  results: NonNullable<StaffRequest['results']>;
}
const key = (value: string) => value.replace(/-/g, '');

export const staffRequestsApi = {
  /** HND-06 reads one page; the status and shoot filters are applied by the server. */
  async list(page = 1, pageSize = 100, filters: StaffRequestFilters = {}): Promise<StaffRequestPage> {
    const params = {
      page,
      pageSize,
      ...(filters.status ? { status: filters.status } : {}),
      ...(filters.shootId ? { shootId: filters.shootId } : {})
    };
    const response = await api.get<{ data: { items: StaffRequest[]; scope: StaffRequestScope }; meta: StaffRequestPage['meta'] }>(
      '/api/v1/staff-requests',
      { params, unwrapEnvelope: false }
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
  async transferPreview(id: string): Promise<ServerTransferPreview> {
    return (await api.get<ServerTransferPreview>('/api/v1/staff-requests/' + encodeURIComponent(id) + '/transfer-preview')).data;
  },
  async transfer(command: StaffCommand): Promise<TransferResult> {
    if (command.id === null || command.revision === null) throw new Error('Список для переноса не выбран.');
    return (
      await api.post<TransferResult>(
        '/api/v1/staff-requests/' + encodeURIComponent(command.id) + '/transfers',
        { reason: command.reason.trim(), confirmed: command.confirmed, revision: command.revision, signature: command.signature },
        { headers: { 'Idempotency-Key': key(command.requestId) } }
      )
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

export function staffRequestError(cause: unknown, action: StaffCommand['action'] = 'submit'): string {
  if (!isAxiosError(cause)) return cause instanceof Error ? cause.message : 'Не удалось сохранить список.';
  const error = (cause.response?.data as { error?: { code?: string; details?: { photoCodes?: string[] } } } | undefined)?.error;
  const code = error?.code;
  const transfer = action === 'confirm' ? staffTransferErrorText(code, error?.details?.photoCodes) : null;
  if (transfer) return transfer;
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
