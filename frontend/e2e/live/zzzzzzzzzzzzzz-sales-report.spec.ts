import { readFileSync } from 'node:fs';
import { test, expect, type APIResponse, type Page } from '@playwright/test';
import { login, token } from './helpers.js';

// E10: the sales report of each staff role and its PDF. Runs last in group B, after the print group got its paid,
// unpaid and late orders (J4, E9, L1, G2), so every child status the report knows can appear.
type Fixture = { print: { token: string; groupId: string } };
type Totals = {
  children: number;
  orderedFamilies: number;
  paidFamilies: number;
  paidOrders: number;
  prints: number;
  digitalFiles: number;
  bundles: number;
  paidTotal: number;
};
type Node = { id: string; name: string };
type Report = {
  level: string;
  institution: Node | null;
  shoot: Node | null;
  group: Node | null;
  totals: Totals;
  products: { id: string; name: string; prints: number }[];
  rows: (Node & { totals: Totals })[];
  children: { id: string; code: string; status: string; orders: number; paidOrders: number; paidTotal: number; hasPreview: boolean }[];
  generatedAt: string;
};
type Order = { paymentStatus: string; latePayment: boolean; quote: { lines: { childCode: string }[] } };
const fixture = JSON.parse(readFileSync('var/e4-fixture.json', 'utf8')) as Fixture;
const groupId = fixture.print.groupId;
const key = () => crypto.randomUUID().replace(/-/g, '');

async function body(response: APIResponse, status = 200) {
  expect(response.status(), await response.text()).toBe(status);
  return response.json();
}
const bearer = async (page: Page) => ({ Authorization: 'Bearer ' + (await token(page)) });
const report = async (page: Page, params: Record<string, string> = {}, status = 200) =>
  (await body(await page.request.get('/api/v1/sales-report', { headers: await bearer(page), params }), status)) as {
    data: Report;
    error?: { code: string };
  };

/** E10-DEC-06 counted independently from the COM-12 orders of the group. */
async function expectedStatuses(page: Page): Promise<Map<string, string>> {
  const orders = (await body(await page.request.get('/api/v1/orders', { headers: await bearer(page), params: { groupId, pageSize: 100 } })))
    .data.items as Order[];
  const states = new Map<string, Set<string>>();
  for (const order of orders) {
    const state = order.paymentStatus === 'paid' ? (order.latePayment ? 'late' : 'paid') : 'unpaid';
    for (const code of new Set(order.quote.lines.map((line) => line.childCode)))
      states.set(code, (states.get(code) ?? new Set()).add(state));
  }
  return new Map([...states].map(([code, set]) => [code, set.has('paid') ? 'paid' : set.has('late') ? 'late' : 'unpaid']));
}

/** The organizer adds an institution to the head's assignments through the staff card API (B2). */
async function assignHead(page: Page, institutionId: string) {
  const headers = { ...(await bearer(page)), 'Idempotency-Key': key() };
  const listing = await body(await page.request.get('/api/v1/users?q=' + encodeURIComponent('head@example.invalid'), { headers }));
  const staff = listing.data.items.find((item: { email: string }) => item.email === 'head@example.invalid');
  const detail = (await body(await page.request.get('/api/v1/users/' + staff.id, { headers }))).data;
  const options = (await body(await page.request.get('/api/v1/users/assignment-options', { headers }))).data;
  await body(
    await page.request.patch('/api/v1/users/' + staff.id, {
      headers,
      data: {
        name: detail.name,
        email: detail.email,
        role: 'head',
        active: true,
        institutionIds: [...new Set([...detail.institutionIds, institutionId])],
        groupIds: [],
        replaceAssignments: false,
        assignmentSignature: options.assignmentSignature,
        revision: detail.revision
      }
    })
  );
}

test.describe.configure({ mode: 'serial' });
test.use({ locale: 'ru-RU' });

test('E10-T09…T12: each role reads the sales of its own area and downloads the PDF', async ({ page, browser, baseURL }, testInfo) => {
  test.setTimeout(240000);
  await login(page);

  // E10-T10: the group level equals COM-14 and the COM-12 orders of the group.
  const group = (await report(page, { groupId })).data;
  const summary = (await body(await page.request.get('/api/v1/groups/' + groupId + '/sales-summary', { headers: await bearer(page) })))
    .data;
  expect(group.level).toBe('group');
  expect([
    group.totals.paidOrders,
    group.totals.paidFamilies,
    group.totals.prints,
    group.totals.digitalFiles,
    group.totals.bundles,
    group.totals.paidTotal
  ]).toEqual([summary.paidOrders, summary.families, summary.prints, summary.digitalFiles, summary.bundles, summary.paidTotal]);
  expect(group.products).toEqual(summary.products);
  expect(group.children.reduce((sum, child) => sum + child.paidTotal, 0)).toBe(group.totals.paidTotal);
  const statuses = await expectedStatuses(page);
  for (const child of group.children) expect([child.code, child.status]).toEqual([child.code, statuses.get(child.code) ?? 'none']);
  expect(group.children.some((child) => child.status === 'paid')).toBe(true);
  expect(JSON.stringify(group)).not.toMatch(/@|accessKey|MF-\d|photoId|buyer/i);
  const institutionId = group.institution!.id;
  const shootId = group.shoot!.id;

  // E10-T03: each level up holds the level below as one row.
  const shoot = (await report(page, { shootId })).data;
  const groupRow = shoot.rows.find((row) => row.id === groupId)!;
  expect(groupRow.totals).toEqual({ ...group.totals, children: groupRow.totals.children });
  const institution = (await report(page, { institutionId })).data;
  expect(institution.rows.find((row) => row.id === shootId)!.totals.paidTotal).toBe(shoot.totals.paidTotal);
  const all = (await report(page)).data;
  expect(all.level).toBe('all');
  expect(all.rows.find((row) => row.id === institutionId)!.totals.paidTotal).toBe(institution.totals.paidTotal);

  // E10-T09: roles. Unknown or malformed IDs look like foreign ones; no Bearer is 401.
  expect((await page.request.get('/api/v1/sales-report')).status()).toBe(401);
  expect((await report(page, { groupId: 'not-a-group' }, 404)).error!.code).toBe('GROUP_NOT_FOUND');
  await assignHead(page, institutionId);
  for (const [account, params, status, code] of [
    ['another-teacher', { groupId }, 200, null],
    ['teacher', { groupId }, 404, 'GROUP_NOT_FOUND'],
    ['head', { groupId }, 200, null],
    ['head', { institutionId }, 200, null],
    ['c4-head', { groupId }, 404, 'GROUP_NOT_FOUND']
  ] as const) {
    const context = await browser.newContext({ baseURL });
    try {
      const other = await context.newPage();
      await login(other, account);
      const answer = await report(other, params, status);
      if (null === code)
        expect(answer.data.totals.paidTotal).toBe('groupId' in params ? group.totals.paidTotal : institution.totals.paidTotal);
      else expect(answer.error!.code).toBe(code);
      if ('head' === account && 'groupId' in params) {
        // E10 widens COM-14 the same way: the head reads the summary of a group of their institution.
        expect(
          (await body(await other.request.get('/api/v1/groups/' + groupId + '/sales-summary', { headers: await bearer(other) }))).data
        ).toEqual(summary);
      }
    } finally {
      await context.close();
    }
  }
  const curatorContext = await browser.newContext({ baseURL });
  try {
    const curator = await curatorContext.newPage();
    await login(curator, 'curator');
    const own = (await report(curator)).data;
    const detail = (
      await body(
        await page.request.get('/api/v1/users?q=' + encodeURIComponent('curator@example.invalid'), { headers: await bearer(page) })
      )
    ).data.items[0];
    const assigned = (await body(await page.request.get('/api/v1/users/' + detail.id, { headers: await bearer(page) }))).data
      .institutionIds as string[];
    expect(own.rows.every((row) => assigned.includes(row.id))).toBe(true);
  } finally {
    await curatorContext.close();
  }

  // E10-T11: the PDF of the group for the organizer.
  const pdf = await page.request.get('/api/v1/sales-report/pdf', { headers: await bearer(page), params: { groupId } });
  expect(pdf.status(), await pdf.text()).toBe(200);
  expect(pdf.headers()['content-type']).toBe('application/pdf');
  expect(pdf.headers()['content-disposition']).toMatch(/^attachment; filename="morefoto-otchet-group-\d{4}-\d{2}-\d{2}\.pdf"$/);
  const bytes = await pdf.body();
  expect(bytes.subarray(0, 5).toString()).toBe('%PDF-');
  expect(bytes.length).toBeGreaterThan(10_000);

  // E10-T04: the child's frame is shown only inside the area of the report.
  const framed = group.children.find((child) => child.hasPreview);
  if (framed) {
    const preview = await page.request.get('/api/v1/sales-report/children/' + framed.id + '/preview', { headers: await bearer(page) });
    expect([preview.status(), preview.headers()['content-type']]).toEqual([200, 'image/webp']);
  }

  // E10-T12: the section for the teacher on desktop and phone, the head's institution level, and the PDF download.
  for (const viewport of [
    { name: 'desktop', width: 1440, height: 1000 },
    { name: 'mobile', width: 390, height: 844 }
  ]) {
    const context = await browser.newContext({ baseURL, viewport, isMobile: viewport.width < 500, hasTouch: viewport.width < 500 });
    try {
      const teacher = await context.newPage();
      await login(teacher, 'another-teacher');
      await teacher.goto('/cabinet/reports?' + new URLSearchParams({ institutionId, shootId, groupId }));
      await expect(teacher.getByTestId('report-title')).toHaveText(group.group!.name);
      await expect(teacher.getByTestId('report-figure-total').locator('dd')).toHaveText(
        new Intl.NumberFormat('ru-RU', {
          style: 'currency',
          currency: 'RUB',
          maximumFractionDigits: group.totals.paidTotal % 100 ? 2 : 0,
          minimumFractionDigits: group.totals.paidTotal % 100 ? 2 : 0
        }).format(group.totals.paidTotal / 100)
      );
      const statusChips = teacher.getByTestId('report-child-status').filter({ visible: true });
      await expect(statusChips).toHaveCount(Math.min(group.children.length, 50));
      await expect(teacher.getByTestId('report-format')).toHaveCount(group.products.length);
      if (framed) await expect(teacher.locator('[data-testid="report-children"] img').filter({ visible: true }).first()).toBeVisible();
      expect(await teacher.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
      await teacher.screenshot({
        path: testInfo.outputPath('e10-' + viewport.name + '-teacher-group.png'),
        fullPage: true,
        animations: 'disabled'
      });
      const download = teacher.waitForEvent('download');
      await teacher.getByTestId('report-pdf').click();
      expect((await download).suggestedFilename()).toMatch(/^morefoto-otchet-group-\d{4}-\d{2}-\d{2}\.pdf$/);

      const head = await context.newPage();
      await login(head, 'head');
      await head.goto('/cabinet/reports?' + new URLSearchParams({ institutionId }));
      await expect(head.getByTestId('report-title')).toHaveText(institution.institution!.name);
      await expect(head.getByTestId('report-row-link').filter({ visible: true }).filter({ hasText: shoot.shoot!.name })).toHaveCount(1);
      await head.getByTestId('report-row-link').filter({ visible: true }).filter({ hasText: shoot.shoot!.name }).click();
      await expect(head.getByTestId('report-title')).toHaveText(shoot.shoot!.name);
      expect(await head.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
      await head.screenshot({
        path: testInfo.outputPath('e10-' + viewport.name + '-head-shoot.png'),
        fullPage: true,
        animations: 'disabled'
      });
    } finally {
      await context.close();
    }
  }

  // The organizer starts at all institutions; the menu leads every role to the section.
  await page.setViewportSize({ width: 1440, height: 1000 });
  await page.goto('/cabinet/overview');
  await page.getByRole('link', { name: 'Отчёты' }).first().click();
  await expect(page).toHaveURL(/\/cabinet\/reports$/);
  await expect(page.getByTestId('report-title')).toHaveText('Все учреждения');
  await page.screenshot({ path: testInfo.outputPath('e10-desktop-organizer-all.png'), fullPage: true, animations: 'disabled' });
});
