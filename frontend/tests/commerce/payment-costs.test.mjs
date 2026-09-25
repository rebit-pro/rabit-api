import test from 'node:test';
import assert from 'node:assert/strict';
import { rateInputValue, rateText, salePrice } from '../../src/modules/morefoto/conditions/payment-costs.ts';
import {
  conditionsAttempt,
  conditionsCommandErrors,
  createConditionsCommand
} from '../../src/modules/morefoto/conditions/conditions-command.ts';

const snapshot = {
  revision: 4,
  catalogRevision: 5,
  products: [
    {
      id: 'p1',
      name: 'Фото 10×15',
      description: '',
      kind: 'physical',
      price: 25000,
      salePrice: 30000,
      printCount: 1,
      format: '10x15',
      unit: 'шт.',
      staffDiscount: true,
      active: true
    }
  ],
  giftThreshold: 0,
  giftForStaff: false,
  paymentCosts: { enabled: true, rateBps: 380, roundingStep: 5000, maxRateBps: 1000 }
};

test('sale price matches the server examples', () => {
  const policy = { enabled: true, rateBps: 380 };
  assert.deepEqual(
    [25000, 50000, 100000, 200000, 96200, 0].map((base) => salePrice(base, policy)),
    [30000, 55000, 105000, 210000, 100000, 0]
  );
  assert.equal(salePrice(50123, { enabled: false, rateBps: 380 }), 50123);
  assert.equal(salePrice(50123, { enabled: true, rateBps: 0 }), 55000);
  assert.equal(salePrice(100000000, { enabled: true, rateBps: 1000 }), 111115000);
});

test('sale price is the minimal rounded compensation across a range', () => {
  for (const rateBps of [0, 1, 280, 350, 380, 999, 1000]) {
    for (let base = 0; base <= 200000; base += 137) {
      const price = salePrice(base, { enabled: true, rateBps });
      assert.equal(price % 5000, 0);
      assert.ok(price * (10000 - rateBps) >= base * 10000);
      if (price > 0) assert.ok((price - 5000) * (10000 - rateBps) < base * 10000);
    }
  }
});

test('rate input accepts percents with two decimals up to the cap', () => {
  assert.deepEqual(
    ['3,8', '3.80', ' 3,8 ', '0', '10', '10,00'].map((value) => rateInputValue(value, 1000)),
    [380, 380, 380, 0, 1000, 1000]
  );
  assert.deepEqual(
    ['10,01', '3,805', '-1', '', 'abc', '100'].map((value) => rateInputValue(value, 1000)),
    [null, null, null, null, null, null]
  );
  assert.equal(rateText(380), '3,80');
});

test('global command sends the payment cost policy, group command does not', () => {
  const global = createConditionsCommand({ snapshot, groupId: null });
  assert.deepEqual(global.paymentCosts, { enabled: true, rate: '3,80', maxRateBps: 1000, savedRateBps: 380 });
  assert.deepEqual(conditionsAttempt(global).body.paymentCosts, { enabled: true, rateBps: 380 });
  const group = createConditionsCommand({ snapshot: { ...snapshot, conditionsRevision: 4, inherit: false }, groupId: 'g1' });
  assert.equal(group.paymentCosts, undefined);
  assert.equal('paymentCosts' in conditionsAttempt(group).body, false);
});

test('editor rejects a rate above the cap and a price above 1 000 000 RUB', () => {
  const command = createConditionsCommand({ snapshot, groupId: null });
  command.paymentCosts.rate = '10,01';
  command.products[0].price = '1000000,01';
  const errors = conditionsCommandErrors(command);
  assert.ok(errors.paymentCostRate);
  assert.ok(errors['price:p1']);
  command.paymentCosts.rate = '10';
  command.products[0].price = '1000000';
  assert.deepEqual(conditionsCommandErrors(command), {});
});

test('#68: switching the policy off is saved with an invalid draft rate and keeps the saved rate', () => {
  const command = createConditionsCommand({ snapshot, groupId: null });
  command.paymentCosts.rate = '10,5';
  assert.ok(conditionsCommandErrors(command).paymentCostRate);
  command.paymentCosts.enabled = false;
  assert.deepEqual(conditionsCommandErrors(command), {});
  assert.deepEqual(conditionsAttempt(command).body.paymentCosts, { enabled: false, rateBps: 380 });
  command.paymentCosts.rate = '5';
  assert.deepEqual(conditionsAttempt(command).body.paymentCosts, { enabled: false, rateBps: 500 });
  command.paymentCosts.enabled = true;
  command.paymentCosts.rate = '10,5';
  assert.ok(conditionsCommandErrors(command).paymentCostRate);
});
