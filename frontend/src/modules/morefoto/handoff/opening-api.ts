import api from '@/api/http';

const path = (groupId: string) => '/api/v1/groups/' + encodeURIComponent(groupId);
const config = (requestId: string) => ({
  headers: { 'Idempotency-Key': requestId.replace(/-/g, '') }
});

export const openingApi = {
  async schedule(groupId: string, revision: number, signature: string, openAt: string, requestId: string): Promise<void> {
    await api.post(path(groupId) + '/opening-schedules', { revision, signature, openAt, confirmed: true }, config(requestId));
  },
  async cancel(groupId: string, revision: number, requestId: string): Promise<void> {
    await api.post(path(groupId) + '/opening-cancellations', { revision }, config(requestId));
  },
  async recordManual(groupId: string, revision: number, signature: string, sentAt: string, requestId: string): Promise<void> {
    await api.post(path(groupId) + '/manual-link-deliveries', { revision, signature, sentAt, confirmed: true }, config(requestId));
  }
};
