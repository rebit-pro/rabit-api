import api from '@/api/http';
import type { DownloadBody, FileDownload, OrderFiles } from './files-types';

const buyer = (orderKey: string) => ({ headers: { 'X-Order-Key': orderKey } });

export const liveFilesApi = {
  async files(orderKey: string): Promise<OrderFiles> {
    return (await api.get<OrderFiles>('/api/v1/public/orders/current/files', buyer(orderKey))).data;
  },
  async request(orderKey: string, body: DownloadBody, requestId: string): Promise<FileDownload> {
    return (
      await api.post<FileDownload>('/api/v1/public/orders/current/downloads', body, {
        headers: { 'X-Order-Key': orderKey, 'Idempotency-Key': requestId }
      })
    ).data;
  },
  async download(orderKey: string, downloadId: string): Promise<FileDownload> {
    return (await api.get<FileDownload>('/api/v1/public/orders/current/downloads/' + encodeURIComponent(downloadId), buyer(orderKey))).data;
  }
};
