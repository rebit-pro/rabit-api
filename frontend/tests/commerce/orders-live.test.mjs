import test from 'node:test';
import assert from 'node:assert/strict';
import {
  checkoutOutcome,
  isCreatedOrder,
  newRequestId,
  orderQuoteAsCart,
  staffFiltersFromQuery,
  staffOrderError,
  staffOrderParams
} from '../../src/modules/morefoto/orders/live/rules.ts';

const problem = (code, status = 409) => ({ status, code, network: false });

test('only an unanswered order request may be repeated with the same idempotency key', () => {
  assert.deepEqual(checkoutOutcome({ status: null, code: '', network: true }), { kind: 'unknown' });
  for (const code of ['PRICE_CHANGED', 'QUOTE_STALE', 'QUOTE_EXPIRED', 'QUOTE_ALREADY_USED']) {
    assert.equal(checkoutOutcome(problem(code)).kind, 'recalculate', code);
  }
  assert.equal(checkoutOutcome(problem('GALLERY_CLOSED')).kind, 'message');
  assert.equal(checkoutOutcome(problem('PURCHASE_DISABLED', 403)).kind, 'message');
});

test('server field codes land on the matching checkout field', () => {
  assert.deepEqual(Object.keys(checkoutOutcome(problem('INVALID_BUYER_EMAIL', 422)).errors), ['email']);
  assert.deepEqual(Object.keys(checkoutOutcome(problem('REVIEW_REQUIRED', 422)).errors), ['reviewed']);
  assert.deepEqual(Object.keys(checkoutOutcome(problem('RECEIPT_CHANNEL_UNAVAILABLE', 422)).errors), ['receiptChannel']);
});

test('order lines never invent image URLs; a trusted thumbnail source is optional', () => {
  const quote = {
    lines: [
      {
        id: 'l1',
        assignmentId: 'a1',
        childCode: 'A',
        photoId: 'p1',
        productId: 'x',
        quantity: 1,
        product: {},
        photo: { id: 'p1', assignmentId: 'a1', code: 'A001', width: 1, height: 1 },
        unitPrice: 1,
        total: 1,
        discount: 0,
        coveredByGift: false
      },
      {
        id: 'l2',
        assignmentId: 'a1',
        childCode: 'A',
        photoId: null,
        productId: 'y',
        quantity: 1,
        product: {},
        photo: null,
        unitPrice: 1,
        total: 1,
        discount: 0,
        coveredByGift: false
      }
    ],
    total: 2,
    subtotal: 2,
    discount: 0,
    giftSaving: 0,
    gifts: [],
    count: 2,
    invalid: [],
    revision: 1,
    conditionsRevision: 1
  };
  assert.equal(orderQuoteAsCart(quote).lines[0].photo.thumbSrc, '');
  assert.equal(orderQuoteAsCart(quote).lines[1].photo, null);
  assert.equal(orderQuoteAsCart(quote, (id) => '/api/v1/photos/' + id + '/thumb').lines[0].photo.thumbSrc, '/api/v1/photos/p1/thumb');
});

test('staff filters come from the URL and only non-empty values reach the API', () => {
  const filters = staffFiltersFromQuery({ q: 'MF-000001', paymentStatus: 'unpaid', page: '3', late: 'true' });
  assert.equal(filters.page, 3);
  assert.deepEqual(staffOrderParams(filters), { page: 3, pageSize: 25, q: 'MF-000001', paymentStatus: 'unpaid' });
  assert.equal(staffFiltersFromQuery({ page: '-1' }).page, 1);
});

test('staff errors explain scope without revealing other orders', () => {
  assert.match(staffOrderError(problem('FORBIDDEN', 403)), /организатору и куратору/);
  assert.match(staffOrderError(problem('ORDER_NOT_FOUND', 404)), /не найден в вашей области/);
  assert.match(staffOrderError(problem('ACCESS_CHANGED')), /права изменились/);
});

test('idempotency keys are 32 lowercase hex characters', () => {
  assert.match(newRequestId(), /^[a-f0-9]{32}$/);
  assert.equal(
    newRequestId(() => 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee'),
    'aaaaaaaabbbb4ccc8dddeeeeeeeeeeee'
  );
});

test('5xx, proxy pages and unexpected failures keep the attempt for a safe repeat', () => {
  for (const status of [500, 502, 503, 504]) assert.deepEqual(checkoutOutcome({ status, code: '', network: false }), { kind: 'unknown' });
  assert.deepEqual(checkoutOutcome({ status: null, code: '', network: false }), { kind: 'unknown' });
  assert.equal(checkoutOutcome({ status: 400, code: 'MALFORMED_JSON', network: false }).kind, 'message');
  assert.equal(checkoutOutcome({ status: 404, code: 'GALLERY_NOT_FOUND', network: false }).kind, 'message');
});

test('a success status counts only with a real order and personal key', () => {
  assert.equal(isCreatedOrder({ id: 'order', number: 'MF-000001', accessKey: 'a'.repeat(64) }), true);
  assert.equal(isCreatedOrder('<html>Bad gateway</html>'), false);
  assert.equal(isCreatedOrder({ id: 'order', number: 'MF-000001', accessKey: 'undefined' }), false);
  assert.equal(isCreatedOrder(null), false);
});
