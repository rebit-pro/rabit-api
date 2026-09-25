import { expect, type APIResponse, type Page } from '@playwright/test';
import { token } from './helpers.js';

/**
 * F2 runs in two files of group B. z-links-preparation prepares the link first in the group and records it here;
 * zzzz-links reports and corrects the delivery after zz-avatar and zzz-handoff, when the minute of preparation is over.
 */
export const preparedLinkPath = 'var/f2-links.json';
export type PreparedLink = {
  groupId: string;
  shootId: string;
  suffix: string;
  /** Moscow minute of the first preparation: the earliest moment a delivery may be reported. */
  prepareMinute: string;
  galleryToken: string;
  signature: string;
};
export type Link = {
  groupId: string;
  revision: number;
  signature: string;
  prepared: boolean;
  problems: string[];
  state: string;
  sentAt: string | null;
  closesAt: string | null;
  deliveryAt: string | null;
  galleryToken?: string | null;
  history?: { kind: string; at: string; reason: string | null; previousSentAt: string | null }[];
};

export const key = () => crypto.randomUUID().replace(/-/g, '');
export async function body(response: APIResponse, status = 200) {
  expect(response.status(), await response.text()).toBe(status);
  if (new URL(response.url()).pathname.includes('link')) expect(response.headers()['cache-control']).toBe('no-store');
  return response.json();
}
export async function auth(page: Page, idempotencyKey = key()) {
  return { Authorization: 'Bearer ' + (await token(page)), 'Idempotency-Key': idempotencyKey };
}
export async function link(page: Page, groupId: string): Promise<Link> {
  return (await body(await page.request.get('/api/v1/groups/' + groupId + '/link', { headers: await auth(page) }))).data;
}
export async function command(
  page: Page,
  groupId: string,
  action: string,
  data: Record<string, unknown>,
  status = 200,
  idempotencyKey = key()
) {
  return body(
    await page.request.post('/api/v1/groups/' + groupId + '/' + action, { headers: await auth(page, idempotencyKey), data }),
    status
  );
}
/** Moscow form value of a moment, as the staff types it. */
export const moscow = (moment: number) => new Date(moment + 3 * 3600000).toISOString().slice(0, 16);
