import api from '@/api/http';
import type { InstitutionDigitalArchive, InstitutionDigitalArchives } from './institution-digital-types';

const institution = (id: string) => '/api/v1/institutions/' + encodeURIComponent(id) + '/digital-archives';
const archive = (id: string) => '/api/v1/institution-digital-archives/' + encodeURIComponent(id);

export const institutionDigitalApi = {
  async list(id: string, signal: AbortSignal): Promise<InstitutionDigitalArchives> {
    return (await api.get<InstitutionDigitalArchives>(institution(id), { signal })).data;
  },
  async create(id: string, key: string, signal: AbortSignal): Promise<InstitutionDigitalArchive> {
    return (
      await api.post<InstitutionDigitalArchive>(institution(id), null, {
        headers: { 'Idempotency-Key': key },
        signal
      })
    ).data;
  },
  async download(id: string, signal: AbortSignal): Promise<{ url: string; expiresAt: string }> {
    return (await api.get<{ url: string; expiresAt: string }>(archive(id) + '/download', { signal })).data;
  },
  async manifest(id: string, signal: AbortSignal): Promise<Blob> {
    return (
      await api.get<Blob>(archive(id) + '/manifest', {
        responseType: 'blob',
        signal
      })
    ).data;
  }
};
