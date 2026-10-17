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
import type { OrderReceipts, PrintHandovers } from './receipt-types';
import { tableViewParams, type TableView } from '../../ui/table-query';

const buyer = (orderKey: string) => ({ headers: { 'X-Order-Key': orderKey } });
const handovers = (groupId: string) => '/api/v1/groups/' + encodeURIComponent(groupId) + '/print-handovers';

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
  async search(filters: PaymentFilters, view?: TableView): Promise<PaymentPage> {
    const response = await api.get<{ data: { items: StaffPayment[] }; meta: PaymentPage['meta'] }>('/api/v1/payments', {
      params: { ...paymentParams(filters), ...(view ? tableViewParams(view) : {}) },
      unwrapEnvelope: false
    });
    return { items: response.data.data.items, meta: response.data.meta };
  },
  async detail(attemptId: string): Promise<StaffPaymentCard> {
    return (await api.get<StaffPaymentCard>('/api/v1/payments/' + encodeURIComponent(attemptId))).data;
  },
  /** PAY-04: the receipts of the order by its personal key. */
  async receipts(orderKey: string): Promise<OrderReceipts> {
    return (await api.get<OrderReceipts>('/api/v1/public/orders/current/receipt', buyer(orderKey))).data;
  },
  /** PAY-12: the paid print of a group and its handover. */
  async handovers(groupId: string): Promise<PrintHandovers> {
    return (await api.get<PrintHandovers>(handovers(groupId))).data;
  },
  /** PAY-13: the print of these orders is handed over; a repeat changes nothing, so no idempotency key is needed. */
  async handOver(groupId: string, orderIds: string[]): Promise<PrintHandovers> {
    return (await api.post<PrintHandovers>(handovers(groupId), { orderIds, confirmed: true })).data;
  }
};
