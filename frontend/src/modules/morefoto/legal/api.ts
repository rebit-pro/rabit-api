import api from '@/api/http';
import type { AcceptedDocument, LegalCatalog, LegalDocument, LegalDocumentCode, LegalDocumentText } from './types';

export const legalApi = {
  async catalog(): Promise<LegalCatalog> {
    return (await api.get<LegalCatalog>('/api/v1/public/legal/documents')).data;
  },
  async document(code: LegalDocumentCode, version?: string): Promise<LegalDocumentText> {
    const path = '/api/v1/public/legal/documents/' + encodeURIComponent(code) + (version ? '/versions/' + encodeURIComponent(version) : '');
    return (await api.get<LegalDocumentText>(path)).data;
  },
  async pending(): Promise<LegalDocument[]> {
    return (await api.get<{ documents: LegalDocument[] }>('/api/v1/legal/consents/pending')).data.documents;
  },
  async accept(consents: AcceptedDocument[]): Promise<LegalDocument[]> {
    return (await api.post<{ documents: LegalDocument[] }>('/api/v1/legal/consents', { consents })).data.documents;
  }
};
