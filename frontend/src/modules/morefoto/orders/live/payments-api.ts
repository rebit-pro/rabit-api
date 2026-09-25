import api from '@/api/http';
import type {
  PaymentAttempt,
  PaymentFilters,
  PaymentPage,
  PaymentQuote,
  StaffPayment,
  StaffPaymentCard,
  StartPaymentBody
} from './payment-types';
import { paymentParams } from './payment-rules';

const buyer = (orderKey: string) => ({ headers: { 'X-Order-Key': orderKey } });

export const livePaymentsApi = {
  async quote(orderKey: string): Promise<PaymentQuote> {
    return (await api.get<PaymentQuote>('/api/v1/public/orders/current/payment-quote', buyer(orderKey))).data;
  },
  async start(orderKey: string, body: StartPaymentBody, requestId: string): Promise<PaymentAttempt> {
    return (
      await api.post<PaymentAttempt>('/api/v1/public/orders/current/payment-attempts', body, {
        headers: { 'X-Order-Key': orderKey, 'Idempotency-Key': requestId }
      })
    ).data;
  },
  async attempt(orderKey: string, attemptId: string): Promise<PaymentAttempt> {
    return (
      await api.get<PaymentAttempt>('/api/v1/public/orders/current/payment-attempts/' + encodeURIComponent(attemptId), buyer(orderKey))
    ).data;
  },
  async search(filters: PaymentFilters): Promise<PaymentPage> {
    const response = await api.get<{ data: { items: StaffPayment[] }; meta: PaymentPage['meta'] }>('/api/v1/payments', {
      params: paymentParams(filters),
      unwrapEnvelope: false
    });
    return { items: response.data.data.items, meta: response.data.meta };
  },
  async detail(attemptId: string): Promise<StaffPaymentCard> {
    return (await api.get<StaffPaymentCard>('/api/v1/payments/' + encodeURIComponent(attemptId))).data;
  }
};
