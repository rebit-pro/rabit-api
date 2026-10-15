import test from 'node:test';
import assert from 'node:assert/strict';
import { frameLine, frameSummary } from '../../src/modules/morefoto/commerce/frame-lines.ts';

// #192: each product row of a frame shows what the cart already holds for it.
const line = (productId, kind, photoId, quantity, total, childCode = 'A') => ({
  id: productId + ':' + (photoId ?? childCode),
  productId,
  childCode,
  photoId,
  photo: photoId ? { id: photoId } : null,
  quantity,
  total,
  product: { id: productId, kind }
});
const quote = {
  lines: [
    line('print-10', 'physical', 'p1', 2, 36000),
    line('magnet', 'physical', 'p1', 1, 35000),
    line('digital', 'digital', 'p2', 1, 25000),
    line('bundle', 'bundle', null, 1, 100000)
  ]
};

test('a row finds its own frame line, a bundle row — the line of the child series', () => {
  assert.equal(frameLine(quote, 'p1', 'A', { id: 'print-10', kind: 'physical' })?.quantity, 2);
  assert.equal(frameLine(quote, 'p2', 'A', { id: 'print-10', kind: 'physical' }), undefined);
  assert.equal(frameLine(quote, 'p1', 'A', { id: 'digital', kind: 'digital' }), undefined);
  assert.equal(frameLine(quote, 'p1', 'A', { id: 'bundle', kind: 'bundle' })?.id, 'bundle:A');
  assert.equal(frameLine(quote, 'p1', 'B', { id: 'bundle', kind: 'bundle' }), undefined);
});

test('the frame summary counts prints by quantity and leaves the series bundle out', () => {
  assert.deepEqual(frameSummary(quote, 'p1'), { count: 3, total: 71000 });
  assert.deepEqual(frameSummary(quote, 'p2'), { count: 1, total: 25000 });
  assert.deepEqual(frameSummary(quote, 'p3'), { count: 0, total: 0 });
});
