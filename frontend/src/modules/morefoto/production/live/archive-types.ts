/** L1: state of the «Печать» block of a group; `open` while print sales run or the link is not handed over. */
export type ArchiveState = 'open' | 'scheduled' | 'manual' | 'building' | 'ready' | 'failed' | 'empty' | 'expired';
export type VersionStatus = 'pending' | 'ready' | 'failed' | 'empty' | 'expired';

export interface ArchiveProduct {
  name: string;
  format: string;
  files: number;
  prints: number;
}
export interface ArchiveVersion {
  id: string;
  groupId: string;
  /** 1 — the group archive, more — a supplement with payments confirmed after the previous build. */
  number: number;
  status: VersionStatus;
  lines: number;
  files: number;
  prints: number;
  /** Orders with print that still waited for the payment confirmation when the version was taken. */
  waitingOrders: number;
  products: ArchiveProduct[];
  manual: boolean;
  createdAt: string;
  readyAt: string | null;
  expiresAt: string | null;
  bytes: number | null;
  error: string | null;
  /** The link without login, the same one for the cabinet and the lab; only for a ready version within retention. */
  archiveUrl: string | null;
  exportUrl: string;
}
export interface GroupArchive {
  groupId: string;
  groupName: string;
  state: ArchiveState;
  deadlineAt: string | null;
  buildFrom: string | null;
  canManage: boolean;
  /** Paid print lines that entered no version yet: a supplement collects them. */
  outsideLines: number;
  outsidePrints: number;
  waitingOrders: number;
  versions: ArchiveVersion[];
}
export interface GroupArchiveStatus {
  groupId: string;
  state: ArchiveState;
  version: number | null;
}
