import test from 'node:test';
import assert from 'node:assert/strict';
import {
  quantityValue,
  phoneDigits,
  displayPhone,
  formatPhone,
  moneyInputValue,
  dateInputValid
} from '../../src/modules/morefoto/ui/field-values.ts';

test('quantity keeps empty and invalid edits out of a committed cart value', () => {
  for (const value of ['', null, '0', '-1', '1.5', '1e2', '100', 'abc']) assert.equal(quantityValue(value), null);
  assert.equal(quantityValue('1'), 1);
  assert.equal(quantityValue('99'), 99);
});
test('Russian formatting preserves digits and existing buyer normalization', () => {
  assert.equal(displayPhone('8 (900) 123 45 67'), '+7 (900) 123-45-67');
  assert.equal(displayPhone('+79001234567'), '+7 (900) 123-45-67');
  assert.equal(displayPhone('+7 (900) 123-45-67'), '+7 (900) 123-45-67');
  assert.equal(displayPhone('7 900 123 45 67'), '+7 (900) 123-45-67');
});
test('explicit international plus is never read as the Russian national 8', () => {
  for (const value of ['+85291234567', '+852 9123 4567', '(+852) 9123-4567', '+8 900 123-45-67']) {
    assert.equal(displayPhone(value), value);
    assert.equal(formatPhone(value), value);
  }
});
test('stored phone digits follow the backend BuyerPolicy rule', () => {
  const cases = [
    ['+85291234567', '85291234567'],
    ['+852 9123 4567', '85291234567'],
    ['(+852) 9123-4567', '85291234567'],
    ['+8 900 123-45-67', '89001234567'],
    ['+49 30 1234567', '49301234567'],
    ['89001234567', '79001234567'],
    ['8 (900) 123-45-67', '79001234567'],
    ['+7 900 123-45-67', '79001234567'],
    ['79001234567', '79001234567']
  ];
  for (const [value, digits] of cases) assert.equal(phoneDigits(value), digits, value);
});
test('formatting never hides invalid symbols or truncates international numbers', () => {
  for (const value of ['', '+49 30 123456', '+123456789012345', 'телефон 79001234567', '+7900']) assert.equal(displayPhone(value), value);
});
test('stored phones read as +7 900 123-45-67 on order pages and in staff orders', () => {
  for (const value of ['+79001234567', '79001234567', '89001234567', '8 (900) 123 45 67', '+7 (900) 123-45-67']) {
    assert.equal(formatPhone(value), '+7 900 123-45-67');
  }
});
test('display formatting leaves non-standard and foreign phones as stored', () => {
  for (const value of ['', '+49 30 123456', '+123456789012345', 'телефон 79001234567', '+7900']) assert.equal(formatPhone(value), value);
});
test('money parses decimal rubles into exact integer kopecks', () => {
  assert.equal(moneyInputValue('1 234,56'), 123456);
  assert.equal(moneyInputValue('0.01'), 1);
  assert.equal(moneyInputValue('180'), 18000);
  for (const value of ['', '1,234', '-1', '1e3', '9007199254740991.00']) assert.equal(moneyInputValue(value), null);
});
test('date input rejects impossible calendar days and accepts a leap day', () => {
  assert.equal(dateInputValid('2024-02-29'), true);
  for (const value of ['', '2026-02-29', '2026-04-31', '2026-13-01', '07.09.2026']) assert.equal(dateInputValid(value), false);
});
