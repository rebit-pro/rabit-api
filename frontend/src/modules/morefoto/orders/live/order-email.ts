import api from '@/api/http';
import type { OrderEmailDelivery, OrderEmailSummary } from './order-email-rules';

const path = '/api/v1/public/orders/current/file-deliveries';
const buyer = (orderKey: string, extra: Record<string, string> = {}) => ({ headers: { 'X-Order-Key': orderKey, ...extra } });

export const orderEmailApi = {
  async summary(orderKey: string): Promise<OrderEmailSummary> {
    return (await api.get<OrderEmailSummary>(path, buyer(orderKey))).data;
  },
  /** One key per attempt: a lost answer is resolved by reloading the summary, not by repeating the letter. */
  async resend(orderKey: string, requestId: string): Promise<OrderEmailDelivery> {
    return (await api.post<OrderEmailDelivery>(path, { channel: 'email' }, buyer(orderKey, { 'Idempotency-Key': requestId }))).data;
  }
};
