import { test } from 'node:test';
import assert from 'node:assert/strict';
import { sortGalleryChildren } from '../../src/modules/morefoto/gallery/series.ts';
import { filterGalleryPhotos } from '../../src/modules/morefoto/gallery/filter.ts';

test('gallery series follow A-Z, AA-ZZ, AAA order without changing the source or photo assignments', () => {
  const codes = ['B', 'CC', 'AAA', 'A', 'BB', 'AB', 'Z', 'ZZ', 'AA'];
  const children = codes.map((code) => Object.freeze({ code, photos: Object.freeze([{ code: code + '001' }]) }));
  Object.freeze(children);
  const sorted = sortGalleryChildren(children);
  assert.deepEqual(
    sorted.map((child) => child.code),
    ['A', 'B', 'Z', 'AA', 'AB', 'BB', 'CC', 'ZZ', 'AAA']
  );
  assert.deepEqual(
    children.map((child) => child.code),
    codes
  );
  for (const child of children) {
    assert.strictEqual(
      sorted.find((item) => item.code === child.code),
      child
    );
    assert.deepEqual(filterGalleryPhotos(sorted, child.code), child.photos);
  }
});

test('all 37 current gallery codes sort in their assigned order, including empty and single series', () => {
  const codes = [
    ...Array.from({ length: 26 }, (_, index) => String.fromCharCode(65 + index)),
    ...Array.from({ length: 11 }, (_, index) => 'A' + String.fromCharCode(65 + index))
  ];
  const children = codes.toReversed().map((code) => ({ code, photos: [] }));
  assert.deepEqual(
    sortGalleryChildren(children).map((child) => child.code),
    codes
  );
  assert.deepEqual(sortGalleryChildren([]), []);
  assert.deepEqual(sortGalleryChildren([children[0]]), [children[0]]);
});
