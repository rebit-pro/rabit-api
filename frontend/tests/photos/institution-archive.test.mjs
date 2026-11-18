import { test } from 'node:test';
import assert from 'node:assert/strict';
import {
  archiveIdentity,
  archivePortions,
  archiveRoots,
  inspectInstitutionArchive,
  archiveImportKey,
  planClass
} from '../../src/modules/morefoto/photos/institution-archive.ts';
import { archiveJobs } from '../../src/modules/morefoto/photos/archive-queue.ts';
import { shootDelivery } from '../../src/modules/morefoto/handoff/shoot-delivery.ts';

const entries = (paths) => paths.map((path) => ({ path, directory: path.endsWith('/'), bytes: 16, method: 0, encrypted: false }));
const check = (paths, root = '') => inspectInstitutionArchive(entries(paths), 'zip', root);

test('TC-02: сохраняет уровень единственного класса, распознаёт обёртку и даёт выбрать неоднозначный корень', () => {
  assert.deepEqual(archiveRoots(entries(['1А/A/1.jpg'])), { wrapper: '', initial: '' });
  assert.deepEqual(archiveRoots(entries(['Школа/1А/A/1.jpg', 'Школа/1Б/A/2.jpg'])), { wrapper: 'Школа', initial: 'Школа' });
  assert.deepEqual(archiveRoots(entries(['1А/A/1.jpg', '1А/1Б/A/2.jpg'])), { wrapper: '1А', initial: '' });
  const result = check(
    ['Школа/1А/A/1.jpg', 'Школа/1Б/A/2.jpg', 'Школа/пусто/', '__MACOSX/иконка', 'Школа/фото.jpg', 'Школа/1А/A/2.jpg', 'Школа/1А/A/2.jpg'],
    'Школа'
  );
  assert.deepEqual(
    result.groups.map((g) => g.name),
    ['1А', '1Б']
  );
  assert.equal(result.skipped.length, 5);
  assert.equal(result.skipped.filter((i) => i.reason.includes('повторяется')).length, 2);
});

test('TC-05: свободные и выбранные коды и получатели группового кадра принадлежат одному классу', () => {
  const result = check(['1А/Серия 01/1.jpg', '1А/B/2.jpg', '1А/GROUP01/all.jpg', '1Б/A/3.jpg']);
  const plan = planClass(result.groups[0], new Map());
  assert.deepEqual(plan.groupCodes, ['A', 'B']);
  assert.equal(plan.folders.find((f) => f.folder === 'Серия 01').code, 'A');
  assert.ok(planClass(result.groups[0], new Map([['Серия 01', 'B']])).problems.length);
  assert.deepEqual(planClass(result.groups[1], new Map()).groupCodes, ['A']);
  const jobs = archiveJobs(
    plan,
    [],
    { shootId: 'Весна', groupId: '1А', archives: new Set(['zip']) },
    () => false,
    () => crypto.randomUUID()
  ).jobs;
  assert.deepEqual(jobs.at(-1).childCodes, ['A', 'B']);
  assert.equal(
    jobs.every((j) => j.groupId === '1А'),
    true
  );
});

test('TC-06: большой архив делится без потерь и сохраняет более 100 получателей группового кадра', async () => {
  const paths = Array.from({ length: 2028 }, (_, i) => '7А/A/' + i + '.jpg');
  paths.push('7А/GROUP/все.jpg');
  const codes = Array.from({ length: 110 }, (_, i) => String.fromCharCode(65 + Math.floor(i / 26), 65 + (i % 26)));
  for (const code of codes) paths.push('7А/' + code + '/портрет.jpg');
  const plan = planClass(check(paths).groups[0], new Map());
  const portions = [...archivePortions(plan, 200)];
  assert.equal(portions.length, 11);
  assert.equal(
    portions.reduce((sum, b) => sum + b.files, 0),
    2139
  );
  assert.equal(new Set(portions.flatMap((p) => p.folders.flatMap((f) => f.files.map((x) => x.path)))).size, 2139);
  assert.equal(portions.at(-1).groupCodes.length, 111);
  const sig = (value) => archiveIdentity(new Blob([value]), new AbortController().signal);
  assert.notEqual(await sig('abc'), await sig('abd'));
  assert.equal(await sig('abc'), await sig('abc'));
});

test('TC-03: повтор архива восстанавливает группы только для тех же съёмки, корня и кодов', async () => {
  const choices = [{ name: '1А', codes: [['Ребёнок', 'A']] }];
  const key = await archiveImportKey('весна', 'zip1', '', choices);
  assert.equal(await archiveImportKey('весна', 'zip1', '', choices), key);
  for (const next of [
    ['осень', 'zip1', '', choices],
    ['весна', 'zip2', '', choices],
    ['весна', 'zip1', 'школа', choices],
    ['весна', 'zip1', '', [{ name: '1А', codes: [['Ребёнок', 'B']] }]]
  ])
    assert.notEqual(await archiveImportKey(...next), key);
});

test('TC-07: ручная отправка при расписании сохраняет назначенное открытие', () => {
  const group = { prepared: true, problems: [], sentAt: null };
  assert.equal(shootDelivery(group).manual, false);
  assert.equal(shootDelivery({ ...group, openingStatus: 'scheduled' }).manual, true);
  assert.equal(shootDelivery({ ...group, openingStatus: 'opened', sentAt: 'now' }).manual, true);
  for (const extra of [{ prepared: false }, { manualSentAt: 'now' }, { openingStatus: 'blocked' }, { sentAt: 'now' }])
    assert.equal(shootDelivery({ ...group, ...extra }).manual, null);
});
