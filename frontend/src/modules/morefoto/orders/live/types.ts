import type { Product } from '../../commerce/types.js';

export type PaymentStatus = 'unpaid' | 'pending' | 'declined' | 'paid';
export type ProductionStatus = 'not-started' | 'queued' | 'printing' | 'ready' | 'delivered';
export interface LiveOrderPhoto {
  id: string;
  assignmentId: string;
  code: string;
  width: number;
  height: number;
}
export interface LiveOrderLine {
  id: string;
  assignmentId: string;
  childCode: string;
  photoId: string | null;
  productId: string;
  quantity: number;
  product: Product;
  photo: LiveOrderPhoto | null;
  unitPrice: number;
  total: number;
  discount: number;
  coveredByGift: boolean;
}
export interface LiveOrderQuote {
  lines: LiveOrderLine[];
  total: number;
  subtotal: number;
  discount: number;
  giftSaving: number;
  gifts: string[];
  count: number;
  invalid: string[];
  revision: number;
  conditionsRevision: number;
}
export interface LiveBuyer {
  name: string;
  phone: string;
  email: string;
  comment: string;
  receiptChannel: string | null;
}
export interface OrderPeriod {
  groupId: string;
  state: 'preparing' | 'open' | 'closed';
  timezone: string;
  sentAt: string | null;
  closesAt: string | null;
  deliveryDueAt: string | null;
  now: string;
}
export interface CreatedOrder {
  id: string;
  number: string;
  groupId: string;
  accessKey: string;
  accessKeyExpiresAt: string;
  paymentStatus: PaymentStatus;
  productionStatus: ProductionStatus;
  quote: LiveOrderQuote;
  buyer: LiveBuyer;
  version: string;
  createdAt: string;
}
export interface BuyerOrder {
  id: string;
  number: string;
  groupId: string;
  institutionName: string;
  shootName: string;
  groupName: string;
  audience: 'regular' | 'staff';
  buyer: LiveBuyer;
  quote: LiveOrderQuote;
  paymentStatus: PaymentStatus;
  productionStatus: ProductionStatus;
  version: string;
  period: OrderPeriod;
  accessKeyExpiresAt: string;
  createdAt: string;
}
export interface StaffOrder {
  id: string;
  number: string;
  institutionId: string;
  institutionName: string;
  shootId: string;
  shootName: string;
  groupId: string;
  groupName: string;
  audience: 'regular' | 'staff';
  createdAt: string;
  buyer: LiveBuyer;
  quote: LiveOrderQuote;
  paymentStatus: PaymentStatus;
  productionStatus: ProductionStatus;
  version: string;
}
export interface CorrectionPhoto {
  childId: string;
  childCode: string;
  assignmentId: string;
  photoId: string;
  code: string;
  width: number;
  height: number;
}
export interface StaffOrderCard extends StaffOrder {
  period: OrderPeriod;
  correctionPhotos: CorrectionPhoto[];
}
export interface StaffOrderFilters {
  q: string;
  /** Scope of the search; the server still limits it to the staff member's own area. */
  institutionId: string;
  shootId: string;
  groupId: string;
  paymentStatus: string;
  productionStatus: string;
  dateFrom: string;
  dateTo: string;
  page: number;
  pageSize: number;
}
export interface StaffOrderPage {
  items: StaffOrder[];
  meta: {
    page: number;
    pageSize: number;
    total: number;
    totalPages: number;
    /** U5: the search without its production filter; payment split comes with the payment provider (G1). */
    summary?: { total: number; byProductionStatus: Record<string, number> };
  };
}
export interface CheckoutBody {
  lines: { assignmentId: string; productId: string; quantity: number }[];
  buyer: { name: string; phone: string; email: string; comment: string; reviewed: boolean };
  quoteToken: string;
}
export interface ApiProblem {
  status: number | null;
  code: string;
  network: boolean;
}
