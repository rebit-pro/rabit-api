import test from 'node:test';
import assert from 'node:assert/strict';
import { calculateQuote } from '../../src/modules/morefoto/commerce/services/pricing.ts';
const photo = (id) => ({ id, code: id, thumbSrc: '/preview.webp', previewSrc: '/preview.webp', width: 100, height: 150 });
const gallery = {
  children: [
    { code: 'A', photos: [photo('a1'), photo('a2')] },
    { code: 'B', photos: [photo('b1'), photo('a1')] }
  ],
  audience: 'regular'
};
const catalog = {
  revision: 1,
  giftThreshold: 200000,
  giftForStaff: false,
  products: [
    { id: 'print', kind: 'physical', name: 'Print', price: 100000, printCount: 1, staffDiscount: true, active: true },
    { id: 'pair', kind: 'physical', name: 'Pair', price: 28000, printCount: 2, staffDiscount: true, active: true },
    { id: 'canvas', kind: 'physical', name: 'Canvas', price: 240000, printCount: 1, staffDiscount: false, active: true },
    { id: 'digital', kind: 'digital', name: 'File', price: 25000, printCount: 0, staffDiscount: true, active: true },
    { id: 'bundle', kind: 'bundle', name: 'Bundle', price: 100000, printCount: 0, staffDiscount: true, active: true }
  ]
};
const line = (childCode, photoId, productId, quantity = 1) => ({ id: childCode + productId, childCode, photoId, productId, quantity });
test('two identical prints are one priced unit with a multiplier of two for production', () => {
  const quote = calculateQuote(gallery, [line('A', 'a1', 'pair', 3)], catalog);
  assert.equal(quote.total, 84000);
  assert.equal(quote.lines[0].quantity * quote.lines[0].product.printCount, 6);
});
test('gift threshold never combines different children', () => {
  const quote = calculateQuote(gallery, [line('A', 'a1', 'print'), line('B', 'b1', 'print')], catalog);
  assert.equal(quote.total, 200000);
  assert.deepEqual(quote.gifts, []);
});
test('digital purchases do not count toward the printed threshold', () => {
  const quote = calculateQuote(gallery, [line('A', 'a1', 'print'), line('A', null, 'bundle')], catalog);
  assert.deepEqual(quote.gifts, []);
  assert.equal(quote.total, 200000);
});
test('gift covers only the bundle and never a separate digital frame', () => {
  const source = [line('A', 'a1', 'print', 2), line('A', 'a1', 'digital'), line('A', null, 'bundle')];
  const eligible = calculateQuote(gallery, source, catalog);
  assert.deepEqual(eligible.gifts, ['A']);
  assert.equal(eligible.total, 225000);
  assert.equal(eligible.giftSaving, 100000);
  assert.equal(eligible.lines.find((item) => item.productId === 'digital').coveredByGift, false);
  assert.equal(eligible.lines.find((item) => item.productId === 'bundle').coveredByGift, true);
  const removed = calculateQuote(gallery, [{ ...source[0], quantity: 1 }, source[1]], catalog);
  assert.deepEqual(removed.gifts, []);
  assert.equal(removed.total, 125000);
});
test('the same photo identifier is evaluated independently for each child', () => {
  const quote = calculateQuote(gallery, [line('A', 'a1', 'print', 2), line('B', 'a1', 'print', 2)], catalog);
  assert.deepEqual(quote.gifts, ['A', 'B']);
  assert.equal(quote.total, 400000);
});
test('staff discount applies only to configured products', () => {
  const quote = calculateQuote({ ...gallery, audience: 'staff' }, [line('A', 'a1', 'print'), line('A', 'a2', 'canvas')], catalog);
  assert.equal(quote.total, 290000);
  assert.equal(quote.discount, 50000);
  assert.deepEqual(quote.gifts, []);
});
test('staff gift uses printed total after discount when configured', () => {
  const staff = { ...gallery, audience: 'staff' },
    enabled = { ...catalog, giftForStaff: true };
  assert.deepEqual(calculateQuote(staff, [line('A', 'a1', 'print', 3)], enabled).gifts, []);
  assert.deepEqual(calculateQuote(staff, [line('A', 'a1', 'print', 4)], enabled).gifts, ['A']);
});
test('staff final unit price rounds an odd kopek half up', () => {
  const odd = {
    ...catalog,
    products: catalog.products.map((product) => (product.id === 'print' ? { ...product, price: 101 } : product))
  };
  const quote = calculateQuote({ ...gallery, audience: 'staff' }, [line('A', 'a1', 'print')], odd);
  assert.equal(quote.lines[0].unitPrice, 51);
  assert.equal(quote.discount, 50);
  assert.equal(quote.total, 51);
});
test('gift wins over staff discount without stacking both reductions', () => {
  const quote = calculateQuote({ ...gallery, audience: 'staff' }, [line('A', 'a1', 'canvas'), line('A', null, 'bundle')], {
    ...catalog,
    giftForStaff: true
  });
  assert.equal(quote.giftSaving, 100000);
  assert.equal(quote.discount, 0);
  assert.equal(quote.total, 240000);
});
test('unknown photos or unavailable products block checkout through invalid lines', () => {
  const quote = calculateQuote(gallery, [line('A', 'b1', 'print'), line('A', 'a1', 'unknown')], catalog);
  assert.equal(quote.invalid.length, 2);
  assert.equal(quote.total, 0);
});
test('digital quantity remains one even with stale persisted data', () => {
  const quote = calculateQuote(gallery, [line('A', 'a1', 'digital', 7)], catalog);
  assert.equal(quote.total, 25000);
  assert.equal(quote.count, 1);
});
