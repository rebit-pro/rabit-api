import { test } from 'node:test';
import assert from 'node:assert/strict';
import { maxRetries, retryDelay, sessionTooShort, uploadFailure } from '../../src/modules/morefoto/photos/upload-retry.ts';
import { ArchiveProgress } from '../../src/modules/morefoto/photos/archive-progress.ts';

test('transient failures repeat, the rest stop the file or the queue (T20)', () => {
  assert.equal(uploadFailure(undefined, undefined, true), 'retry');
  assert.equal(uploadFailure(undefined, undefined, false), 'offline');
  for (const status of [408, 425, 429, 500, 502, 503, 504]) assert.equal(uploadFailure(status, undefined, true), 'retry', String(status));
  assert.equal(uploadFailure(401, 'TOKEN_EXPIRED', true), 'session');
  assert.equal(uploadFailure(409, 'GROUP_MEDIA_LOCKED', true), 'locked');
  assert.equal(uploadFailure(409, 'REVISION_CONFLICT', true), 'fail');
  assert.equal(uploadFailure(422, 'INVALID_PHOTO', true), 'fail');
  assert.equal(uploadFailure(403, undefined, true), 'fail');
});

test('repeats wait longer each time and end after the last pause (T20)', () => {
  assert.deepEqual(
    Array.from({ length: maxRetries + 1 }, (_, index) => retryDelay(index + 1)),
    [2000, 5000, 15000, 30000, 60000, 120000, null]
  );
});

test('a session that ends before the estimated upload is reported before the start (T11)', () => {
  const now = Date.parse('2026-09-26T12:00:00Z');
  const gigabytes = 4.4 * 1024 ** 3;
  // 4.4 GB at 1 MB/s is 75 minutes, doubled — 2.5 hours.
  assert.equal(sessionTooShort(now + 2 * 3600 * 1000, now, gigabytes, 1024 * 1024), true);
  assert.equal(sessionTooShort(now + 3 * 3600 * 1000, now, gigabytes, 1024 * 1024), false);
  assert.equal(sessionTooShort(null, now, gigabytes, 1024 * 1024), false);
});

test('accepted archive files survive a reload and a broken storage costs only the convenience (T22)', () => {
  const memory = new Map();
  const storage = { getItem: (key) => memory.get(key) ?? null, setItem: (key, value) => memory.set(key, value) };
  const first = new ArchiveProgress(storage, 'k');
  first.mark('group p1', 'A/1.jpg');
  first.mark('group p1', 'A/1.jpg');

  const reloaded = new ArchiveProgress(storage, 'k');
  assert.equal(reloaded.has('group p1', 'A/1.jpg'), true);
  assert.equal(reloaded.has('other p1', 'A/1.jpg'), false);
  assert.equal(memory.get('k'), '{"group p1":["A/1.jpg"]}');

  const broken = {
    getItem: () => '{not json',
    setItem: () => {
      throw new Error('QuotaExceededError');
    }
  };
  const fallback = new ArchiveProgress(broken, 'k');
  fallback.mark('group p1', 'A/1.jpg');
  assert.equal(fallback.has('group p1', 'A/1.jpg'), true);
  assert.equal(new ArchiveProgress(null, 'k').has('group p1', 'A/1.jpg'), false);
});
