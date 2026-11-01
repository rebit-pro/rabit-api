import test from 'node:test';
import assert from 'node:assert/strict';
import { validateBuyer, normalizeBuyer } from '../../src/modules/morefoto/orders/services/validation.ts';
const valid = {
  childFirstName: 'Иван',
  childLastName: 'Иванов',
  email: 'TEST@example.test',
  comment: '',
  receiptChannel: 'email',
  reviewed: true
};
test('E8-T05: email is the only contact, normalized without changing the comment', () => {
  assert.deepEqual(validateBuyer(valid, false), {});
  assert.deepEqual(normalizeBuyer({ ...valid, comment: ' забрать в саду ' }), {
    email: 'test@example.test',
    comment: 'забрать в саду',
    receiptChannel: 'email'
  });
});
test('E8-T05: buyer reports the required fields independently, without a name or a phone', () => {
  assert.deepEqual(Object.keys(validateBuyer({ ...valid, email: 'bad', reviewed: false }, false)), ['email', 'reviewed']);
  assert.ok(validateBuyer({ ...valid, email: '   ' }, false).email);
});
test('MAX requires availability, email remains an alternative', () => {
  assert.ok(validateBuyer({ ...valid, receiptChannel: 'max' }, false).receiptChannel);
  assert.deepEqual(validateBuyer({ ...valid, receiptChannel: 'max' }, true), {});
  assert.deepEqual(validateBuyer(valid, false), {});
});
test('comment is optional and the length boundary is enforced', () => {
  assert.deepEqual(validateBuyer({ ...valid, comment: 'a'.repeat(1000) }, false), {});
  assert.ok(validateBuyer({ ...valid, comment: 'a'.repeat(1001) }, false).comment);
});
