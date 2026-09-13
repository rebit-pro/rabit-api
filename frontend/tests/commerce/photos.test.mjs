import test from 'node:test';
import assert from 'node:assert/strict';
import {
  fileProblem,
  photoLimits,
  childCodeAt,
  nextChildCode,
  photoCode,
  nextSequence,
  duplicatePhoto,
  completeChildSelection,
  validChildCode
} from '../../src/modules/morefoto/photos/rules.ts';
test('Фотографии: границы размера, пустой файл и запрещённый формат', () => {
  const file = { name: 'кадр.jpg', size: photoLimits.bytes, type: 'image/jpeg' };
  assert.equal(fileProblem(file), '');
  assert.match(fileProblem({ ...file, size: file.size + 1 }), /25 МБ/);
  assert.match(fileProblem({ ...file, size: 0 }), /пуст/);
  assert.match(fileProblem({ ...file, type: 'image/svg+xml' }), /JPEG/);
});
test('Буквенные коды продолжаются после Z и не используют кириллицу', () => {
  assert.equal(childCodeAt(25), 'Z');
  assert.equal(childCodeAt(26), 'AA');
  assert.equal(childCodeAt(18277), 'ZZZ');
  assert.equal(validChildCode('AA'), true);
  for (const code of ['А', 'A1', 'AAAA', '']) assert.equal(validChildCode(code), false);
});
test('Свободный код ищется внутри своей группы', () => {
  assert.equal(
    nextChildCode(
      [
        { groupId: 'a', childCode: 'A' },
        { groupId: 'b', childCode: 'B' }
      ],
      'a'
    ),
    'B'
  );
});
test('Нумерация кадров не повторяет номера после переназначения', () => {
  const photos = [
    { groupId: 'g', childCode: 'A', sequence: 1 },
    { groupId: 'g', childCode: 'A', sequence: 3 }
  ];
  assert.equal(photoCode('A', nextSequence(photos, 'g', 'A')), 'A004');
  assert.equal(nextSequence(photos, 'other', 'A'), 1);
});
test('Дубли определяются по содержимому в пределах съёмки', () => {
  const photo = { id: '1', shootId: 's1', fingerprint: 'abc', filename: 'one.jpg' };
  assert.equal(duplicatePhoto([photo], 's1', 'abc'), photo);
  assert.equal(duplicatePhoto([photo], 's2', 'abc'), undefined);
  assert.equal(duplicatePhoto([photo], 's1', 'different'), undefined);
});
test('Полный набор исключает часть ребёнка, неизвестный ID и смешение групп', () => {
  const photos = [
    { id: '1', groupId: 'a', childCode: 'A' },
    { id: '2', groupId: 'a', childCode: 'A' },
    { id: '3', groupId: 'b', childCode: 'A' }
  ];
  assert.equal(completeChildSelection(photos, ['1', '2']), true);
  for (const ids of [[], ['1'], ['1', '2', '3'], ['1', '2', 'missing']]) assert.equal(completeChildSelection(photos, ids), false);
});
