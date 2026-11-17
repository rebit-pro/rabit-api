import type { VersionStatus } from './archive-types';

export interface InstitutionArchiveVersion {
  id: string;
  number: number;
  kind: 'full' | 'supplement';
  status: VersionStatus;
  lines: number;
  files: number;
  prints: number;
  generation: number;
  groups: string[];
  error: string | null;
  bytes: number | null;
  createdAt: string;
  readyAt: string | null;
  expiresAt: string | null;
}

export interface InstitutionPrintArchive {
  institutionId: string;
  institutionName: string;
  state: 'open' | 'scheduled' | VersionStatus;
  canManage: boolean;
  buildFrom: string | null;
  outsideLines: number;
  waitingOrders: number;
  error: string | null;
  blockers: {
    id: string;
    name: string;
    shootName: string;
    deadlineAt: string | null;
  }[];
  versions: InstitutionArchiveVersion[];
}
