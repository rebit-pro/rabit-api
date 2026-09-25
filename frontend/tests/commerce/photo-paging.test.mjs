import test from 'node:test';
import assert from 'node:assert/strict';
import { localPhotoPage, photoFilter, photoPage, photoPages, photoPageSize } from '../../src/modules/morefoto/photos/paging.ts';
import { freeChildCode } from '../../src/modules/morefoto/photos/rules.ts';

function photo(id, groupId, codes = []) {
  return {
    id,
    groupId,
    assignments: codes.map((childCode, index) => ({
      childId: groupId + ':' + childCode,
      childCode,
      sequence: index + 1,
      code: childCode + '001'
    }))
  };
}

test('Фото: страница, итог и сводка группы считаются как на сервере', () => {
  const photos = [
    ...Array.from({ length: 130 }, (_, index) => photo('g1-' + index, 'g1', index < 30 ? ['A'] : index < 40 ? ['B', 'AA'] : [])),
    photo('g2-0', 'g2', ['C'])
  ];

  const second = localPhotoPage(photos, 'g1', 'all', 2);
  assert.equal(photoPageSize, 60);
  assert.equal(second.items.length, 60);
  assert.equal(second.items[0].id, 'g1-60');
  assert.equal(second.total, 130);
  assert.deepEqual(second.summary, { photos: 130, unassigned: 90, children: ['A', 'AA', 'B'] });
  assert.equal(localPhotoPage(photos, 'g1', 'all', 3).items.length, 10);

  const unassigned = localPhotoPage(photos, 'g1', 'unassigned', 2);
  assert.equal(unassigned.total, 90);
  assert.equal(unassigned.items.length, 30);
  assert.deepEqual(unassigned.summary, second.summary);

  const child = localPhotoPage(photos, 'g1', 'AA', 1);
  assert.equal(child.total, 10);
  assert.ok(child.items.every((item) => item.assignments.some((assignment) => assignment.childCode === 'AA')));
  assert.equal(localPhotoPage(photos, 'g1', 'all', 4).items.length, 0);
});

test('Фото: полная страница заполняет последний ряд при любом числе колонок', () => {
  // The frame grid has 1–5 columns within the 1320 px cabinet content.
  for (const columns of [1, 2, 3, 4, 5]) assert.equal(photoPageSize % columns, 0, columns + ' columns');
});

test('Фото: номер страницы и фильтр из URL проверяются', () => {
  assert.equal(photoPage('3'), 3);
  for (const value of [undefined, '', '0', '-1', '2.5', 'abc', '01', ['2']]) assert.equal(photoPage(value), 1);
  assert.equal(photoFilter('unassigned'), 'unassigned');
  assert.equal(photoFilter('AB'), 'AB');
  for (const value of [undefined, 'a', 'A001', 'ABCD', 'all ', ['A']]) assert.equal(photoFilter(value), 'all');
  assert.equal(photoPages(0), 1);
  assert.equal(photoPages(60), 1);
  assert.equal(photoPages(61), 2);
});

test('Фото: свободный код ребёнка берётся из сводки группы', () => {
  assert.equal(freeChildCode(new Set()), 'A');
  assert.equal(freeChildCode(new Set(['A', 'B', 'D'])), 'C');
  assert.equal(freeChildCode(new Set(Array.from({ length: 26 }, (_, index) => String.fromCharCode(65 + index)))), 'AA');
});
