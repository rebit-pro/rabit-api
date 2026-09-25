export type SessionEndReason = 'revoked' | 'session-expired';

/**
 * Why the server ended a session, for the sign-in page (design plan 10.1). Older endpoints answer every 401 with
 * UNAUTHORIZED, so without a machine code a token whose own lifetime is not over counts as replaced by a newer
 * sign-in or revoked, and only a token past its expiry counts as expired.
 */
export function sessionEndReason(code: string | null, expiresAt: string | null, now = Date.now()): SessionEndReason {
  if (code === 'SESSION_REVOKED') return 'revoked';
  if (code === 'TOKEN_EXPIRED') return 'session-expired';
  const deadline = expiresAt === null ? Number.NaN : Date.parse(expiresAt);
  return Number.isFinite(deadline) && deadline > now ? 'revoked' : 'session-expired';
}
