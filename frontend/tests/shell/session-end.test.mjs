import { test } from 'node:test';
import assert from 'node:assert/strict';
import { sessionEndReason } from '../../src/api/sessionEnd.ts';

const now = Date.parse('2026-09-23T12:00:00Z');

test('a machine code decides the reason', () => {
  assert.equal(sessionEndReason('SESSION_REVOKED', '2026-09-23T11:00:00Z', now), 'revoked');
  assert.equal(sessionEndReason('TOKEN_EXPIRED', '2026-09-23T13:00:00Z', now), 'session-expired');
});

test('without a code a token with time left was replaced or revoked', () => {
  assert.equal(sessionEndReason('UNAUTHORIZED', '2026-09-23T13:00:00Z', now), 'revoked');
  assert.equal(sessionEndReason(null, '2026-09-23T12:00:01Z', now), 'revoked');
});

test('without a code a token past its lifetime or without one has expired', () => {
  assert.equal(sessionEndReason('UNAUTHORIZED', '2026-09-23T12:00:00Z', now), 'session-expired');
  assert.equal(sessionEndReason(null, null, now), 'session-expired');
  assert.equal(sessionEndReason(null, 'not a date', now), 'session-expired');
});
