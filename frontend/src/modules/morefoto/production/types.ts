import type { ReadyMark, Transfer } from '../shipping/types.js';
import type { ManagedGroup, OrganizationState } from '../organization/types.js';
import type { PhotoState } from '../photos/types.js';
import type { OrderSnapshot } from '../orders/types.js';
export interface PrintRow {
  key: string;
  orderId: string;
  orderNumber: string;
  lineId: string;
  photoId: string;
  photoCode: string;
  productName: string;
  format: string;
  quantity: number;
  perUnit: number;
  prints: number;
  childCode: string;
  sourceGroupId: string;
  sourceGroupName: string;
  sourceChildCode: string;
  purchaseGroupName: string;
  audience: OrderSnapshot['audience'];
}
export interface RunRow extends PrintRow {
  prior: number;
  next: number;
}
export interface Exclusion {
  orderId: string;
  number: string;
  reason: string;
}
export interface PrintPlan {
  rows: PrintRow[];
  excluded: Exclusion[];
  digitalCount: number;
  signature: string;
  closed: boolean;
  closesAt: string | null;
  deliveryAt: string | null;
}
export interface ProductionEvent {
  at: string;
  actor: string;
  text: string;
}
export interface PrintVersion {
  ready?: ReadyMark;
  number: number;
  createdAt: string;
  actor: string;
  reason: string;
  plan: PrintPlan;
  runRows: RunRow[];
  surplus: RunRow[];
  startedAt?: string;
  startedBy?: string;
  packages: Record<string, { at: string; actor: string }>;
}
export interface PrintJob {
  id: string;
  number: string;
  groupId: string;
  institutionId: string;
  shootId: string;
  revision: number;
  versions: PrintVersion[];
  history: ProductionEvent[];
}
export interface ProductionState {
  transfers?: Transfer[];
  deliveryOperations?: { id: string; signature: string; actorId: number }[];
  jobs: PrintJob[];
  operations: { id: string; signature: string; actorId: number; jobId: string }[];
}
export interface ProductionGroup {
  group: ManagedGroup;
  institutionName: string;
  shootName: string;
  plan: PrintPlan;
  job: PrintJob | null;
}
export interface ProductionWorkspace {
  editable: boolean;
  actorId: number;
  groups: ProductionGroup[];
}
export interface ProductionCommand {
  id: string;
  groupId: string;
  revision: number;
  signature: string;
  kind: 'version' | 'start' | 'pack';
  reason: string;
  orderId?: string;
  packed?: boolean;
}
export interface PlanInput {
  group: ManagedGroup;
  organization: OrganizationState;
  photos: PhotoState;
  orders: OrderSnapshot[];
  state: ProductionState;
  now: string;
}
