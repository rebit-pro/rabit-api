import { existsSync, readFileSync, writeFileSync } from 'node:fs';
import { test, expect, type Browser, type Download, type Locator, type Page } from '@playwright/test';
import { login, orderConsents, png, token } from './helpers.js';
import { auth, body, link } from './f2-links.js';

// #92 slice 2: staff lists, orders and payments as server-sorted tables with removal and CSV. It runs last in its
// group: the orders it removes are reported to the E5 verifier, which the order specs before it have created.
type Fixture = Record<'open', { token: string; groupId: string; photoId: string }>;
const fixture = JSON.parse(readFileSync('var/e4-fixture.json', 'utf8')) as Fixture;
const gallery = '/api/v1/public/galleries/' + fixture.open.token;
const problems = new WeakMap<Page, string[]>();
const suffix = () => crypto.randomUUID().slice(0, 8);
/** Visible rows of a table: the desktop row and the mobile card of one record share its ID. */
const rows = (scope: Page | Locator) => scope.locator('[data-row-id]').filter({ visible: true });

/** DEC-10: a removed unpaid order keeps its consent record; the verifier allows exactly these orphans. */
function reportRemoved(orderIds: string[]): void {
  const path = 'var/e5-orders.json';
  const secrets = existsSync(path) ? JSON.parse(readFileSync(path, 'utf8')) : {};
  secrets.removedOrderIds = [...(secrets.removedOrderIds ?? []), ...orderIds];
  writeFileSync(path, JSON.stringify(secrets));
}
/** E8: the email is the only contact; `tag` makes both orders of a run findable by one search. */
async function placeOrder(page: Page, tag: string, quantity: number): Promise<{ id: string; number: string }> {
  const catalog = (await body(await page.request.get(gallery + '/catalog'))).data;
  const product = catalog.products.find((item: { kind: string }) => item.kind === 'physical');
  const photo = (await body(await page.request.get(gallery))).data.children.flatMap(
    (child: { photos: { assignmentId: string }[] }) => child.photos
  )[0];
  const lines = [{ assignmentId: photo.assignmentId, productId: product.id, quantity }];
  const priced = (await body(await page.request.post(gallery + '/quotes', { data: { lines } }))).data;
  const buyer = { email: tag + '-' + quantity + '@example.test', comment: '', reviewed: true };
  return (
    await body(
      await page.request.post(gallery + '/orders', {
        headers: { 'Idempotency-Key': crypto.randomUUID().replace(/-/g, '') },
        data: { lines, buyer, quoteToken: priced.quoteToken, consents: await orderConsents(page) }
      }),
      201
    )
  ).data;
}
async function csv(download: Promise<Download>): Promise<string[]> {
  const text = readFileSync((await (await download).path())!, 'utf8');
  expect(text.startsWith('﻿')).toBe(true);
  return text.slice(1).trimEnd().split('\r\n');
}
async function asCurator(browser: Browser, baseURL: string | undefined, check: (page: Page) => Promise<void>): Promise<void> {
  const context = await browser.newContext({ baseURL });
  try {
    const page = await context.newPage();
    await login(page, 'curator');
    await check(page);
  } finally {
    await context.close();
  }
}

test.use({ locale: 'ru-RU', acceptDownloads: true });
test.beforeEach(async ({ page, request }) => {
  expect(await (await request.get('/__e2e')).json()).toEqual({ fixture: 'rabit-real-e2e' });
  const errors: string[] = [];
  problems.set(page, errors);
  page.on('pageerror', (error) => errors.push(error.message));
  page.on('response', (response) => {
    if (new URL(response.url()).pathname.startsWith('/api/') && response.status() >= 500)
      errors.push(response.status() + ' ' + response.url());
  });
  await page.setViewportSize({ width: 1440, height: 1000 });
  await login(page);
});
test.afterEach(async ({ page }) => {
  expect(problems.get(page)).toEqual([]);
});

test('#92 T06 T09 T12 T13 T15: заказы — сортировка сервером, CSV и удаление неоплаченных', async ({ page, browser, baseURL }) => {
  const buyer = 'tables-' + suffix();
  const cheap = await placeOrder(page, buyer, 1);
  const dear = await placeOrder(page, buyer, 2);
  let removed = false;
  try {
    // T09: the curator reads orders but never removes them.
    await asCurator(browser, baseURL, async (curator) => {
      const refused = await curator.request.delete('/api/v1/orders/' + cheap.id, { headers: await auth(curator) });
      expect(refused.status()).toBe(403);
    });

    // T12: the header asks the server for the order of the whole filter; the URL keeps it through a reload.
    await page.goto('/cabinet/orders?q=' + encodeURIComponent(buyer));
    await expect(rows(page)).toHaveCount(2);
    const sorted = page.waitForRequest(
      (r) => new URL(r.url()).pathname === '/api/v1/orders' && new URL(r.url()).searchParams.get('sort') === 'total'
    );
    await page.getByRole('button', { name: 'Сортировать: Сумма', exact: true }).click();
    expect(new URL((await sorted).url()).searchParams.get('direction')).toBe('asc');
    await expect(page).toHaveURL(/sort=total&direction=asc/);
    await expect(rows(page).first()).toContainText(cheap.number);
    await page.reload();
    await expect(rows(page).first()).toContainText(cheap.number);
    await expect(rows(page).last()).toContainText(dear.number);

    // T13: the selection goes to Excel as CSV with a BOM, semicolons and one line per order.
    await rows(page).first().getByRole('checkbox').check();
    await rows(page).last().getByRole('checkbox').check();
    const picked = page.waitForEvent('download');
    await page.getByTestId('order-bulk').getByRole('button', { name: 'Выгрузить CSV', exact: true }).click();
    const lines = await csv(picked);
    expect(lines).toHaveLength(3);
    // DEC-08: the buyer is the email only (E8), there is no name or phone column.
    expect(lines[0]).toBe('Номер;Создан (мск);Учреждение;Съёмка;Группа;Email покупателя;Сумма, ₽;Оплата;Изготовление');
    expect(lines.slice(1).join('\n')).toContain(buyer + '-1@example.test');
    // DEC-07: only the selection is exported; there is no «export all».
    await expect(page.getByText('Выгрузить всё', { exact: false })).toHaveCount(0);

    // T06, T15: both unpaid orders go after one confirmation; the buyer link and the registry forget them.
    await page.getByTestId('order-bulk').getByRole('button', { name: 'Удалить неоплаченные', exact: true }).click();
    const dialog = page.getByTestId('order-remove-dialog');
    await expect(dialog).toContainText(cheap.number);
    await expect(dialog).toContainText(dear.number);
    reportRemoved([cheap.id, dear.id]);
    await dialog.getByRole('button', { name: 'Удалить', exact: true }).click();
    await expect(page.getByRole('status').filter({ hasText: 'Удалено: 2 из 2.' })).toBeVisible();
    await expect(rows(page)).toHaveCount(0);
    removed = true;
    for (const order of [cheap, dear]) {
      expect((await page.request.get('/api/v1/orders/' + order.id, { headers: await auth(page) })).status()).toBe(404);
    }
  } finally {
    if (!removed) {
      reportRemoved([cheap.id, dear.id]);
      for (const order of [cheap, dear]) await page.request.delete('/api/v1/orders/' + order.id, { headers: await auth(page) });
    }
  }
});

test('#92 T10 T11: платежи и списки сотрудников сортируются сервером по белому списку', async ({ page }) => {
  const headers = await auth(page);
  for (const path of ['/api/v1/orders', '/api/v1/payments', '/api/v1/staff-requests']) {
    const refused = await page.request.get(path + '?sort=ID', { headers });
    expect(refused.status(), path).toBe(422);
    expect((await refused.json()).error.code).toBe('INVALID_SORT');
  }
  const byAmount = (await body(await page.request.get('/api/v1/payments?sort=amount&direction=asc&pageSize=100', { headers }))).data.items;
  const amounts = byAmount.map((item: { amount: number }) => item.amount);
  expect(amounts).toEqual([...amounts].sort((a, b) => a - b));

  await page.goto('/cabinet/payments');
  const sorted = page.waitForRequest(
    (r) => new URL(r.url()).pathname === '/api/v1/payments' && new URL(r.url()).searchParams.get('sort') === 'amount'
  );
  await page.getByRole('button', { name: 'Сортировать: Сумма', exact: true }).click();
  expect(new URL((await sorted).url()).searchParams.get('direction')).toBe('asc');
  await expect(page).toHaveURL(/sort=amount&direction=asc/);
});

test('#92 T01 T05 T16: список сотрудников на проверке удаляется из таблицы и больше не держит ссылку группы', async ({ page }) => {
  const mark = 'T16 ' + suffix();
  const create = async (path: string, data: Record<string, unknown>) =>
    (await body(await page.request.post(path, { headers: await auth(page), data }), 201)).data;
  const institution = await create('/api/v1/institutions', { name: mark, address: 'Москва, Табличная улица, 92' });
  try {
    const shoot = await create('/api/v1/institutions/' + institution.id + '/shoots', { name: mark + ' съёмка', date: '2026-10-15' });
    const group = await create('/api/v1/shoots/' + shoot.id + '/groups', { name: mark + ' группа', groupKind: 'regular' });
    const upload = await body(
      await page.request.post('/api/v1/shoots/' + shoot.id + '/photos', {
        headers: { Authorization: 'Bearer ' + (await token(page)) },
        multipart: { groupId: group.id, file: { name: 't16-' + group.id + '.png', mimeType: 'image/png', buffer: png } }
      }),
      202
    );
    let revision = 0;
    await expect
      .poll(
        async () => {
          const media = await body(await page.request.get('/api/v1/shoots/' + shoot.id + '/photos', { headers: await auth(page) }));
          revision = media.data.revision;
          return media.data.items.find((item: { id: string }) => item.id === upload.data.id)?.status;
        },
        { timeout: 30000 }
      )
      .toBe('ready');
    await body(
      await page.request.post('/api/v1/groups/' + group.id + '/photo-assignments', {
        headers: await auth(page),
        data: { shootId: shoot.id, revision, photoIds: [upload.data.id], childCode: 'A' }
      })
    );
    const request = await create('/api/v1/staff-requests', {
      institutionId: institution.id,
      shootId: shoot.id,
      rows: [{ id: crypto.randomUUID(), groupId: group.id, code: 'A' }],
      comment: ''
    });
    // T05: the pending list holds the link of its group.
    expect((await link(page, group.id)).problems).toContain('staffRequestsPending');

    await page.goto('/cabinet/staff-requests');
    const row = rows(page).filter({ hasText: mark + ' съёмка' });
    await expect(row).toHaveCount(1);
    await row.getByRole('checkbox').check();
    await page.getByTestId('request-bulk').getByRole('button', { name: 'Удалить', exact: true }).click();
    const dialog = page.getByTestId('request-remove-dialog');
    await expect(dialog).toContainText(mark + ' съёмка');
    const removal = page.waitForResponse(
      (r) => r.request().method() === 'DELETE' && new URL(r.url()).pathname === '/api/v1/staff-requests/' + request.id
    );
    await dialog.getByRole('button', { name: 'Удалить', exact: true }).click();
    expect((await removal).status()).toBe(204);
    await expect(page.getByRole('status').filter({ hasText: 'Удалено: 1 из 1.' })).toBeVisible();
    await expect(row).toHaveCount(0);
    expect((await page.request.get('/api/v1/staff-requests/' + request.id, { headers: await auth(page) })).status()).toBe(404);
    expect((await link(page, group.id)).problems).not.toContain('staffRequestsPending');
  } finally {
    expect([204, 404]).toContain(
      (await page.request.delete('/api/v1/institutions/' + institution.id, { headers: await auth(page) })).status()
    );
  }
});
