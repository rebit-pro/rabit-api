import type { AcceptedDocument, LegalCatalog, LegalDocument, LegalDocumentCode, Seller } from './types';

export const COOKIE_NOTICE_KEY = 'morefoto:cookie-notice:v1';
/** Contacts typed at checkout stay in this browser no longer than a week. */
export const CHECKOUT_DRAFT_TTL_MS = 7 * 24 * 60 * 60 * 1000;
export const ORDER_DOCUMENTS: LegalDocumentCode[] = ['buyer-consent', 'offer'];
export const STAFF_DOCUMENTS: LegalDocumentCode[] = ['staff-consent'];

export function documentPath(code: LegalDocumentCode, version?: string): string {
  return '/legal/' + code + (version ? '/v/' + encodeURIComponent(version) : '');
}

export function findDocument(catalog: LegalCatalog | null, code: LegalDocumentCode): LegalDocument | null {
  return catalog?.documents.find((document) => document.code === code) ?? null;
}

/** Current versions of the documents a scenario requires; null while any of them is unknown. */
export function acceptedDocuments(catalog: LegalCatalog | null, codes: LegalDocumentCode[]): AcceptedDocument[] | null {
  const accepted: AcceptedDocument[] = [];
  for (const code of codes) {
    const document = findDocument(catalog, code);
    if (!document) return null;
    accepted.push({ code, version: document.version });
  }
  return accepted;
}

/** One line for footers: name and registration numbers, or a promise to publish them. */
export function sellerLine(seller: Seller | null): string {
  if (!seller?.published) return 'Реквизиты продавца будут опубликованы до начала продаж';
  return seller.name + ' · ИНН ' + seller.inn + ' · ОГРНИП ' + seller.ogrnip;
}

/** A draft of buyer contacts is kept only while it is fresh; drafts without a moment are treated as stale. */
export function isDraftFresh(savedAt: unknown, now: number): boolean {
  return typeof savedAt === 'number' && savedAt <= now && now - savedAt < CHECKOUT_DRAFT_TTL_MS;
}

export function formatEffectiveDate(value: string): string {
  const [year, month, day] = value.split('-');
  return day && month && year ? day + '.' + month + '.' + year : value;
}
