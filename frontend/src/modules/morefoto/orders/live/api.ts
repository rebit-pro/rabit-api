import { isAxiosError } from 'axios';
import api from '@/api/http';
import type {
  ApiProblem,
  BuyerOrder,
  CheckoutBody,
  CreatedOrder,
  StaffOrder,
  StaffOrderCard,
  StaffOrderFilters,
  StaffOrderPage
} from './types';
import { staffOrderParams } from './rules';

export const liveOrdersApi = {
  async create(token: string, body: CheckoutBody, requestId: string): Promise<CreatedOrder> {
    return (
      await api.post<CreatedOrder>('/api/v1/public/galleries/' + encodeURIComponent(token) + '/orders', body, {
        headers: { 'Idempotency-Key': requestId }
      })
    ).data;
  },
  async current(orderKey: string): Promise<BuyerOrder> {
    return (await api.get<BuyerOrder>('/api/v1/public/orders/current', { headers: { 'X-Order-Key': orderKey } })).data;
  },
  async search(filters: StaffOrderFilters): Promise<StaffOrderPage> {
    const response = await api.get<{ data: { items: StaffOrder[] }; meta: StaffOrderPage['meta'] }>('/api/v1/orders', {
      params: staffOrderParams(filters),
      unwrapEnvelope: false
    });
    return { items: response.data.data.items, meta: response.data.meta };
  },
  async detail(orderId: string): Promise<StaffOrderCard> {
    return (await api.get<StaffOrderCard>('/api/v1/orders/' + encodeURIComponent(orderId))).data;
  }
};

export function apiProblem(cause: unknown): ApiProblem {
  if (!isAxiosError(cause)) return { status: null, code: '', network: false };
  const code = (cause.response?.data as { error?: { code?: string } } | undefined)?.error?.code ?? '';
  return { status: cause.response?.status ?? null, code, network: cause.response === undefined };
}
