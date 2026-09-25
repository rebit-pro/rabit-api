export type LegalDocumentCode = 'privacy' | 'offer' | 'buyer-consent' | 'staff-consent';

export interface LegalDocument {
  code: LegalDocumentCode;
  version: string;
  title: string;
  effectiveFrom: string;
}

/** Individual entrepreneur: published only when every required requisite is set on the server. */
export interface Seller {
  published: boolean;
  name: string | null;
  inn: string | null;
  ogrnip: string | null;
  address: string | null;
  email: string | null;
  phone: string | null;
}

export interface LegalCatalog {
  documents: LegalDocument[];
  seller: Seller;
}

export interface LegalBlock {
  type: 'heading' | 'paragraph' | 'list';
  text: string;
  level: number;
  items: string[];
}

export interface LegalDocumentText {
  document: LegalDocument;
  current: boolean;
  blocks: LegalBlock[];
  versions: LegalDocument[];
}

export interface AcceptedDocument {
  code: LegalDocumentCode;
  version: string;
}
