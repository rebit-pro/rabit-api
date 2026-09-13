import type { SettlementState } from '../settlement/types.js';
import type { BuyerDelivery, SupportRequest } from './delivery/types.js';
import type { CartQuote } from '../commerce/types.js';
import type { GalleryPhoto } from '../gallery/types.js';
export type PaymentResult = 'paid' | 'declined';
export type PaymentOutcome = PaymentResult | 'pending' | 'connection-lost';
export interface PaymentAttempt {
  id: string;
  requestId: string;
  amount: number;
  status: 'pending' | PaymentResult;
  startedAt: string;
  completedAt?: string;
}
export interface OrderEvent {
  type: 'payment-started' | 'payment-confirmed' | 'payment-declined' | 'price-reviewed';
  at: string;
  attemptId?: string;
  amount?: number;
  previousTotal?: number;
}
export type ReceiptChannel = 'email' | 'max';
export interface BuyerFields {
  name: string;
  phone: string;
  email: string;
  comment: string;
  receiptChannel: ReceiptChannel;
  reviewed: boolean;
}
export interface CheckoutDraft extends BuyerFields {
  requestId: string;
}
export type BuyerErrors = Partial<Record<keyof BuyerFields, string>>;
export interface OrderSnapshot {
  physicalDelivery?: { readyAt?: string; transferredAt?: string; number?: string };
  id: string;
  accessKey: string;
  requestId: string;
  number: string;
  groupId: string;
  galleryToken: string;
  institutionName: string;
  groupName: string;
  shootName: string;
  audience: 'regular' | 'staff';
  createdAt: string;
  closesAt: string;
  buyer: Omit<BuyerFields, 'reviewed'>;
  quote: CartQuote;
  digitalPhotos: GalleryPhoto[];
  paymentStatus: 'unpaid' | 'pending' | 'declined' | 'paid';
  paymentAttempts?: PaymentAttempt[];
  paidAt?: string;
  latePayment?: boolean;
  history?: OrderEvent[];
  buyerDelivery?: BuyerDelivery;
  supportRequests?: SupportRequest[];
  settlement?: SettlementState;
  productionStatus: 'not-started' | 'queued' | 'printing' | 'ready' | 'delivered';
}
