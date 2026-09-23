import type { AvatarRef } from '@/api/auth';
import type { StaffRole } from '../types';
export type AccountStatus = 'pending' | 'active' | 'blocked';
/** Personal invitation of a pending staff member; the link itself only travels in the letter. */
export interface StaffInvitation {
  sentAt: string;
  expiresAt: string;
  state: 'sent' | 'expired' | 'accepted';
}
export interface StaffSummary {
  id: number;
  name: string;
  email: string;
  role: StaffRole;
  active: boolean;
  revision: number;
  accessRevision: number;
  accountStatus: AccountStatus;
  assignmentCount: number;
  invitation?: StaffInvitation | null;
  avatar?: AvatarRef | null;
}
export interface StaffDetail extends Omit<StaffSummary, 'assignmentCount'> {
  institutionIds: string[];
  groupIds: string[];
  assignmentSignature: string;
}
export interface InstitutionOption {
  id: string;
  name: string;
  address: string;
  curatorId: number | null;
  headId: number | null;
}
export interface GroupOption {
  id: string;
  name: string;
  shootId: string;
  shootName: string;
  institutionId: string;
  institutionName: string;
  teacherId: number | null;
}
export interface AssignmentOptions {
  institutions: InstitutionOption[];
  groups: GroupOption[];
  staff: StaffSummary[];
  assignmentSignature: string;
}
export interface StaffPage {
  items: StaffSummary[];
  meta: {
    page: number;
    pageSize: number;
    total: number;
    totalPages: number;
    /** U5: each split ignores its own filter, so a tile shows what the list will hold after a click. */
    summary?: { byAccountStatus: Record<AccountStatus, number>; byRole: Record<string, number> };
  };
}
export interface StaffDraft {
  id: number | null;
  revision: number | null;
  requestId: string;
  name: string;
  email: string;
  role: StaffRole;
  active: boolean;
  institutionIds: string[];
  groupIds: string[];
  replaceAssignments: boolean;
  reason: string;
  assignmentSignature: string;
}
export interface StaffFilters {
  q: string;
  role: StaffRole | null;
  accountStatus: AccountStatus | null;
}
export interface StaffMutationResult {
  id: number;
  revision: number;
  accessRevision: number;
  assignmentSignature: string;
  accountStatus: AccountStatus;
}
