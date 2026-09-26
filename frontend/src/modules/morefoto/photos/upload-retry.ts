/**
 * How the upload queue reacts to a failed file. Every upload is idempotent on the server (the content hash finds
 * the same photo, labels are not duplicated), so a transient failure is simply sent again.
 */
export type UploadFailure = 'retry' | 'offline' | 'session' | 'locked' | 'fail';

const retryDelays = [2000, 5000, 15000, 30000, 60000, 120000];
const transientStatuses = [408, 425, 429, 500, 502, 503, 504];

export function uploadFailure(status: number | undefined, code: string | undefined, online: boolean): UploadFailure {
  if (status === undefined) return online ? 'retry' : 'offline';
  if (status === 401) return 'session';
  if (status === 409 && code === 'GROUP_MEDIA_LOCKED') return 'locked';

  return transientStatuses.includes(status) ? 'retry' : 'fail';
}

/** Pause before the given repeat (1-based); null once the automatic repeats are spent. */
export function retryDelay(attempt: number): number | null {
  return retryDelays[attempt - 1] ?? null;
}

export const maxRetries = retryDelays.length;

/** The session is not prolonged: warn before a start that would outlive it, with a double margin for a slow link. */
export function sessionTooShort(expiresAt: number | null, now: number, bytes: number, bytesPerSecond: number): boolean {
  return expiresAt !== null && expiresAt - now < (bytes / bytesPerSecond) * 1000 * 2;
}
