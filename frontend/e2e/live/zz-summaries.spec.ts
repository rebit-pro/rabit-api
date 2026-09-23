import { test, expect, type APIResponse, type Page } from '@playwright/test';
import { login, logout, token } from './helpers.js';

// U5: server summaries. Every split must add up to the unfiltered total, a filter must not change the split it
// filters by, and a curator's counters must stay inside the organizer's ones. Data comes from the earlier specs.
type Counts = Record<string, number>;

async function get(page: Page, path: string) {
  const response: APIResponse = await page.request.get(path, { headers: { Authorization: 'Bearer ' + (await token(page)) } });
  expect(response.status(), path + ': ' + (await response.text())).toBe(200);
  return response.json();
}

const sum = (counts: Counts) => Object.values(counts).reduce((total, value) => total + value, 0);

async function linkSummary(page: Page, query = '') {
  const payload = await get(page, '/api/v1/group-links?pageSize=5' + query);
  return {
    total: payload.meta.total as number,
    summary: payload.meta.summary as { byState: Counts; closingSoon: number; prepared: number; referenceNow: string }
  };
}

async function requestSummary(page: Page, query = '') {
  const payload = await get(page, '/api/v1/staff-requests?pageSize=5' + query);
  return { total: payload.meta.total as number, byStatus: payload.meta.summary.byStatus as Counts };
}

async function orderSummary(page: Page, query = '') {
  const payload = await get(page, '/api/v1/orders?pageSize=5' + query);
  return { total: payload.meta.total as number, summary: payload.meta.summary as { total: number; byProductionStatus: Counts } };
}

test('U5: сводки организатора сходятся со списками и не зависят от своего фильтра', async ({ page }) => {
  await login(page);

  const links = await linkSummary(page);
  expect(sum(links.summary.byState)).toBe(links.total);
  expect(links.summary.prepared).toBeLessThanOrEqual(links.summary.byState.preparing!);
  expect(links.summary.closingSoon).toBeLessThanOrEqual(links.summary.byState.open!);
  expect(Date.parse(links.summary.referenceNow)).not.toBeNaN();
  const openLinks = await linkSummary(page, '&state=open');
  expect(openLinks.summary.byState).toEqual(links.summary.byState);
  expect(openLinks.total).toBe(links.summary.byState.open);

  const requests = await requestSummary(page);
  expect(sum(requests.byStatus)).toBe(requests.total);
  const submitted = await requestSummary(page, '&status=submitted');
  expect(submitted.byStatus).toEqual(requests.byStatus);
  expect(submitted.total).toBe(requests.byStatus.submitted);

  const orders = await orderSummary(page);
  expect(orders.summary.total).toBe(orders.total);
  expect(sum(orders.summary.byProductionStatus)).toBe(orders.total);
  const notStarted = await orderSummary(page, '&productionStatus=not-started');
  expect(notStarted.summary).toEqual(orders.summary);
  expect(notStarted.total).toBe(orders.summary.byProductionStatus['not-started']);

  const staff = await get(page, '/api/v1/users?pageSize=5');
  expect(sum(staff.meta.summary.byAccountStatus)).toBe(staff.meta.total);
  expect(sum(staff.meta.summary.byRole)).toBe(staff.meta.total);
  const pending = await get(page, '/api/v1/users?pageSize=5&accountStatus=pending');
  expect(pending.meta.total).toBe(staff.meta.summary.byAccountStatus.pending);
  expect(pending.meta.summary.byAccountStatus).toEqual(staff.meta.summary.byAccountStatus);

  const institutions = await get(page, '/api/v1/institutions?pageSize=20');
  for (const item of institutions.data.items as { shootCount: number; groupCount: number; openGroupCount: number }[]) {
    expect(item.openGroupCount).toBeLessThanOrEqual(item.groupCount);
    expect(item.shootCount).toBeGreaterThanOrEqual(0);
  }
  const withShoots = (institutions.data.items as { id: string; shootCount: number }[]).find((item) => item.shootCount > 0);
  if (withShoots) {
    const detail = await get(page, '/api/v1/institutions/' + withShoots.id + '?pageSize=1');
    expect(sum(detail.data.groups.meta.summary.byState)).toBe(detail.data.groups.meta.total);
    const shootId = detail.data.shoots.items[0].id as string;
    const shoot = await get(page, '/api/v1/shoots/' + shootId + '?pageSize=1');
    expect(sum(shoot.data.groups.meta.summary.byState)).toBe(shoot.data.groups.meta.total);
    const photos = await get(page, '/api/v1/shoots/' + shootId + '/photos?pageSize=1');
    expect(sum(photos.data.stats.byStatus)).toBe(photos.data.meta.total);
    expect(photos.data.stats.unassigned).toBeLessThanOrEqual(photos.data.stats.byStatus.ready);
  }

  await logout(page);
  await login(page, 'curator');
  const curatorLinks = await linkSummary(page);
  expect(sum(curatorLinks.summary.byState)).toBe(curatorLinks.total);
  expect(curatorLinks.total).toBeLessThanOrEqual(links.total);
  const curatorRequests = await requestSummary(page);
  expect(sum(curatorRequests.byStatus)).toBe(curatorRequests.total);
  expect(curatorRequests.total).toBeLessThanOrEqual(requests.total);
  const curatorOrders = await orderSummary(page);
  expect(curatorOrders.summary.total).toBe(curatorOrders.total);
  expect(curatorOrders.total).toBeLessThanOrEqual(orders.total);
});
