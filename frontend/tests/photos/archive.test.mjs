import { test } from 'node:test';
import assert from 'node:assert/strict';
import { folderCodes, planArchives } from '../../src/modules/morefoto/photos/archive.ts';

const limits = { batch: 2000, bytes: 25 * 1024 * 1024 };
const file = (path, bytes = 1000) => ({
  path,
  bytes,
  compressedBytes: bytes,
  method: 0,
  offset: 0,
  directory: path.endsWith('/'),
  encrypted: false
});
const source = (key, paths) => ({ key, name: key + '.zip', entries: paths.map((path) => (typeof path === 'string' ? file(path) : path)) });
const plan = (sources, existing = [], marks = []) => planArchives(sources, existing, new Set(marks), limits);
const shape = (result) => result.folders.map((folder) => [folder.folder, folder.kind, folder.code, folder.files.length]);

test('folders become children by code and GROUP folders become shared frames (T07)', () => {
  const result = plan(
    [source('p1', ['A/A002.jpg', 'A/A001.jpg', 'b/1.jpg', 'AA/1.JPG', 'GROUP-1/g.jpg', 'group_F/f.png', 'BB/1.webp'])],
    ['A', 'C']
  );

  assert.deepEqual(shape(result), [
    ['A', 'child', 'A', 2],
    ['b', 'child', 'B', 1],
    ['AA', 'child', 'AA', 1],
    ['BB', 'child', 'BB', 1],
    ['GROUP-1', 'group', null, 1],
    ['group_F', 'group', null, 1]
  ]);
  assert.deepEqual(result.groupCodes, ['A', 'B', 'C', 'AA', 'BB']);
  assert.deepEqual(
    result.folders[0].files.map((item) => item.name),
    ['A001.jpg', 'A002.jpg']
  );
  assert.equal(result.folders[0].existing, true);
  assert.deepEqual(folderCodes(result.folders[4], result), ['A', 'B', 'C', 'AA', 'BB']);
  assert.deepEqual(folderCodes(result.folders[0], result), ['A']);
  assert.deepEqual(result.problems, []);
});

test('a manual mark turns a coded folder into group frames, as for shoot 158 with F, L, U, AX', () => {
  const result = plan([source('p1', ['A/1.jpg', 'F/1.jpg', 'G/1.jpg'])], [], ['F']);

  assert.deepEqual(shape(result), [
    ['A', 'child', 'A', 1],
    ['G', 'child', 'G', 1],
    ['F', 'group', null, 1]
  ]);
  assert.deepEqual(result.groupCodes, ['A', 'G']);
});

test('a Cyrillic or a long folder name stops the start with a hint (T07)', () => {
  const result = plan([source('p1', ['А/1.jpg', 'ABCD/1.jpg', 'B/1.jpg'])]);
  const problems = Object.fromEntries(result.folders.map((folder) => [folder.folder, folder.problem]));

  assert.match(problems['А'], /кириллицей.*латинская буква: A/);
  assert.match(problems.ABCD, /не код ребёнка/);
  assert.equal(problems.B, '');
  assert.equal(result.problems.length, 1);
});

test('service entries, wrappers, loose and oversized files are handled before the start (T06, T12)', () => {
  const result = plan([
    source(
      'p1',
      [
        'Съёмка 158/',
        'Съёмка 158/A/1.jpg',
        'Съёмка 158/A/nested/2.jpg',
        'Съёмка 158/A/.DS_Store',
        'Съёмка 158/A/notes.txt',
        'Съёмка 158/A/Thumbs.db',
        '__MACOSX/Съёмка 158/A/._1.jpg',
        'Съёмка 158/B/empty.jpg',
        'Съёмка 158/B/huge.jpg',
        'Съёмка 158/loose.jpg'
      ].map((path) => (path.endsWith('empty.jpg') ? file(path, 0) : path.endsWith('huge.jpg') ? file(path, limits.bytes + 1) : path))
    )
  ]);

  assert.deepEqual(shape(result), [['A', 'child', 'A', 2]]);
  assert.deepEqual(
    result.skipped.map((item) => [item.path, item.reason]),
    [
      ['Съёмка 158/A/notes.txt', 'Не фотография: допустимы JPEG, PNG и WebP.'],
      ['Съёмка 158/B/empty.jpg', 'Файл пуст.'],
      ['Съёмка 158/B/huge.jpg', 'Файл больше 25 МБ.'],
      ['Съёмка 158/loose.jpg', 'Файл лежит вне папки ребёнка.']
    ]
  );
});

test('parts chosen together are one plan: a child split between parts is one child (T18)', () => {
  const result = plan([
    source('p1', ['A/1.jpg', 'B/1.jpg']),
    source('p2', ['A/2.jpg']),
    source('p3', ['GROUP-1/g.jpg']),
    { key: 'v', name: '158.z01', entries: [] }
  ]);

  assert.deepEqual(shape(result), [
    ['A', 'child', 'A', 2],
    ['B', 'child', 'B', 1],
    ['GROUP-1', 'group', null, 1]
  ]);
  assert.deepEqual(
    result.folders[0].files.map((item) => item.archive),
    ['p1', 'p2']
  );
  assert.match(result.problems[0], /многотомного архива/);
});

test('limits and group frames without children are reported (T12)', () => {
  const many = source(
    'p1',
    Array.from({ length: 2001 }, (_, index) => 'A/' + index + '.jpg')
  );
  assert.match(planArchives([many], [], new Set(), limits).problems.join(' '), /не больше 2000/);
  assert.match(plan([source('p1', ['GROUP/1.jpg'])]).problems.join(' '), /некому достаться/);
  assert.match(plan([source('p1', ['readme.txt'])]).problems.join(' '), /нет фотографий/);
});
