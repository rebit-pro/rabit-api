import test from 'node:test';
import assert from 'node:assert/strict';
import { finishPayment } from '../../src/modules/morefoto/orders/services/payment-rules.ts';
const close = '2026-09-12T15:00:00.000Z';
function pending() {
  return {
    paymentStatus: 'pending',
    productionStatus: 'not-started',
    quote: { total: 18000 },
    digitalPhotos: [],
    paymentAttempts: [{ id: 'a1', requestId: 'r1', amount: 18000, status: 'pending', startedAt: '2026-09-07T09:00:00.000Z' }],
    history: []
  };
}
test('confirmation preserves the attempted amount and independent manufacturing state', () => {
  const order = pending();
  const result = finishPayment(order, 'a1', 'paid', '2026-09-07T09:01:00.000Z', close);
  assert.equal(result.paymentStatus, 'paid');
  assert.equal(result.productionStatus, 'not-started');
  assert.deepEqual(result.quote, { total: 18000 });
  assert.equal(result.paymentAttempts[0].amount, 18000);
  assert.equal(order.paymentStatus, 'pending');
  assert.equal(result.latePayment, false);
  assert.equal(result.history.length, 1);
});
test('decline is definitive and does not invent a paid date', () => {
  const result = finishPayment(pending(), 'a1', 'declined', '2026-09-07T09:01:00.000Z', close);
  assert.equal(result.paymentStatus, 'declined');
  assert.equal(result.paidAt, undefined);
});
test('duplicate or conflicting callbacks cannot reverse a confirmed payment', () => {
  const paid = finishPayment(pending(), 'a1', 'paid', '2026-09-07T09:01:00.000Z', close);
  assert.equal(finishPayment(paid, 'a1', 'paid', '2026-09-09T09:00:00.000Z', close), paid);
  assert.equal(finishPayment(paid, 'a1', 'declined', '2026-09-09T09:00:00.000Z', close), paid);
  assert.equal(paid.history.length, 1);
});
test('confirmation at closing boundary and after it is retained as late', () => {
  assert.equal(finishPayment(pending(), 'a1', 'paid', close, close).latePayment, true);
  assert.equal(finishPayment(pending(), 'a1', 'paid', '2026-09-13T09:00:00.000Z', close).latePayment, true);
});
test('stale declined callback cannot affect the next pending attempt', () => {
  const order = pending();
  order.paymentAttempts[0].status = 'declined';
  order.paymentAttempts.push({ id: 'a2', requestId: 'r2', amount: 18000, status: 'pending', startedAt: '2026-09-08T09:00:00.000Z' });
  assert.equal(finishPayment(order, 'a1', 'paid', '2026-09-08T09:01:00.000Z', close), order);
  assert.equal(order.paymentStatus, 'pending');
  assert.equal(order.paymentAttempts[1].status, 'pending');
});
test('foreign attempt and backward demo time are rejected', () => {
  assert.throws(() => finishPayment(pending(), 'foreign', 'paid', '2026-09-08T09:00:00.000Z', close), /не относится/);
  assert.throws(() => finishPayment(pending(), 'a1', 'paid', '2026-09-06T09:00:00.000Z', close), /раньше начала/);
});
