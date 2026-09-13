import test from 'node:test';
import assert from 'node:assert/strict';
import { reviewFixture, reviewNow, reviewLinks } from '../../src/modules/morefoto/review/fixture.ts';
import { reviewTime, reviewSettingsError, hasDemoData } from '../../src/modules/morefoto/review/rules.ts';
test('R16: fixture graph has real photos, valid assignments and separate scopes', () => {
  const { organization: o, photos: s } = reviewFixture();
  assert.equal(new Set(s.photos.map((p) => p.id)).size, s.photos.length);
  assert.ok(s.photos.length > 8);
  for (const g of o.groups) {
    assert.ok(o.shoots.some((x) => x.id === g.shootId && x.institutionId === g.institutionId));
    assert.ok(o.institutions.some((x) => x.id === g.institutionId));
    if (g.teacherId) assert.ok(o.users.some((x) => x.id === g.teacherId && x.role === 'teacher'));
  }
  for (const p of s.photos) {
    assert.ok(o.groups.some((g) => g.id === p.groupId && g.shootId === p.shootId));
    assert.equal(p.groupId, p.originalGroupId);
    assert.match(p.previewSrc, /^\/demo\/gallery-v1\//);
  }
  assert.ok(s.photos.some((p) => p.groupId === 'school-1a'));
  assert.ok(s.photos.some((p) => p.groupId === 'sun-stars'));
  assert.equal(o.institutions.find((x) => x.id === 'school').curatorId, null);
});
test('R16: sales require handoff; staff and autumn begin empty', () => {
  const { organization: o, photos: s, now } = reviewFixture();
  assert.equal(now, reviewNow);
  for (const g of o.groups) {
    assert.equal(g.state, 'preparing');
    assert.equal(g.closesAt, null);
    assert.equal(g.sentAt, undefined);
  }
  assert.equal(s.photos.filter((p) => ['sun-staff', 'sun-bees'].includes(p.groupId)).length, 0);
  assert.deepEqual(s.staffRequests, []);
  assert.equal(o.groups.find((g) => g.id === 'sun-stars').galleryToken, reviewLinks.regular.slice(3));
});
test('R16: reset creates fresh snapshots without retaining edits', () => {
  const first = reviewFixture();
  first.organization.groups[0].name = 'changed';
  first.photos.photos[0].childCode = 'changed';
  first.photos.staffRequests.push({ id: 'x' });
  const second = reviewFixture();
  assert.equal(second.organization.groups[0].name, 'Звёздочки');
  assert.notEqual(second.photos.photos[0].childCode, 'changed');
  assert.deepEqual(second.photos.staffRequests, []);
});
test('R16: strict Moscow time rejects impossible dates and supports calendar boundary', () => {
  assert.equal(reviewTime('2026-09-08T12:00'), reviewNow);
  assert.equal(reviewTime('2026-10-08T12:00'), '2026-10-08T09:00:00.000Z');
  for (const date of ['', '2026-02-30T12:00', '2026-09-08T25:00', 'nonsense']) assert.equal(reviewTime(date), null);
});
test('R16: runtime controls accept only documented delays', () => {
  for (const delay of [0, 250, 1500, 3000]) assert.equal(reviewSettingsError({ date: '2026-09-08T12:00', delay, offline: false }), '');
  for (const delay of [-1, 100, NaN, Infinity]) assert.ok(reviewSettingsError({ date: '2026-09-08T12:00', delay, offline: false }));
  assert.ok(reviewSettingsError({ date: 'bad', delay: 0, offline: false }));
});
test('R16: any existing demo key protects user data, unrelated keys do not block', () => {
  for (const key of ['orders:v1', 'auth:token', 'photos:v1', 'checkout:draft', 'unknown'])
    assert.equal(hasDemoData(['morefoto:demo:' + key]), true);
  assert.equal(hasDemoData(['theme', 'morefoto:preference']), false);
  assert.equal(hasDemoData([]), false);
});
