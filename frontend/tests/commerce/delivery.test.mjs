import test from 'node:test';
import assert from 'node:assert/strict';
import { downloadAccess, downloadDeadline, validateSupport } from '../../src/modules/morefoto/orders/delivery/rules.ts';
import { storedZip } from '../../src/modules/morefoto/orders/delivery/zip.ts';
const photo = { id: 'photo-1', code: 'A001-01' };
const order = { paymentStatus: 'paid', paidAt: '2026-09-07T09:00:00Z', digitalPhotos: [photo], quote: { lines: [] } };
test('download month clamps month-end in the Moscow calendar and preserves time', () => {
  assert.equal(downloadDeadline('2026-01-31T20:30:00Z'), '2026-02-28T20:30:00.000Z');
  assert.equal(downloadDeadline('2026-01-31T22:30:00Z'), '2026-02-28T22:30:00.000Z');
  assert.equal(downloadDeadline('2028-01-31T10:00:00Z'), '2028-02-29T10:00:00.000Z');
  assert.equal(downloadDeadline('2026-12-31T10:00:00Z'), '2027-01-31T10:00:00.000Z');
});
test('paid entitlements last independently of group closure, excluding exact deadline', () => {
  assert.equal(downloadAccess({ ...order, closesAt: '2026-09-10T00:00:00Z' }, '2026-09-20T00:00:00Z').state, 'available');
  assert.equal(downloadAccess(order, '2026-10-07T08:59:59.999Z').state, 'available');
  assert.equal(downloadAccess(order, '2026-10-07T09:00:00Z').state, 'expired');
  assert.equal(downloadAccess(order, '2026-09-01T00:00:00Z').state, 'unpaid');
});
test('unpaid, pending, late confirmation and physical-only orders have no download access', () => {
  for (const paymentStatus of ['unpaid', 'pending', 'declined'])
    assert.equal(downloadAccess({ ...order, paymentStatus }, order.paidAt).state, 'unpaid');
  assert.equal(downloadAccess({ ...order, latePayment: true }, order.paidAt).state, 'review');
  assert.equal(downloadAccess({ ...order, digitalPhotos: [] }, order.paidAt).state, 'empty');
});
test('support validates topic, reply email, text and photo ownership', () => {
  const draft = {
    requestId: 'x',
    topic: 'files',
    photoId: photo.id,
    replyEmail: 'fixed@example.test',
    message: 'Не скачивается фотография'
  };
  assert.deepEqual(validateSupport(draft, order), {});
  const errors = validateSupport({ ...draft, topic: 'unknown', photoId: 'foreign', replyEmail: 'bad', message: '  ' }, order);
  assert.deepEqual(Object.keys(errors).sort(), ['message', 'photoId', 'replyEmail', 'topic']);
});
test('ZIP refuses empty archives and unsafe paths', () => {
  assert.throws(() => storedZip([]), /число/);
  assert.throws(() => storedZip([{ name: '../file.webp', data: new Uint8Array() }]), /имя/);
});
