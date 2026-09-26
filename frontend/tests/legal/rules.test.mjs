import test from 'node:test';
import assert from 'node:assert/strict';
import {
  acceptedDocuments,
  CHECKOUT_DRAFT_TTL_MS,
  documentPath,
  formatEffectiveDate,
  isDraftFresh,
  ORDER_DOCUMENTS,
  sellerLine,
  STAFF_DOCUMENTS
} from '../../src/modules/morefoto/legal/rules.ts';

const catalog = {
  documents: [
    { code: 'privacy', version: '2026-09-25', title: 'Политика', effectiveFrom: '2026-09-25' },
    { code: 'offer', version: '2026-10-01', title: 'Оферта', effectiveFrom: '2026-10-01' },
    { code: 'buyer-consent', version: '2026-09-25', title: 'Согласие покупателя', effectiveFrom: '2026-09-25' },
    { code: 'staff-consent', version: '2026-09-25', title: 'Согласие сотрудника', effectiveFrom: '2026-09-25' }
  ],
  seller: { published: false, name: null, inn: null, ogrnip: null, address: null, email: null, phone: null }
};

test('an order accepts the buyer consent and the offer, staff only its own consent', () => {
  assert.deepEqual(acceptedDocuments(catalog, ORDER_DOCUMENTS), [
    { code: 'buyer-consent', version: '2026-09-25' },
    { code: 'offer', version: '2026-10-01' }
  ]);
  assert.deepEqual(acceptedDocuments(catalog, STAFF_DOCUMENTS), [{ code: 'staff-consent', version: '2026-09-25' }]);
});

test('without a loaded catalog nothing can be accepted', () => {
  assert.equal(acceptedDocuments(null, ORDER_DOCUMENTS), null);
  assert.equal(acceptedDocuments({ ...catalog, documents: catalog.documents.slice(0, 1) }, ORDER_DOCUMENTS), null);
});

test('the seller line promises requisites until they are published', () => {
  assert.equal(sellerLine(null), 'Реквизиты продавца будут опубликованы до начала продаж');
  assert.equal(sellerLine(catalog.seller), 'Реквизиты продавца будут опубликованы до начала продаж');
  const published = {
    published: true,
    name: 'ИП Тестов Т. Т.',
    inn: '366200000000',
    ogrnip: '300000000000000',
    address: 'Воронеж',
    email: 'pd@example.test',
    phone: null
  };
  assert.equal(sellerLine(published), 'ИП Тестов Т. Т. · ИНН 366200000000 · ОГРНИП 300000000000000');
});

test('checkout contacts are kept for a week at most', () => {
  const now = Date.UTC(2026, 8, 25);
  assert.equal(isDraftFresh(now - 1000, now), true);
  assert.equal(isDraftFresh(now - CHECKOUT_DRAFT_TTL_MS, now), false);
  assert.equal(isDraftFresh(undefined, now), false);
  assert.equal(isDraftFresh(now + 60_000, now), false);
});

test('document links and dates', () => {
  assert.equal(documentPath('privacy'), '/legal/privacy');
  assert.equal(documentPath('offer', '2026-10-01'), '/legal/offer/v/2026-10-01');
  assert.equal(formatEffectiveDate('2026-09-25'), '25.09.2026');
});
