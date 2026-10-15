import test from 'node:test';
import assert from 'node:assert/strict';
import {
  archiveBadge,
  archiveErrorText,
  archiveHref,
  buildAction,
  isBuilding,
  versionErrorText,
  versionTitle
} from '../../src/modules/morefoto/production/live/archive-rules.ts';

// L1: the «Печать» block of «Ссылки и сроки» — the print archive for the lab.
const version = (status, number = 1) => ({ id: 'v' + number, groupId: 'g', number, status, archiveUrl: null });
const archive = (patch = {}) => ({ state: 'ready', canManage: true, outsideLines: 0, versions: [version('ready')], ...patch });

test('a row badge appears only after print sales close', () => {
  assert.equal(archiveBadge('open'), null);
  assert.deepEqual(archiveBadge('ready'), { text: 'архив печати готов', tone: 'success' });
  assert.equal(archiveBadge('failed').tone, 'danger');
  assert.equal(archiveBadge('manual').text, 'архив не собран');
  for (const state of ['scheduled', 'manual', 'building', 'ready', 'failed', 'empty', 'expired']) assert.ok(archiveBadge(state));
});

test('only the organizer builds: the first archive of a closed group or a supplement with late payments', () => {
  assert.equal(buildAction(archive({ state: 'manual', versions: [] })), 'archive');
  assert.equal(buildAction(archive({ state: 'scheduled', versions: [] })), 'archive');
  assert.equal(buildAction(archive({ state: 'open', versions: [] })), null);
  assert.equal(buildAction(archive()), null);
  assert.equal(buildAction(archive({ outsideLines: 2 })), 'supplement');
  assert.equal(buildAction(archive({ outsideLines: 2, canManage: false })), null);
  assert.equal(buildAction(archive({ state: 'manual', versions: [], canManage: false })), null);
});

test('titles, building state and error texts', () => {
  assert.equal(versionTitle(version('ready')), 'Архив печати');
  assert.equal(versionTitle(version('ready', 3)), 'Дополнение 2');
  assert.equal(isBuilding(archive({ versions: [version('ready'), version('pending', 2)] })), true);
  assert.equal(isBuilding(archive()), false);
  assert.match(versionErrorText('ORIGINAL_MISSING'), /оригинала/);
  assert.match(versionErrorText(null), /Соберите его заново/);
  assert.match(archiveErrorText({ network: true }), /Ответ сервера не получен/);
  assert.match(archiveErrorText({ network: false, status: 409, code: 'NOTHING_TO_BUILD' }), /дополнение не нужно/);
  assert.match(archiveErrorText({ network: false, status: 403, code: 'FORBIDDEN' }), /только организатор/);
  assert.match(archiveErrorText({ network: false, status: 500 }), /Повторите позже/);
});

test('the archive link is absolute for the lab whatever the API base', () => {
  const url = '/api/v1/public/production/archives/abc';
  assert.equal(archiveHref(url, '/api', 'https://app.morefoto36.ru'), 'https://app.morefoto36.ru' + url);
  assert.equal(archiveHref(url, undefined, 'https://app.morefoto36.ru'), 'https://app.morefoto36.ru' + url);
  assert.equal(archiveHref(url, 'https://api.example.ru/api', 'https://app.morefoto36.ru'), 'https://api.example.ru' + url);
});
