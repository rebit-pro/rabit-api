import type { DashboardData } from './dashboard/types.js';
import type { FinancialTotals } from './settlement/types.js';
import type { PhotoShoot } from './organization/types.js';

export type StaffRole = 'organizer' | 'curator' | 'head' | 'teacher';

export interface Institution {
  id: string;
  name: string;
  address: string;
}

export interface Group {
  id: string;
  institutionId: string;
  name: string;
  shootName: string;
  kind: 'regular' | 'staff';
  state: 'preparing' | 'open' | 'closed';
  closesAt: string | null;
  shootId?: string;
  galleryToken?: string;
}

export interface ScopeSnapshot {
  dashboard?: DashboardData;
  groupTotals?: Record<string, FinancialTotals>;
  totals?: Record<string, FinancialTotals>;
  shoots: PhotoShoot[];
  institutions: Institution[];
  groups: Group[];
}

export const roleLabels: Record<StaffRole, string> = {
  organizer: 'Организатор',
  curator: 'Куратор',
  head: 'Руководитель учреждения',
  teacher: 'Ответственный группы'
};

export const roleHeadings: Record<StaffRole, string> = {
  organizer: 'Кабинет организатора',
  curator: 'Кабинет куратора',
  head: 'Кабинет учреждения',
  teacher: 'Мои группы'
};

export function isStaffRole(value: unknown): value is StaffRole {
  return typeof value === 'string' && Object.prototype.hasOwnProperty.call(roleLabels, value);
}
