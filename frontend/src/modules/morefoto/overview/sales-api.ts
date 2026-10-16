import api from '@/api/http';

/** E9 (COM-14): what the parents of a group paid for; only paid orders, no buyer data. */
export interface GroupSales {
  groupId: string;
  paidOrders: number;
  families: number;
  prints: number;
  digitalFiles: number;
  bundles: number;
  /** Kopecks. */
  paidTotal: number;
  products: { id: string; name: string; prints: number }[];
}

export const salesApi = {
  async group(groupId: string): Promise<GroupSales> {
    return (await api.get<GroupSales>('/api/v1/groups/' + encodeURIComponent(groupId) + '/sales-summary')).data;
  }
};
