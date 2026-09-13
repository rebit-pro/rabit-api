export type UiTableValue = string | number | null;
export interface UiTableRow {
  id: string;
  [key: string]: UiTableValue;
}
export type UiTableTone = 'neutral' | 'info' | 'success' | 'warning';
export interface UiTableColumn {
  key: string;
  label: string;
  type?: 'text' | 'number' | 'money' | 'date' | 'status';
  sortable?: boolean;
  primary?: boolean;
  mobile?: boolean;
  statuses?: Record<string, { label: string; tone: UiTableTone }>;
}
export interface UiTableSort {
  key: string;
  direction: 'asc' | 'desc';
}
export type UiTableKind = 'institutions' | 'orders';
