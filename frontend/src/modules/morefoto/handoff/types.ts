import type { ManagedPhoto } from '../photos/types.js';
import type { ScopeSnapshot, StaffRole } from '../types.js';
export interface LinkEvent {
  kind: 'prepared' | 'transmitted' | 'corrected';
  actorName?: string;
  actorId: number;
  at: string;
  sentAt?: string;
  closesAt?: string;
  previousSentAt?: string;
  previousClosesAt?: string;
  reason?: string;
}
export interface LinkPreparation {
  at: string;
  actorId: number;
  signature: string;
}
export interface Operation {
  requestId: string;
  actorId: number;
  signature: string;
}
export interface RequestRow {
  id: string;
  groupId: string;
  code: string;
}
export interface SubmittedRow extends RequestRow {
  childCode: string;
  photoIds: string[];
}
export interface TransferResult {
  rowId: string;
  fromGroupId: string;
  fromChildCode: string;
  targetGroupId: string;
  targetChildCode: string;
  photoIds: string[];
}
export interface StaffRequest {
  id: string;
  institutionId: string;
  shootId: string;
  createdBy: number;
  createdByName?: string;
  createdAt: string;
  revision: number;
  status: 'submitted' | 'clarification' | 'transferred';
  rows: SubmittedRow[];
  comment: string;
  history: { actorName?: string; kind: 'submitted' | 'clarification' | 'transferred'; actorId: number; at: string; comment: string }[];
  results?: TransferResult[];
  staffEligibility?: {
    eligible: boolean;
    source: 'verified_staff_assignment';
    verifiedAt: string;
  };
}
export interface LinkGroup {
  id: string;
  name: string;
  institutionId: string;
  shootId: string;
  shootName: string;
  galleryToken: string;
  kind: 'regular' | 'staff';
  revision: number;
  state: 'preparing' | 'open' | 'closed';
  sentAt: string | null;
  closesAt: string | null;
  deliveryAt: string | null;
  extensionClosesAt?: string;
  signature: string;
  prepared: boolean;
  problems: string[];
  history: LinkEvent[];
  photoCount: number;
  childCount: number;
}
export interface HandoffWorkspace {
  scope: ScopeSnapshot;
  groups: LinkGroup[];
  requests: StaffRequest[];
  photos: ManagedPhoto[];
  role: StaffRole;
  now: string;
  /** U5 server split of the visible staff requests; the demo counts its local list instead. */
  requestSummary?: Record<'submitted' | 'clarification' | 'transferred', number>;
}
export interface LinkCommand {
  kind: 'link';
  action: 'prepare' | 'transmit' | 'correct';
  requestId: string;
  groupId: string;
  revision: number;
  signature: string;
  sentAt: string;
  reason: string;
  confirmed: boolean;
  photosReviewed: boolean;
  conditionsReviewed: boolean;
  staffReviewed: boolean;
}
export interface StaffCommand {
  kind: 'request';
  action: 'submit' | 'clarify' | 'confirm';
  requestId: string;
  id: string | null;
  revision: number | null;
  institutionId: string;
  shootId: string;
  rows: RequestRow[];
  comment: string;
  reason: string;
  confirmed: boolean;
  signature: string;
}
export type HandoffCommand = LinkCommand | StaffCommand;
export type HandoffErrors = Record<string, string>;
/** Кадр в проверке переноса: демо-режим передаёт полные ManagedPhoto, live — только ID, код и служебное превью. */
export type ReviewPhoto = Pick<ManagedPhoto, 'id' | 'code' | 'previewSrc'>;
export interface ReviewBundle<P extends ReviewPhoto = ManagedPhoto> {
  row: SubmittedRow;
  photos: P[];
  targetCode: string;
  hasOrders?: boolean;
}
export interface RequestPreview<P extends ReviewPhoto = ManagedPhoto> {
  targetGroupId: string;
  targetGroupName?: string;
  bundles: ReviewBundle<P>[];
  signature: string;
  hasOrders: boolean;
}
/** HND-10: полный текущий набор каждого ребёнка заявки с целевыми кодами папки сотрудников. */
export interface ServerTransferPreview {
  targetGroupId: string;
  targetGroupName: string;
  bundles: {
    rowId: string;
    groupId: string;
    childCode: string;
    targetCode: string;
    hasOrders: boolean;
    photos: { id: string; code: string; revision: number }[];
  }[];
  signature: string;
  hasOrders: boolean;
  revision: number;
}
