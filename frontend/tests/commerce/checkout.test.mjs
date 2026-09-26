import test from 'node:test';
import assert from 'node:assert/strict';
import { validateBuyer, normalizeBuyer } from '../../src/modules/morefoto/orders/services/validation.ts';
const valid = {
  name: 'Тестовый покупатель',
  phone: '+7 (900) 123-45-67',
  email: 'TEST@example.test',
  comment: '',
  receiptChannel: 'email',
  reviewed: true
};
test('buyer accepts formatted phones and normalizes contacts without changing the comment', () => {
  assert.deepEqual(validateBuyer(valid, false), {});
  assert.equal(normalizeBuyer({ ...valid, phone: '8 900 123-45-67' }).phone, '+79001234567');
  assert.equal(normalizeBuyer({ ...valid, phone: '7 900 123-45-67' }).phone, '+79001234567');
  assert.equal(normalizeBuyer({ ...valid, phone: '+852 9123 4567' }).phone, '+85291234567');
  assert.equal(normalizeBuyer({ ...valid, phone: '+8 900 123-45-67' }).phone, '+89001234567');
  assert.equal(normalizeBuyer(valid).email, 'test@example.test');
});
test('buyer reports the required fields independently', () => {
  assert.deepEqual(Object.keys(validateBuyer({ ...valid, name: '', phone: '12', email: 'bad', reviewed: false }, false)), [
    'name',
    'phone',
    'email',
    'reviewed'
  ]);
});
test('phone allows international formats but rejects letters and excessive digits', () => {
  assert.deepEqual(validateBuyer({ ...valid, phone: '+44 20 7946 0123' }, false), {});
  assert.ok(validateBuyer({ ...valid, phone: '+7 hello 9001234567' }, false).phone);
  assert.ok(validateBuyer({ ...valid, phone: '1'.repeat(16) }, false).phone);
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
