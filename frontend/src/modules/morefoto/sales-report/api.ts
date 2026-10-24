import api from '@/api/http';

export type SalesReportLevel = 'all' | 'institution' | 'shoot' | 'group';
export type ChildSalesStatus = 'paid' | 'late' | 'unpaid' | 'none';

export interface SalesTotals {
  /** Children of the markup in the groups of the level. */
  children: number;
  /** Children with an order in any payment state. */
  orderedFamilies: number;
  paidFamilies: number;
  paidOrders: number;
  prints: number;
  digitalFiles: number;
  bundles: number;
  /** Kopecks. */
  paidTotal: number;
}

export interface SalesReportNode {
  id: string;
  name: string;
}

export interface ChildSales {
  id: string;
  code: string;
  status: ChildSalesStatus;
  orders: number;
  paidOrders: number;
  prints: number;
  digitalFiles: number;
  bundles: number;
  /** Kopecks. */
  paidTotal: number;
  hasPreview: boolean;
}

/** E10 (COM-15): the sales of the staff member's area at one level; no buyer data, children by code only. */
export interface SalesReport {
  level: SalesReportLevel;
  institution: SalesReportNode | null;
  shoot: SalesReportNode | null;
  group: SalesReportNode | null;
  totals: SalesTotals;
  products: { id: string; name: string; prints: number }[];
  /** Institutions, shoots or groups of the level; empty for a group. */
  rows: (SalesReportNode & { totals: SalesTotals })[];
  /** Children of a group; empty for the other levels. */
  children: ChildSales[];
  generatedAt: string;
}

export interface SalesReportFilter {
  institutionId?: string;
  shootId?: string;
  groupId?: string;
}

export const reportsApi = {
  async report(filter: SalesReportFilter): Promise<SalesReport> {
    return (await api.get<SalesReport>('/api/v1/sales-report', { params: filter })).data;
  },
  /** COM-16: the same report as a branded PDF; the server builds it, so it may take longer than a JSON read. */
  async pdf(filter: SalesReportFilter): Promise<Blob> {
    return (await api.get<Blob>('/api/v1/sales-report/pdf', { params: filter, responseType: 'blob', timeout: 60_000 })).data;
  }
};

/** COM-17: the child's first ready frame; loaded with the staff token through useProtectedImage. */
export function childPreviewSource(childId: string): string {
  return '/api/v1/sales-report/children/' + encodeURIComponent(childId) + '/preview';
}
