import type { PrintRow } from '../production/types.js';
export interface ReadyMark {
  at: string;
  actor: string;
  responsible: string;
  comment: string;
}
export interface TransferGroup {
  groupId: string;
  groupName: string;
  kind: 'regular' | 'staff';
  jobId: string;
  jobNumber: string;
  version: number;
  deadline: string | null;
  readyAt: string;
  rows: PrintRow[];
}
export interface Transfer {
  id: string;
  number: string;
  institutionId: string;
  institutionName: string;
  shootId: string;
  shootName: string;
  at: string;
  recordedAt: string;
  actor: string;
  responsible: string;
  receiver: string;
  comment: string;
  groups: TransferGroup[];
}
export interface DeliveryGroup {
  id: string;
  name: string;
  kind: 'regular' | 'staff';
  institutionId: string;
  institutionName: string;
  shootId: string;
  shootName: string;
  deadline: string | null;
  overdue: boolean;
  status: string;
  packs: number;
  prints: number;
  readyAt: string | null;
  jobNumber: string;
  version: number;
  signature: string;
  canReady: boolean;
  canUnready: boolean;
  problem: string;
}
export interface TransferBatch {
  id: string;
  institutionId: string;
  institutionName: string;
  shootId: string;
  shootName: string;
  address: string;
  signature: string;
  groups: { id: string; name: string; kind: 'regular' | 'staff'; packs: number; prints: number; deadline: string | null }[];
  packs: number;
  prints: number;
  earliestAt: string;
}
export interface TransferSummary extends Omit<Transfer, 'groups'> {
  groups: { groupId: string; groupName: string; kind: 'regular' | 'staff'; packs: number; prints: number; deadline: string | null }[];
}
export interface DeliveryView {
  editable: boolean;
  staff: boolean;
  actorId: number;
  actorName: string;
  now: string;
  groups: DeliveryGroup[];
  batches: TransferBatch[];
  transfers: TransferSummary[];
}
export interface DeliveryCommand {
  id: string;
  kind: 'ready' | 'unready' | 'transfer';
  targetId: string;
  signature: string;
  date: string;
  responsible: string;
  receiver: string;
  comment: string;
  confirmed: boolean;
}
export type DeliveryErrors = Partial<Record<keyof DeliveryCommand, string>>;
