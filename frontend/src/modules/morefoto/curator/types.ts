import type { ScopeSnapshot } from '../types.js';
import type { OrderSnapshot } from '../orders/types.js';
import type { SupportRequest } from '../orders/delivery/types.js';
export interface GroupExtension {
  id: string;
  actorId: number;
  actorName: string;
  at: string;
  orderId: string;
  supportId: string;
  previousClosesAt: string;
  closesAt: string;
  reason: string;
  signature: string;
}
export interface SupportEvent {
  requestId: string;
  signature: string;
  actorId: number;
  actorName: string;
  at: string;
  status: 'in-progress' | 'resolved';
  comment: string;
}
export interface OrderPeriod {
  groupId: string;
  revision: number;
  sentAt: string | null;
  closesAt: string | null;
  deliveryAt: string | null;
  extensions: GroupExtension[];
  curator: string;
  state: 'preparing' | 'open' | 'closed';
  now: string;
}
export type StaffOrder = Omit<OrderSnapshot, 'accessKey' | 'requestId' | 'galleryToken'> & {
  institutionId: string;
  shootId: string;
  period: OrderPeriod;
};
export interface CaseItem {
  request: SupportRequest;
  order: StaffOrder;
}
export interface CuratorWorkspace {
  scope: ScopeSnapshot;
  orders: StaffOrder[];
  cases: CaseItem[];
  now: string;
}
export interface CaseCommand {
  kind: 'case';
  action: 'reply' | 'extend';
  requestId: string;
  orderId: string;
  supportId: string;
  revision: number;
  groupRevision: number;
  status: 'in-progress' | 'resolved';
  comment: string;
  closesAt: string;
  reason: string;
  confirmed: boolean;
}
export type CaseErrors = Record<string, string>;
export interface WorkFilters {
  query: string;
  institution: string;
  shoot: string;
  group: string;
  payment: string;
  production: string;
  dateFrom: string;
  dateTo: string;
  status: string;
  topic: string;
  late: string;
  settlement?: string;
}
