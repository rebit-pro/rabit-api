import { readFileSync } from 'node:fs';
import { test, expect, type APIResponse, type Browser, type Page } from '@playwright/test';
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

/** The title the section shows for a report level. */
const title = (level: Report): string =>
  'group' === level.level
    ? level.group!.name
    : 'shoot' === level.level
      ? level.shoot!.name
      : 'institution' === level.level
        ? level.institution!.name
        : 'Все учреждения';

/** Where entering the section without filters must land, walked through the API: single rows lead the teacher down,
 * the head only leaves the list of institutions. */
async function landing(staff: Page, role: 'head' | 'teacher'): Promise<Report> {
  let level = (await report(staff)).data;
  while (1 === level.rows.length && 'group' !== level.level && ('teacher' === role || 'all' === level.level)) {
    const id = level.rows[0]!.id;
    const params: Record<string, string> =
      'all' === level.level
        ? { institutionId: id }
        : 'institution' === level.level
          ? { institutionId: level.institution!.id, shootId: id }
          : { institutionId: level.institution!.id, shootId: level.shoot!.id, groupId: id };
    level = (await report(staff, params)).data;
  }
  return level;
}

type Viewport = { name: string; width: number; height: number };

/** Each role gets its own browser context: the session lives in localStorage, and a second login in one context is
 * sent away from /login by the router guard. */
async function asStaff(
  browser: Browser,
  baseURL: string | undefined,
  account: string,
  viewport: Viewport,
  check: (staff: Page) => Promise<void>
) {
  const mobile = viewport.width < 500;
  const context = await browser.newContext({
    baseURL,
    viewport: { width: viewport.width, height: viewport.height },
    isMobile: mobile,
    hasTouch: mobile
  });
  try {
    const staff = await context.newPage();
    await login(staff, account);
    await check(staff);
  } finally {
    await context.close();
  }
}

test.describe.configure({ mode: 'serial' });
test.use({ locale: 'ru-RU' });

test('E10-T09…T12: each role reads the sales of its own area and downloads the PDF', async ({ page, browser, baseURL }, testInfo) => {
  test.setTimeout(420000);
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
  // E10-T12: entering the section without filters. In group b the head has only the F2 institution and «another-teacher»
  // only the print group (E9), so the head lands on the institution and the teacher goes down to the group.
  for (const [account, role, level] of [
    ['head', 'head', 'institution'],
    ['another-teacher', 'teacher', 'group']
  ] as const) {
    await asStaff(browser, baseURL, account, { name: 'desktop', width: 1440, height: 1000 }, async (staff) => {
      const target = await landing(staff, role);
      expect(target.level).toBe(level);
      await staff.getByRole('link', { name: 'Отчёты' }).first().click();
      await expect(staff.getByTestId('report-title')).toHaveText(title(target));
      await expect(staff).toHaveURL(
        'group' === level ? new RegExp('groupId=' + target.group!.id) : new RegExp('institutionId=' + target.institution!.id + '$')
      );
      const crumbs = staff.getByRole('navigation', { name: 'Хлебные крошки' });
      if ('group' === level) {
        await expect(crumbs).toContainText(target.institution!.name);
        await expect(crumbs).toContainText(target.shoot!.name);
        await expect(crumbs).not.toContainText('Все учреждения');
        await staff.screenshot({
          path: testInfo.outputPath('e10-desktop-' + account + '-entry.png'),
          fullPage: true,
          animations: 'disabled'
        });
        await crumbs.getByRole('link', { name: target.shoot!.name }).click();
        // A step back is the user's choice: the single-row shoot stays open instead of descending again.
        await expect(staff.getByTestId('report-title')).toHaveText(target.shoot!.name);
      } else {
        await expect(crumbs).toHaveCount(0);
        await staff.screenshot({
          path: testInfo.outputPath('e10-desktop-' + account + '-entry.png'),
          fullPage: true,
          animations: 'disabled'
        });
      }
    });
  }
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
    // The search matches substrings: «c4-curator@…» comes first by name, so the account is picked by its exact email.
    const detail = (
      await body(
        await page.request.get('/api/v1/users?q=' + encodeURIComponent('curator@example.invalid'), { headers: await bearer(page) })
      )
    ).data.items.find((item: { email: string }) => item.email === 'curator@example.invalid');
    const assigned = (await body(await page.request.get('/api/v1/users/' + detail.id, { headers: await bearer(page) }))).data
      .institutionIds as string[];
    expect(own.rows.length).toBeGreaterThan(0);
    expect(own.rows.every((row) => assigned.includes(row.id))).toBe(true);
  } finally {
    await curatorContext.close();
  }

  // E10-T11: the PDF of the group for the organizer.
  const pdf = await page.request.get('/api/v1/sales-report/pdf', { headers: await bearer(page), params: { groupId } });
  expect(pdf.status(), await pdf.text()).toBe(200);
  expect(pdf.headers()['content-type']).toMatch(/^application\/pdf\b/);
  expect(pdf.headers()['content-disposition']).toMatch(/^attachment; filename="morefoto-otchet-group-\d{4}-\d{2}-\d{2}\.pdf"$/);
  const bytes = await pdf.body();
  expect(bytes.subarray(0, 5).toString()).toBe('%PDF-');
  expect(bytes.length).toBeGreaterThan(10_000);

  // E10-T04: the child's frame is shown only inside the area of the report.
  const framed = group.children.find((child) => child.hasPreview);
  expect(framed, 'the print group was handed over and its children have ready frames').toBeDefined();
  const preview = await page.request.get('/api/v1/sales-report/children/' + framed!.id + '/preview', { headers: await bearer(page) });
  expect([preview.status(), preview.headers()['content-type']]).toEqual([200, 'image/webp']);
  const foreign = await browser.newContext({ baseURL });
  try {
    const teacher = await foreign.newPage();
    await login(teacher, 'teacher');
    expect(
      (await teacher.request.get('/api/v1/sales-report/children/' + framed!.id + '/preview', { headers: await bearer(teacher) })).status()
    ).toBe(404);
  } finally {
    await foreign.close();
  }

  // E10-T12: the section for every role on desktop and phone, the head's institution level, and the PDF download.
  const total = new Intl.NumberFormat('ru-RU', {
    style: 'currency',
    currency: 'RUB',
    maximumFractionDigits: group.totals.paidTotal % 100 ? 2 : 0,
    minimumFractionDigits: group.totals.paidTotal % 100 ? 2 : 0
  }).format(group.totals.paidTotal / 100);
  const fits = (staff: Page) => staff.evaluate(() => document.documentElement.scrollWidth <= innerWidth);
  for (const viewport of [
    { name: 'desktop', width: 1440, height: 1000 },
    { name: 'mobile', width: 390, height: 844 }
  ]) {
    const shot = (staff: Page, name: string) =>
      staff.screenshot({ path: testInfo.outputPath('e10-' + viewport.name + '-' + name + '.png'), fullPage: true, animations: 'disabled' });
    await asStaff(browser, baseURL, 'another-teacher', viewport, async (teacher) => {
      await teacher.goto('/cabinet/reports?' + new URLSearchParams({ institutionId, shootId, groupId }));
      await expect(teacher.getByTestId('report-title')).toHaveText(group.group!.name);
      await expect(teacher.getByTestId('report-figure-total').locator('dd')).toHaveText(total);
      await expect(teacher.getByTestId('report-child-status').filter({ visible: true })).toHaveCount(Math.min(group.children.length, 50));
      await expect(teacher.getByTestId('report-format')).toHaveCount(group.products.length);
      await expect(teacher.locator('[data-testid="report-children"] img').filter({ visible: true }).first()).toBeVisible();
      expect(await fits(teacher)).toBe(true);
      await shot(teacher, 'teacher-group');
      const download = teacher.waitForEvent('download');
      await teacher.getByTestId('report-pdf').click();
      expect((await download).suggestedFilename()).toMatch(/^morefoto-otchet-group-\d{4}-\d{2}-\d{2}\.pdf$/);
    });
    await asStaff(browser, baseURL, 'head', viewport, async (head) => {
      await head.goto('/cabinet/reports?' + new URLSearchParams({ institutionId }));
      await expect(head.getByTestId('report-title')).toHaveText(institution.institution!.name);
      const row = head.getByTestId('report-row-link').filter({ visible: true }).filter({ hasText: shoot.shoot!.name });
      await expect(row).toHaveCount(1);
      await row.click();
      await expect(head.getByTestId('report-title')).toHaveText(shoot.shoot!.name);
      expect(await fits(head)).toBe(true);
      await shot(head, 'head-shoot');
    });
    // The curator stays on the assigned institutions; the organizer sees the whole path in the breadcrumbs.
    await asStaff(browser, baseURL, 'curator', viewport, async (curator) => {
      await curator.goto('/cabinet/reports');
      await expect(curator.getByTestId('report-title')).toHaveText('Все учреждения');
      await expect(curator.getByTestId('report-row-link').filter({ visible: true })).toHaveCount((await report(curator)).data.rows.length);
      expect(await fits(curator)).toBe(true);
      await shot(curator, 'curator-all');
    });
    await asStaff(browser, baseURL, 'organizer', viewport, async (organizer) => {
      await organizer.goto('/cabinet/reports?' + new URLSearchParams({ groupId }));
      await expect(organizer.getByTestId('report-title')).toHaveText(group.group!.name);
      const path = organizer.getByRole('navigation', { name: 'Хлебные крошки' });
      await expect(path.getByRole('link', { name: 'Все учреждения' })).toBeVisible();
      await expect(path).toContainText(group.institution!.name);
      expect(await fits(organizer)).toBe(true);
      await shot(organizer, 'organizer-group');
    });
  }

  // The organizer starts at all institutions; the menu leads every role to the section.
  await page.setViewportSize({ width: 1440, height: 1000 });
  await page.goto('/cabinet/overview');
  await page.getByRole('link', { name: 'Отчёты' }).first().click();
  await expect(page).toHaveURL(/\/cabinet\/reports$/);
  await expect(page.getByTestId('report-title')).toHaveText('Все учреждения');
  await page.screenshot({ path: testInfo.outputPath('e10-desktop-organizer-all.png'), fullPage: true, animations: 'disabled' });
});
