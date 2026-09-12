import type { Group } from '../types.js';
import type { StaffRequest } from '../handoff/types.js';
export interface GroupWork {
  sentAt: string | null;
  deliveryAt: string | null;
  linkState: 'checking' | 'ready' | 'sent';
}
export interface RequestSummary {
  id: string;
  institutionId: string;
  shootId: string;
  groupIds: string[];
  createdAt: string;
  status: StaffRequest['status'];
}
export interface DashboardData {
  now: string;
  groups: Record<string, GroupWork>;
  requests: RequestSummary[];
  curators: Record<string, { name: string; email: string }>;
}
export interface DashboardFilters {
  q: string;
  shoot: string;
  state: string;
}
export const stateLabels: Record<Group['state'], string> = {
  preparing: 'Подготовка',
  open: 'Приём открыт',
  closed: 'Приём закрыт'
};
