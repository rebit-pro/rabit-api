import test from 'node:test';
import assert from 'node:assert/strict';
import {
  forgetOrderKey,
  isFinal,
  nextPollDelay,
  paymentError,
  paymentFiltersFromQuery,
  paymentParams,
  recallOrderKey,
  rememberOrderKey,
  staffPaymentError
} from '../../src/modules/morefoto/orders/live/payment-rules.ts';

const key = 'a'.repeat(64);
function memoryStorage() {
  const data = new Map();
  return {
    data,
    getItem: (name) => (data.has(name) ? data.get(name) : null),
    setItem: (name, value) => data.set(name, String(value)),
    removeItem: (name) => data.delete(name)
  };
}

test('G1-DEC-04: the order key waits on this device by attempt ID and is forgotten after the result', () => {
  const storage = memoryStorage();
  rememberOrderKey(storage, 'attempt-1', key);
  assert.equal(recallOrderKey(storage, 'attempt-1'), key);
  assert.equal(recallOrderKey(storage, 'attempt-2'), null);
  forgetOrderKey(storage, 'attempt-1');
  assert.equal(recallOrderKey(storage, 'attempt-1'), null);
});

test('a tampered or blocked storage never yields a key', () => {
  const storage = memoryStorage();
  storage.setItem('morefoto:payment:attempt-1', 'not-a-key');
  assert.equal(recallOrderKey(storage, 'attempt-1'), null);
  const blocked = {
    getItem: () => {
      throw new Error('denied');
    },
    setItem: () => {
      throw new Error('denied');
    }
  };
  assert.doesNotThrow(() => rememberOrderKey(blocked, 'attempt-1', key));
  assert.equal(recallOrderKey(blocked, 'attempt-1'), null);
});

test('the return page stops waiting only at a final status or after five minutes', () => {
  assert.equal(isFinal({ status: 'succeeded' }), true);
  assert.equal(isFinal({ status: 'canceled' }), true);
  assert.equal(isFinal({ status: 'pending' }), false);
  assert.equal(isFinal({ status: 'unknown' }), false);
  assert.deepEqual([0, 59999, 60000, 299999, 300000].map(nextPollDelay), [3000, 3000, 10000, 10000, null]);
});

test('buyer and staff errors speak about the payment, not HTTP', () => {
  assert.match(paymentError({ status: 409, code: 'PAYMENT_CLOSED', network: false }), /Приём заказов группы завершён/);
  assert.match(paymentError({ status: 409, code: 'QUOTE_CHANGED', network: false }), /обновили данные/);
  assert.match(paymentError({ status: null, code: '', network: true }), /соединение/);
  assert.match(staffPaymentError({ status: 403, code: 'FORBIDDEN', network: false }), /организатору и куратору/);
  assert.match(staffPaymentError({ status: 404, code: 'PAYMENT_ATTEMPT_NOT_FOUND', network: false }), /не найден/);
});

test('registry filters round-trip through the URL and drop unknown values', () => {
  const filters = paymentFiltersFromQuery({ status: 'succeeded', orderNumber: 'MF-0007', late: 'yes', page: '3', dateFrom: '2026-09-01' });
  assert.deepEqual(filters, {
    status: 'succeeded',
    orderNumber: 'MF-0007',
    dateFrom: '2026-09-01',
    dateTo: '',
    late: '',
    page: 3,
    pageSize: 25
  });
  assert.deepEqual(paymentParams(filters), { page: 3, pageSize: 25, status: 'succeeded', orderNumber: 'MF-0007', dateFrom: '2026-09-01' });
  assert.equal(paymentFiltersFromQuery({ status: 'paid' }).status, '');
});

test('review #80: a lost start repeats its own key and method; another method is a new request', async () => {
  const { isUncertain, startKey } = await import('../../src/modules/morefoto/orders/live/payment-rules.ts');
  let issued = 0;
  const fresh = () => 'key-' + ++issued;
  assert.equal(startKey(null, 'sbp', fresh), 'key-1');
  const lost = { method: 'sbp', key: 'key-1' };
  assert.equal(startKey(lost, 'sbp', fresh), 'key-1', 'the same body repeats with the same key');
  assert.equal(startKey(lost, 'bank_card', fresh), 'key-2', 'another method never reuses the key');
  assert.equal(isUncertain({ status: null, code: '', network: true }), true);
  assert.equal(isUncertain({ status: 502, code: '', network: false }), true);
  assert.equal(isUncertain({ status: 429, code: '', network: false }), true);
  assert.equal(isUncertain({ status: 409, code: 'QUOTE_CHANGED', network: false }), false);
  assert.equal(isUncertain({ status: 422, code: 'PAYMENT_METHOD_UNAVAILABLE', network: false }), false);
});

test('review #80: a pending payment with its provider page can be continued from the return page', async () => {
  const { returnState } = await import('../../src/modules/morefoto/orders/live/payment-rules.ts');
  assert.equal(returnState({ status: 'pending', redirectUrl: 'https://yoomoney.ru/checkout/x' }, false), 'continue');
  assert.equal(returnState({ status: 'pending', redirectUrl: null }, false), 'checking');
  assert.equal(returnState({ status: 'unknown', redirectUrl: null }, true), 'waiting');
  assert.equal(returnState({ status: 'succeeded', redirectUrl: null }, false), 'paid');
  assert.equal(returnState({ status: 'canceled', redirectUrl: null }, true), 'failed');
  assert.equal(returnState(null, false), 'checking');
});
