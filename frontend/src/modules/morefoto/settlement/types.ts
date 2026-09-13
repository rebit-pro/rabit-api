import type { OrderSnapshot } from '../orders/types.js';
import type { CartQuoteLine } from '../commerce/types.js';
export type SaleOrder = Omit<OrderSnapshot, 'accessKey' | 'requestId' | 'galleryToken'>;
export interface ActorEvent {
  id: string;
  at: string;
  actorId: number;
  actorName: string;
  reason: string;
  supportId?: string;
}
export interface Correction extends ActorEvent {
  kind: 'contacts' | 'line';
  buyerBefore?: OrderSnapshot['buyer'];
  buyerAfter?: OrderSnapshot['buyer'];
  before?: CartQuoteLine;
  after?: CartQuoteLine;
  production: OrderSnapshot['productionStatus'];
  needsReprint: boolean;
  reprintApproved?: boolean;
}
export interface Refund extends ActorEvent {
  amount: number;
  allocations: Record<string, number>;
  status: 'pending' | 'confirmed' | 'failed';
  fileIds: string[] | null;
  hold: boolean;
  holdApplied?: boolean;
  history: { at: string; actorName: string; status: Refund['status']; reason: string }[];
}
export interface Fulfilment extends ActorEvent {
  decision: 'fulfil' | 'refund';
}
export interface Recovery extends ActorEvent {
  email: string;
  previousEmail: string;
  status: 'sent' | 'failed';
}
export interface PreparedFiles {
  id: string;
  at: string;
  photoIds: string[];
}
export interface SettlementState {
  revision: number;
  operations: { id: string; signature: string }[];
  corrections: Correction[];
  refunds: Refund[];
  decisions: Fulfilment[];
  recoveries: Recovery[];
  preparedFiles: PreparedFiles[];
  hold: boolean;
  needsReprint: boolean;
  reprintApproved?: boolean;
}
export interface FinancialTotals {
  paidCount: number;
  paid: number;
  refunded: number;
  pending: number;
  net: number;
}
export interface SaleCommand {
  kind: 'settlement';
  action: 'contacts' | 'line' | 'refund' | 'result' | 'retry' | 'fulfilment' | 'recover';
  requestId: string;
  orderId: string;
  version: string;
  supportId: string;
  reason: string;
  confirmed: boolean;
  verified: boolean;
  name: string;
  email: string;
  phone: string;
  lineId: string;
  photoId: string;
  quantity: string;
  mode: 'amount' | 'lines';
  amount: string;
  lineIds: string[];
  files: 'keep' | 'selected' | 'all';
  production: 'keep' | 'hold';
  refundId: string;
  result: 'confirmed' | 'failed';
  decision: 'fulfil' | 'refund';
}
export type SaleErrors = Partial<Record<keyof SaleCommand, string>>;
