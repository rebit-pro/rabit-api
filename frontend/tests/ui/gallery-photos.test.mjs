import test from 'node:test';
import assert from 'node:assert/strict';
import { galleryPhotos } from '../../src/modules/morefoto/gallery/photos.ts';

const photo = (id, assignmentId = id) => ({
  id,
  assignmentId,
  filename: 'IMG_0001.JPEG'
});

test('all children stay together in letter order, with the original frame order', () => {
  const children = [
    { code: 'AA', photos: [photo('aa1')] },
    { code: 'B', photos: [photo('b2'), photo('b1')] },
    { code: 'AB', photos: [photo('ab1')] },
    { code: 'Z', photos: [photo('z1')] },
    { code: 'A', photos: [photo('a1')] }
  ];
  const before = structuredClone(children);
  assert.deepEqual(
    galleryPhotos(children).map((item) => item.id),
    ['a1', 'b2', 'b1', 'z1', 'aa1', 'ab1']
  );
  assert.deepEqual(children, before);
});

test('shared frames and identical filenames keep each separate assignment', () => {
  const first = photo('shared', 'assignment-a');
  const second = photo('shared', 'assignment-b');
  assert.deepEqual(
    galleryPhotos([
      { code: 'A', photos: [first] },
      { code: 'B', photos: [second] }
    ]),
    [first, second]
  );
  assert.equal(first.filename, 'IMG_0001.JPEG');
});

test('empty children and a group without frames produce an empty list', () => {
  assert.deepEqual(galleryPhotos([]), []);
  assert.deepEqual(galleryPhotos([{ code: 'A', photos: [] }]), []);
});
