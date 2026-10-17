import type { Receipt, ReceiptStatus } from './receipt-types';

/** G1: payment through the provider's own page; the server alone confirms the result. */
export type PaymentMethod = 'sbp' | 'bank_card';
export type AttemptStatus = 'unknown' | 'pending' | 'succeeded' | 'canceled';

export interface PaymentQuote {
  quote: { subtotal: number; discount: number; giftSaving: number; total: number; currency: 'RUB' };
  quoteToken: string;
  orderVersion: string;
  canPay: boolean;
  precedingAttemptId: string | null;
  activeAttemptId: string | null;
  paymentMethods: PaymentMethod[];
  /** G2, issue #120: the provider test shop — money is not charged. */
  testMode: boolean;
}
export interface PaymentAttempt {
  id: string;
  status: AttemptStatus;
  amount: number;
  paymentMethod: PaymentMethod;
  orderVersion: string;
  latePayment: boolean;
  redirectUrl: string | null;
  created: boolean;
}
export interface StartPaymentBody {
  orderVersion: string;
  precedingAttemptId: string | null;
  quoteToken: string;
  paymentMethod: PaymentMethod;
}
export interface StaffPayment {
  id: string;
  orderId: string;
  orderNumber: string;
  institutionName: string;
  groupName: string;
  createdAt: string;
  amount: number;
  currency: string;
  paymentMethod: PaymentMethod;
  status: AttemptStatus;
  paidAt: string | null;
  latePayment: boolean;
  incomeAmount: number | null;
  cancelReason: string | null;
  /** G2: the payment receipt of the attempt; null — paid without a receipt or not paid. */
  receiptStatus: ReceiptStatus | null;
}
export interface StaffPaymentFact {
  attemptId: string;
  amount: number;
  incomeAmount: number | null;
  paidAt: string;
  latePayment: boolean;
  confirmedBy: 'start' | 'return' | 'notification' | 'reconcile';
}
export interface StaffPaymentCard {
  payment: StaffPayment;
  providerPaymentId: string | null;
  checkCount: number;
  lastCheckAt: string | null;
  nextCheckAt: string | null;
  attempts: StaffPayment[];
  facts: StaffPaymentFact[];
  receipts: Receipt[];
}
export interface PaymentFilters {
  status: string;
  orderNumber: string;
  dateFrom: string;
  dateTo: string;
  late: '' | 'true' | 'false';
  page: number;
  pageSize: number;
}
export interface PaymentPage {
  items: StaffPayment[];
  meta: { page: number; pageSize: number; total: number; totalPages: number };
}
