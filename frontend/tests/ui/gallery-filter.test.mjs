import { test } from 'node:test';
import assert from 'node:assert/strict';
import { filterGalleryPhotos } from '../../src/modules/morefoto/gallery/filter.ts';

const photo = (code, id = code, assignmentId = code) => ({
  id,
  assignmentId,
  code,
  thumbSrc: '',
  previewSrc: '',
  width: 100,
  height: 150
});
const series = (code) => ({
  code,
  photos: Array.from({ length: 4 }, (_, index) => photo(code + String(index + 1).padStart(3, '0')))
});
const codes = [
  ...Array.from({ length: 26 }, (_, index) => String.fromCharCode(65 + index)),
  ...Array.from({ length: 11 }, (_, index) => 'A' + String.fromCharCode(65 + index)),
  'BA',
  'BB',
  'CC',
  'AAA',
  'BBB',
  'CCC',
  'ZZZ'
];
const children = codes.map(series);

test('each child code selects exactly its own series, including repeated and shared letters', () => {
  for (const child of children) {
    assert.deepEqual(filterGalleryPhotos(children, child.code), child.photos, child.code);
  }
});

test('clearing the filter returns every assignment in gallery order', () => {
  assert.deepEqual(
    filterGalleryPhotos(children, ''),
    children.flatMap((child) => child.photos)
  );
  assert.deepEqual(
    filterGalleryPhotos(children, '  '),
    children.flatMap((child) => child.photos)
  );
  assert.deepEqual(filterGalleryPhotos([], 'A'), []);
});

test('a missing child code does not match a longer code containing it', () => {
  assert.deepEqual(filterGalleryPhotos(children, 'ZZ'), []);
  assert.deepEqual(filterGalleryPhotos(children, 'UNKNOWN'), []);
});

test('a full frame code finds only that frame, without substring matches', () => {
  assert.deepEqual(filterGalleryPhotos(children, 'A001'), [children[0].photos[0]]);
  assert.deepEqual(filterGalleryPhotos(children, 'AA001'), [children[26].photos[0]]);
  assert.deepEqual(filterGalleryPhotos(children, '001'), []);
});

test('lowercase and surrounding spaces work for both series and frames', () => {
  assert.deepEqual(filterGalleryPhotos(children, ' bb '), children.find((child) => child.code === 'BB').photos);
  assert.deepEqual(filterGalleryPhotos(children, ' aa001 '), [children[26].photos[0]]);
});

test('legacy alphanumeric child codes remain complete separate series', () => {
  const legacy = [
    { code: 'A001', photos: [photo('A001-01'), photo('A001-02')] },
    { code: 'A0011', photos: [photo('A0011-01')] }
  ];
  assert.deepEqual(filterGalleryPhotos(legacy, 'A001'), legacy[0].photos);
  assert.deepEqual(filterGalleryPhotos(legacy, 'A001-02'), [legacy[0].photos[1]]);
});

test('a shared frame keeps the assignment of the chosen child', () => {
  const shared = [
    { code: 'A', photos: [photo('A001', 'shared', 'assignment-a')] },
    { code: 'AA', photos: [photo('AA001', 'shared', 'assignment-aa')] }
  ];
  assert.deepEqual(filterGalleryPhotos(shared, 'A'), shared[0].photos);
  assert.deepEqual(filterGalleryPhotos(shared, 'AA'), shared[1].photos);
  assert.equal(filterGalleryPhotos(shared, '').length, 2);
});
