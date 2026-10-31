import { existsSync, readFileSync, writeFileSync } from 'node:fs';
import { test, expect, type APIResponse, type Page } from '@playwright/test';
import { login, orderConsents, token } from './helpers.js';

// E9: a teacher sees what the parents of the own group bought. The spec runs after J4/J2 paid the print order of the
// print group and before L1 closes its print sales; it assigns «another-teacher» to that group, so no other spec changes.
type Fixture = { print: { token: string; groupId: string } };
type Sales = {
  groupId: string;
  paidOrders: number;
  families: number;
  prints: number;
  digitalFiles: number;
  bundles: number;
  paidTotal: number;
  products: { id: string; name: string; prints: number }[];
};
type Line = {
  childCode: string;
  photoId: string | null;
  productId: string;
  quantity: number;
  coveredByGift: boolean;
  product: { name: string; kind: string; printCount: number };
};
type Order = { paymentStatus: string; latePayment: boolean; quote: { total: number; lines: Line[] } };
const fixture = JSON.parse(readFileSync('var/e4-fixture.json', 'utf8')) as Fixture;
const groupId = fixture.print.groupId;
const summary = '/api/v1/groups/' + groupId + '/sales-summary';
const gallery = '/api/v1/public/galleries/' + fixture.print.token;
const key = () => crypto.randomUUID().replace(/-/g, '');

async function body(response: APIResponse, status = 200) {
  expect(response.status(), await response.text()).toBe(status);
  return response.json();
}
const bearer = async (page: Page) => ({ Authorization: 'Bearer ' + (await token(page)) });
const sales = async (page: Page): Promise<Sales> => (await body(await page.request.get(summary, { headers: await bearer(page) }))).data;

/** The same figures counted independently from the COM-12 orders of the group: paid only, no late payment. */
async function expected(page: Page): Promise<Sales> {
  const orders = (await body(await page.request.get('/api/v1/orders', { headers: await bearer(page), params: { groupId, pageSize: 100 } })))
    .data.items as Order[];
  const paid = orders.filter((order) => order.paymentStatus === 'paid' && !order.latePayment);
  const lines = paid.flatMap((order) => order.quote.lines);
  const physical = lines.filter((line) => line.product.kind === 'physical');
  const products = new Map<string, { id: string; name: string; prints: number }>();
  for (const line of physical) {
    const product = products.get(line.productId) ?? { id: line.productId, name: line.product.name, prints: 0 };
    product.prints += line.quantity * line.product.printCount;
    products.set(line.productId, product);
  }
  const count = (kind: string, needsPhoto: boolean) =>
    lines
      .filter((line) => line.product.kind === kind && !line.coveredByGift && (!needsPhoto || line.photoId))
      .reduce((sum, line) => sum + line.quantity, 0);
  return {
    groupId,
    paidOrders: paid.length,
    families: new Set(lines.map((line) => line.childCode)).size,
    prints: physical.reduce((sum, line) => sum + line.quantity * line.product.printCount, 0),
    digitalFiles: count('digital', true),
    bundles: count('bundle', false),
    paidTotal: paid.reduce((sum, order) => sum + order.quote.total, 0),
    products: [...products.values()].sort((left, right) => right.prints - left.prints || left.name.localeCompare(right.name))
  };
}

/** The organizer adds the print group to the teacher's assignments through the staff card API (B2). */
async function assignTeacher(page: Page, email: string) {
  const headers = { ...(await bearer(page)), 'Idempotency-Key': key() };
  const listing = await body(await page.request.get('/api/v1/users?q=' + encodeURIComponent(email), { headers }));
  const staff = listing.data.items.find((item: { email: string }) => item.email === email);
  const detail = (await body(await page.request.get('/api/v1/users/' + staff.id, { headers }))).data;
  const options = (await body(await page.request.get('/api/v1/users/assignment-options', { headers }))).data;
  await body(
    await page.request.patch('/api/v1/users/' + staff.id, {
      headers,
      data: {
        name: detail.name,
        email: detail.email,
        role: 'teacher',
        active: true,
        institutionIds: [],
        groupIds: [...new Set([...detail.groupIds, groupId])],
        replaceAssignments: false,
        assignmentSignature: options.assignmentSignature,
        revision: detail.revision
      }
    })
  );
}

/** An unpaid digital order of the group: the E5 verifier accounts for every order of the run. */
async function placeUnpaidOrder(page: Page) {
  const catalog = (await body(await page.request.get(gallery + '/catalog'))).data;
  const digital = catalog.products.find((item: { kind: string; active: boolean }) => item.kind === 'digital' && item.active);
  const child = (await body(await page.request.get(gallery))).data.children[0];
  const lines = [{ assignmentId: child.photos[0].assignmentId, productId: digital.id, quantity: 1 }];
  const priced = (await body(await page.request.post(gallery + '/quotes', { data: { lines } }))).data;
  const idempotencyKey = key();
  const created = (
    await body(
      await page.request.post(gallery + '/orders', {
        headers: { 'Idempotency-Key': idempotencyKey },
        data: {
          lines,
          buyer: { email: 'sales.e9@example.test', comment: '', reviewed: true },
          child: { firstName: 'Иван', lastName: 'Иванов' },
          quoteToken: priced.quoteToken,
          consents: await orderConsents(page)
        }
      }),
      201
    )
  ).data;
  const path = 'var/e5-orders.json';
  const orders = existsSync(path)
    ? JSON.parse(readFileSync(path, 'utf8'))
    : { accessKeys: [], idempotencyKeys: [], galleryToken: '', orderIds: [] };
  orders.orderIds.push(created.id);
  orders.accessKeys.push(created.accessKey);
  orders.idempotencyKeys.push(idempotencyKey);
  writeFileSync(path, JSON.stringify(orders));
}

test.describe.configure({ mode: 'serial' });
test.use({ locale: 'ru-RU' });

test('E9-T05…T08: the teacher of the group sees its paid sales; other roles and groups see nothing', async ({
  page,
  browser,
  baseURL
}, testInfo) => {
  test.setTimeout(180000);
  await login(page);

  // E9-T06: COM-14 equals the independent count over COM-12; the paid print order of J4 is there.
  const before = await sales(page);
  expect(before).toEqual(await expected(page));
  expect(before.paidOrders).toBeGreaterThanOrEqual(1);
  expect(before.prints).toBeGreaterThanOrEqual(1);
  expect(JSON.stringify(before)).not.toMatch(/@|accessKey|MF-\d/);

  // E9-T07: an unpaid order of the group changes nothing.
  await placeUnpaidOrder(page);
  expect(await sales(page)).toEqual(before);

  // E9-T05: roles. The owner of another group learns nothing; a broken ID is an unknown group. Since E10 the head reads
  // the groups of their own institutions only: here the head has just the F2 institution, so this group stays hidden.
  // The E10 spec assigns the head to this institution later and checks the allowed answer.
  await assignTeacher(page, 'another-teacher@example.invalid');
  expect((await page.request.get(summary)).status()).toBe(401);
  expect(
    (await body(await page.request.get('/api/v1/groups/not-a-group/sales-summary', { headers: await bearer(page) }), 404)).error.code
  ).toBe('GROUP_NOT_FOUND');
  for (const [account, status, code] of [
    ['teacher', 404, 'GROUP_NOT_FOUND'],
    ['head', 404, 'GROUP_NOT_FOUND']
  ] as const) {
    const context = await browser.newContext({ baseURL });
    try {
      const other = await context.newPage();
      await login(other, account);
      expect((await body(await other.request.get(summary, { headers: await bearer(other) }), status)).error.code).toBe(code);
    } finally {
      await context.close();
    }
  }

  // E9-T08: the teacher's overview shows the block of the group with the same figures on desktop and phone.
  for (const viewport of [
    { name: 'desktop', width: 1440, height: 1000 },
    { name: 'mobile', width: 390, height: 844 }
  ]) {
    const context = await browser.newContext({ baseURL, viewport, isMobile: viewport.width < 500, hasTouch: viewport.width < 500 });
    try {
      const teacher = await context.newPage();
      await login(teacher, 'another-teacher');
      expect(await sales(teacher)).toEqual(before);
      await teacher.goto('/cabinet/overview');
      const card = teacher.getByTestId('overview-group').filter({ has: teacher.getByTestId('group-sales-total') });
      await expect(card).toHaveCount(1);
      const block = card.getByTestId('group-sales');
      await expect(block.getByRole('heading', { name: 'Продажи', exact: true })).toBeVisible();
      const rubles = new Intl.NumberFormat('ru-RU', {
        style: 'currency',
        currency: 'RUB',
        maximumFractionDigits: 2,
        minimumFractionDigits: 0
      });
      await expect(block.getByTestId('group-sales-total')).toHaveText(rubles.format(before.paidTotal / 100));
      for (const [testId, label, value] of [
        ['group-sales-orders', 'Оплаченных заказов', before.paidOrders],
        ['group-sales-families', 'Купивших семей', before.families],
        ['group-sales-prints', 'Печатных фото', before.prints],
        ['group-sales-files', 'Цифровых файлов', before.digitalFiles]
      ] as const) {
        await expect(block.getByTestId(testId)).toHaveText(new RegExp('^' + label + '\\s*' + value + '$'));
      }
      await expect(block.getByTestId('group-sales-format')).toHaveCount(before.products.length);
      await expect(block.getByTestId('group-sales-format').first()).toContainText(before.products[0]!.name);
      expect(await teacher.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
      await block.scrollIntoViewIfNeeded();
      await teacher.screenshot({
        path: testInfo.outputPath('e9-' + viewport.name + '-teacher-sales.png'),
        fullPage: true,
        animations: 'disabled'
      });
    } finally {
      await context.close();
    }
  }
});
