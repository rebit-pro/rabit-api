import test from 'node:test';
import assert from 'node:assert/strict';
import { printIncludesCopy } from '../../src/modules/morefoto/commerce/print-copy.ts';
import { digitalEntitlements } from '../../src/modules/morefoto/orders/services/entitlements.ts';

const photo = (id, code) => ({ id, code });
const line = (kind, photoId, childCode = 'A') => ({
  product: { kind },
  photoId,
  photo: photoId ? photo(photoId, photoId) : null,
  childCode
});
const gallery = {
  children: [
    { code: 'A', photos: [photo('a1', 'A-01'), photo('a2', 'A-02')] },
    { code: 'B', photos: [photo('b1', 'B-01'), photo('b2', 'B-02')] }
  ]
};

test('J4-T05: a print of the frame in the cart brings its digital copy, other frames do not', () => {
  const quote = { lines: [line('physical', 'a1'), line('physical', 'a1'), line('digital', 'b1', 'B')] };
  assert.equal(printIncludesCopy(quote, 'a1'), true);
  assert.equal(printIncludesCopy(quote, 'b1'), false, 'A digital line is not a print.');
  assert.equal(printIncludesCopy(quote, 'a2'), false);
  assert.equal(printIncludesCopy({ lines: [line('bundle', null)] }, 'a1'), false);
});

test('J4-T05: the demo order gives each printed frame once, next to digital lines and the gift set', () => {
  const quote = { lines: [line('physical', 'a1'), line('physical', 'a1'), line('digital', 'a1')], gifts: ['B'] };
  assert.deepEqual(
    digitalEntitlements(gallery, quote).map((item) => item.id),
    ['a1', 'b1', 'b2']
  );
  assert.deepEqual(
    digitalEntitlements(gallery, { lines: [line('physical', 'a2')], gifts: [] }).map((item) => item.id),
    ['a2']
  );
});
