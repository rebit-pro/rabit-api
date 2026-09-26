import test from 'node:test';
import assert from 'node:assert/strict';
import {
  contentHref,
  downloadBody,
  fileSize,
  filesCount,
  filesError,
  keepsRequestKey,
  pollDelay
} from '../../src/modules/morefoto/orders/live/files-rules.ts';

const ready = {
  id: 'd1',
  kind: 'zip',
  status: 'ready',
  expiresAt: null,
  filename: 'a.zip',
  error: null,
  contentUrl: '/api/v1/public/orders/current/downloads/d1/content?token=1.ab'
};

test('J1-T12: a single file names exactly one photo, an archive takes all available', () => {
  assert.deepEqual(downloadBody('p1'), { kind: 'file', photoIds: ['p1'] });
  assert.deepEqual(downloadBody(), { kind: 'zip' });
});

test('J1-T12: the content link opens only a ready download and respects a separate API host', () => {
  assert.equal(contentHref(ready, undefined), ready.contentUrl);
  assert.equal(contentHref(ready, 'https://api.example.ru/api/'), 'https://api.example.ru' + ready.contentUrl);
  assert.equal(contentHref({ ...ready, status: 'pending' }, undefined), null);
  assert.equal(contentHref({ ...ready, contentUrl: null }, undefined), null);
});

test('J1-T12: archive polling slows down after half a minute', () => {
  assert.equal(pollDelay(0), 2000);
  assert.equal(pollDelay(31_000), 5000);
});

test('J1-T12: sizes and counts read naturally in Russian', () => {
  assert.equal(fileSize(500), '1 КБ');
  assert.equal(fileSize(3 * 1024 * 1024 + 300 * 1024), '3,3 МБ');
  assert.equal(fileSize(2 * 1024 ** 3), '2,00 ГБ');
  assert.deepEqual([1, 2, 5, 11, 21, 104].map(filesCount), ['1 файл', '2 файла', '5 файлов', '11 файлов', '21 файл', '104 файла']);
});

test('J1-T12: server refusals become buyer-facing messages; only an unanswered request keeps its key', () => {
  assert.match(filesError({ status: 413, code: 'ARCHIVE_TOO_LARGE', network: false }), /по одной/);
  assert.match(filesError({ status: null, code: '', network: true }), /Нет связи/);
  assert.match(filesError({ status: 409, code: 'SOMETHING_NEW', network: false }), /Повторите/);
  assert.equal(keepsRequestKey({ status: null, code: '', network: true }), true);
  assert.equal(keepsRequestKey({ status: 502, code: '', network: false }), true);
  assert.equal(
    keepsRequestKey({
      status: 409,
      code: 'ARCHIVE_IN_PROGRESS',
      network: false
    }),
    false
  );
});
