import api from '@/api/http';
import type { InstitutionArchiveVersion, InstitutionPrintArchive } from './institution-archive-types';

const institution = (id: string) => '/api/v1/institutions/' + encodeURIComponent(id) + '/production';
const archive = (id: string) => '/api/v1/production/institution-archives/' + encodeURIComponent(id);

export const institutionArchiveApi = {
  async get(id: string): Promise<InstitutionPrintArchive> {
    return (await api.get<InstitutionPrintArchive>(institution(id))).data;
  },
  async supplement(id: string, key: string): Promise<InstitutionArchiveVersion> {
    return (await api.post<InstitutionArchiveVersion>(institution(id) + '/supplements', null, { headers: { 'Idempotency-Key': key } }))
      .data;
  },
  async renew(id: string, key: string): Promise<InstitutionArchiveVersion> {
    return (await api.post<InstitutionArchiveVersion>(archive(id) + '/renewal', null, { headers: { 'Idempotency-Key': key } })).data;
  },
  async download(id: string): Promise<{ url: string; expiresAt: string }> {
    return (await api.post<{ url: string; expiresAt: string }>(archive(id) + '/download')).data;
  },
  async manifest(id: string): Promise<Blob> {
    return (await api.get<Blob>(archive(id) + '/export', { responseType: 'blob' })).data;
  }
};
