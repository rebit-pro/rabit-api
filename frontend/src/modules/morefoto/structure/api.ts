import { isAxiosError } from 'axios';
import api from '@/api/http';
import type {
  Institution,
  Shoot,
  ShootDetail,
  StructureAttempt,
  StructurePage,
  StructureScope,
  PageMeta,
  InstitutionDetail,
  InstitutionPages
} from './model';
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
