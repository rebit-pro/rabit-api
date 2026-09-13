import type { GroupExtension } from '../curator/types.js';
import type { Group, Institution, StaffRole } from '../types.js';

export interface ManagedInstitution extends Institution {
  curatorId: number | null;
  headId: number | null;
  revision: number;
}
export interface PhotoShoot {
  id: string;
  institutionId: string;
  name: string;
  date: string | null;
  revision: number;
}
import type { LinkEvent, LinkPreparation, Operation } from '../handoff/types.js';
export interface ManagedGroup extends Group {
  extensions?: GroupExtension[];
  preparation?: LinkPreparation;
  sentAt?: string;
  linkHistory?: LinkEvent[];
  linkOperations?: Operation[];
  shootId: string;
  teacherId: number | null;
  galleryToken: string;
  revision: number;
}
export interface StaffOption {
  id: number;
  name: string;
  email: string;
  role: StaffRole;
}
export type EntityKind = 'institution' | 'shoot' | 'group';
import type { ManagedStaff } from '../management/types.js';
export interface OrganizationState {
  users: ManagedStaff[];
  userOperations: { requestId: string; actorId: number; id: number; signature?: string }[];
  institutions: ManagedInstitution[];
  shoots: PhotoShoot[];
  groups: ManagedGroup[];
  operations: { requestId: string; actorId: number; kind: EntityKind; id: string }[];
}
export interface OrganizationSnapshot extends OrganizationState {
  staff: StaffOption[];
}
export interface EntityFields {
  name: string;
  address: string;
  date: string;
  curatorId: number | null;
  headId: number | null;
  teacherId: number | null;
  groupKind: 'regular' | 'staff';
}
export type FieldErrors = Partial<Record<keyof EntityFields, string>>;
export interface EditorTarget {
  kind: EntityKind;
  id?: string;
  parentId?: string;
}
export interface SaveCommand extends EditorTarget {
  requestId: string;
  revision: number | null;
  fields: EntityFields;
}
export interface SaveResult {
  kind: EntityKind;
  id: string;
}
