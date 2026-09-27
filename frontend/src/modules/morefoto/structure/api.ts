import { isAxiosError } from 'axios';
import api from '@/api/http';
import type {
  Institution,
  Shoot,
  ShootDetail,
  StructureAttempt,
  StructurePage,
  StructureKind,
  StructureScope,
  PageMeta,
  InstitutionDetail,
  InstitutionPages
} from './model';
const resources: Record<StructureKind, string> = { institution: 'institutions', shoot: 'shoots', group: 'groups' };
export const structureApi = {
  async institution(institutionId: string, pages: InstitutionPages, pageSize = 25): Promise<InstitutionDetail> {
    const result = await api.get<InstitutionDetail>('/api/v1/institutions/' + encodeURIComponent(institutionId), {
      params: { ...pages, pageSize }
    });
    return result.data;
  },
  async list(scope: StructureScope, page = 1, pageSize = 25, q = ''): Promise<StructurePage> {
    if (scope.kind === 'group') {
      const result = await api.get<ShootDetail>('/api/v1/shoots/' + encodeURIComponent(scope.shootId ?? ''), {
        params: { page, pageSize }
      });
      if (result.data.institutionId !== scope.institutionId)
        throw new Error('Съёмка не относится к этому учреждению. Вернитесь к списку учреждений.');
      return {
        items: result.data.groups.items,
        meta: result.data.groups.meta,
        shoot: result.data
      };
    }
    const path =
      scope.kind === 'institution'
        ? '/api/v1/institutions'
        : '/api/v1/institutions/' + encodeURIComponent(scope.institutionId ?? '') + '/shoots';
    const result = await api.get<{
      data: { items: Institution[] | Shoot[] };
      meta: PageMeta;
    }>(path, {
      params: {
        page,
        pageSize,
        ...(scope.kind === 'institution' && q ? { q } : {})
      },
      unwrapEnvelope: false
    });
    return { items: result.data.data.items, meta: result.data.meta };
  },
  /** All shoots of the institution, for choosing the shoot of a group on the institution page. */
  async shoots(institutionId: string): Promise<Shoot[]> {
    const shoots: Shoot[] = [];
    for (let page = 1; ; page++) {
      const result = await structureApi.list({ kind: 'shoot', institutionId }, page, 100);
      shoots.push(...(result.items as Shoot[]));
      if (page >= result.meta.totalPages || !result.items.length) return shoots;
    }
  },
  /** Removes the record with everything that belongs to it; an already removed one counts as done (#92). */
  async remove(kind: StructureKind, id: string): Promise<void> {
    try {
      await api.delete('/api/v1/' + resources[kind] + '/' + encodeURIComponent(id));
    } catch (cause) {
      if (!isAxiosError(cause) || cause.response?.status !== 404) throw cause;
    }
  },
  async save(attempt: StructureAttempt): Promise<{ id: string; revision: number }> {
    const options = { headers: { 'Idempotency-Key': attempt.key } };
    return (
      await (attempt.method === 'POST' ? api.post(attempt.path, attempt.body, options) : api.patch(attempt.path, attempt.body, options))
    ).data;
  }
};
export function structureError(cause: unknown): string {
  if (!isAxiosError(cause)) return cause instanceof Error ? cause.message : 'Не удалось выполнить запрос.';
  switch (cause.response?.status) {
    case 401:
      return 'Сессия завершена. Войдите снова.';
    case 403:
      return 'Недостаточно прав для этого действия.';
    case 404:
      return 'Учреждение, съёмка или группа недоступны. Вернитесь к списку учреждений.';
    case 409:
      return 'Данные изменены в другой вкладке. Ваши изменения сохранены в черновике. Загрузите актуальные данные.';
    case 422:
      return 'Сервер отклонил данные. Проверьте заполненные поля.';
    default:
      return 'Не удалось получить ответ сервера. Проверьте соединение и повторите запрос.';
  }
}
/** Refusal of one removed record (#92 DEC-01): records with orders stay for the history of orders and payments. */
export function structureRemovalError(cause: unknown): string {
  const code = isAxiosError(cause) ? (cause.response?.data as { error?: { code?: string } } | undefined)?.error?.code : undefined;
  if (code === 'STRUCTURE_HAS_ORDERS') return 'По ней уже есть заказы — удалить нельзя, данные сохраняются для истории заказов и оплат.';
  if (code === 'PHOTO_PROCESSING') return 'Часть кадров ещё обрабатывается — повторите через минуту.';
  if (isAxiosError(cause) && cause.response?.status === 409) return 'Данные изменились. Обновите страницу и повторите.';
  return structureError(cause);
}
