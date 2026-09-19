import { isAxiosError } from 'axios';
import api from '@/api/http';
import type { CatalogProduct } from '../catalog/api';

export interface ConditionsSnapshot {
  revision: number;
  catalogRevision: number;
  conditionsRevision?: number;
  inherit?: boolean;
  products: CatalogProduct[];
  giftThreshold: number;
  giftForStaff: boolean;
}
export interface ConditionsBody {
  revision: number;
  catalogRevision: number;
  conditionsRevision?: number;
  inherit?: boolean;
  products: { id: string; price: number; active: boolean; staffDiscount: boolean }[];
  giftEnabled: boolean;
  giftThreshold: number;
  giftForStaff: boolean;
}
export interface ConditionsAttempt {
  groupId: string | null;
  key: string;
  body: ConditionsBody;
}
export interface ConditionsMutation {
  revision: number;
  conditionsRevision?: number;
}
const path = (groupId: string | null) =>
  groupId ? '/api/v1/groups/' + encodeURIComponent(groupId) + '/conditions' : '/api/v1/catalog/conditions';
export const conditionsApi = {
  get(groupId: string | null = null): Promise<ConditionsSnapshot> {
    return api.get(path(groupId)).then((response) => response.data);
  },
  save(attempt: ConditionsAttempt): Promise<ConditionsMutation> {
    return api.put(path(attempt.groupId), attempt.body, { headers: { 'Idempotency-Key': attempt.key } }).then((response) => response.data);
  }
};
export function conditionsError(cause: unknown): string {
  if (!isAxiosError(cause)) return cause instanceof Error ? cause.message : 'Не удалось выполнить запрос.';
  switch (cause.response?.status) {
    case 401:
      return 'Сессия завершена. Войдите снова.';
    case 403:
      return 'Недостаточно прав для управления условиями продаж.';
    case 404:
      return 'Группа больше недоступна. Вернитесь к списку съёмок.';
    case 409:
      return 'Условия или каталог изменены в другой вкладке. Черновик сохранён. Загрузите актуальные данные.';
    case 422:
      return 'Сервер отклонил условия. Проверьте цены, доступность и подарочный комплект.';
    default:
      return 'Не удалось получить ответ сервера. Проверьте соединение и повторите запрос.';
  }
}
