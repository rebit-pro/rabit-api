import test from 'node:test';
import assert from 'node:assert/strict';
import {
  digitalArchiveError,
  digitalArchiveRefreshDelay,
  digitalArchiveStatus
} from '../../src/modules/morefoto/files/institution-digital-rules.ts';

test('queued, building, empty, failure and expiry stay distinguishable', () => {
  assert.equal(digitalArchiveStatus({ status: 'pending', attempts: 0 }), 'Ожидает сборки');
  assert.match(digitalArchiveStatus({ status: 'pending', attempts: 1 }), /Собирается/);
  assert.match(digitalArchiveStatus({ status: 'empty' }), /Нет купленных/);
  assert.equal(digitalArchiveStatus({ status: 'failed' }), 'Ошибка сборки');
  assert.equal(digitalArchiveStatus({ status: 'expired' }), 'Срок ZIP истёк');
});

test('polling stops with no work and refreshes exactly when a ready ZIP expires', () => {
  const now = Date.parse('2026-11-17T12:00:00Z');
  assert.equal(digitalArchiveRefreshDelay([], now), null);
  assert.equal(digitalArchiveRefreshDelay([{ status: 'failed' }], now), null);
  assert.equal(digitalArchiveRefreshDelay([{ status: 'pending' }], now), 4000);
  assert.equal(digitalArchiveRefreshDelay([{ status: 'ready', expiresAt: '2026-11-17T12:01:00Z' }], now), 60_000);
  assert.equal(digitalArchiveRefreshDelay([{ status: 'ready', expiresAt: '2026-11-17T11:00:00Z' }], now), 1000);
});

test('missing originals and oversized sets never suggest a partial or gifted archive', () => {
  assert.match(digitalArchiveError('ORIGINAL_MISSING'), /не выдан частично/);
  assert.match(digitalArchiveError('DIGITAL_ARCHIVE_TOO_LARGE'), /10 000 файлов, 20 ГиБ/);
  assert.match(digitalArchiveError('ARCHIVE_ACCESS_REVOKED'), /Права инициатора изменились/);
  assert.match(digitalArchiveError('DIGITAL_ARCHIVE_IN_PROGRESS'), /уже собирается/);
});
