import { isAxiosError } from 'axios';
import api from '@/api/http';
import type { Product } from '../commerce/types';

export type CatalogProduct = Required<Product>;
export type ProductPayload = Omit<CatalogProduct, 'id'>;
export interface CatalogPage {
  data: { items: CatalogProduct[]; revision: number };
  meta: { page: number; pageSize: number; total: number };
}
export interface ProductMutation {
  id: string;
  revision: number;
}
export interface ProductAttempt {
  id: string | null;
  key: string;
  body: ProductPayload & { revision?: number };
}
export const catalogApi = {
  list(page = 1, pageSize = 25): Promise<CatalogPage> {
    return api.get('/api/v1/catalog/products', { params: { page, pageSize }, unwrapEnvelope: false }).then((r) => r.data);
  },
  save(attempt: ProductAttempt): Promise<ProductMutation> {
    const options = { headers: { 'Idempotency-Key': attempt.key } };
    return (
      attempt.id
        ? api.patch('/api/v1/catalog/products/' + encodeURIComponent(attempt.id), attempt.body, options)
        : api.post('/api/v1/catalog/products', attempt.body, options)
    ).then((r) => r.data);
  },
  /** Removes the product with its rows in group conditions; an already removed one counts as done. */
  async remove(id: string): Promise<void> {
    try {
      await api.delete('/api/v1/catalog/products/' + encodeURIComponent(id));
    } catch (cause) {
      if (!isAxiosError(cause) || cause.response?.status !== 404) throw cause;
    }
  }
};
/** Refusal of one product in a table action; the editor keeps its own texts about drafts. */
export function catalogActionError(cause: unknown): string {
  if (!isAxiosError(cause)) return catalogError(cause);
  const code = (cause.response?.data as { error?: { code?: string } } | undefined)?.error?.code;
  if (code === 'PRODUCT_IN_ORDERS') return 'Эту продукцию уже покупали — снимите её с продажи.';
  if (cause.response?.status === 409) return 'Каталог изменён в другой вкладке. Обновите каталог и повторите.';
  return catalogError(cause);
}
export function catalogError(cause: unknown): string {
  if (!isAxiosError(cause)) return cause instanceof Error ? cause.message : 'Не удалось выполнить запрос.';
  switch (cause.response?.status) {
    case 401:
      return 'Сессия завершена. Войдите снова.';
    case 403:
      return 'Недостаточно прав для управления каталогом.';
    case 404:
      return 'Товар больше недоступен. Обновите каталог.';
    case 409:
      return 'Каталог изменён в другой вкладке. Ваши изменения сохранены в черновике. Загрузите актуальные данные.';
    case 422:
      return 'Сервер отклонил данные товара. Проверьте заполненные поля.';
    default:
      return 'Не удалось получить ответ сервера. Проверьте соединение и повторите запрос.';
  }
}
