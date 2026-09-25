import { readFileSync, writeFileSync } from 'node:fs';
import { test, expect, type APIResponse, type Browser, type Page } from '@playwright/test';
import { login, password, token } from './helpers.js';

type Fixture = Record<'open' | 'preparing' | 'closed' | 'revoked', { token: string; groupId: string; photoId: string }>;
type Line = { assignmentId: string; productId: string; quantity: number };
const fixture = JSON.parse(readFileSync('var/e4-fixture.json', 'utf8')) as Fixture;
const gallery = (kind: keyof Fixture = 'open') => '/api/v1/public/galleries/' + fixture[kind].token;
const key = () => crypto.randomUUID().replace(/-/g, '');
const buyer = { name: 'Анна Тестовая', phone: '8 (900) 555-01-02', email: 'Order.E5@Example.test', comment: 'E5 проверка', reviewed: true };
// The verifier proves that none of these secrets is stored in clear text.
const secrets = { accessKeys: [] as string[], idempotencyKeys: [] as string[], galleryToken: fixture.open.token, orderIds: [] as string[] };
let printId = '';
let assignment = '';
let institutionId = '';

async function body(response: APIResponse, status = 200) {
  expect(response.status(), await response.text()).toBe(status);
  return response.json();
}
async function auth(page: Page) {
  return { Authorization: 'Bearer ' + (await token(page)) };
}
async function quote(page: Page, lines: Line[]) {
  return (await body(await page.request.post(gallery() + '/quotes', { data: { lines } }))).data as {
    quoteToken: string;
    quote: { total: number };
  };
}
async function order(page: Page, data: Record<string, unknown>, idempotencyKey = key(), kind: keyof Fixture = 'open') {
  secrets.idempotencyKeys.push(idempotencyKey);
  return page.request.post(gallery(kind) + '/orders', { headers: { 'Idempotency-Key': idempotencyKey }, data });
}
async function setPrintPrice(page: Page, price: number) {
  const path = '/api/v1/groups/' + fixture.open.groupId + '/conditions';
  const snapshot = (await body(await page.request.get(path, { headers: await auth(page) }))).data;
  await body(
    await page.request.put(path, {
      headers: { ...(await auth(page)), 'Idempotency-Key': key() },
      data: {
        revision: snapshot.revision,
        catalogRevision: snapshot.catalogRevision,
        conditionsRevision: snapshot.conditionsRevision,
        inherit: false,
        giftEnabled: snapshot.giftEnabled ?? true,
        giftThreshold: snapshot.giftThreshold,
        giftForStaff: snapshot.giftForStaff,
        products: snapshot.products.map((p: { id: string; price: number; active: boolean; staffDiscount: boolean }) => ({
          id: p.id,
          price: p.id === printId ? price : p.price,
          active: p.active,
          staffDiscount: p.staffDiscount
        }))
      }
    })
  );
}
/** Signs a staff account in a fresh context: repeated logins in one tab would be redirected away from /login. */
async function asStaff(browser: Browser, baseURL: string | undefined, account: string, check: (page: Page) => Promise<void>) {
  const context = await browser.newContext({ baseURL });
  try {
    const page = await context.newPage();
    await page.goto('/login');
    await page.getByRole('textbox', { name: 'Email', exact: true }).fill(account + '@example.invalid');
    await page.getByLabel('Пароль', { exact: true }).fill(password);
    const profile = page.waitForResponse((r) => new URL(r.url()).pathname === '/api/v1/me');
    await page.getByRole('button', { name: 'Войти', exact: true }).click();
    expect((await profile).status()).toBe(200);
    await check(page);
  } finally {
    await context.close();
  }
}
function remember(created: { id: string; accessKey: string }) {
  secrets.orderIds.push(created.id);
  secrets.accessKeys.push(created.accessKey);
  writeFileSync('var/e5-orders.json', JSON.stringify(secrets));
}

test.beforeAll(async ({ browser }) => {
  const page = await browser.newPage();
  try {
    const catalog = (await body(await page.request.get(gallery() + '/catalog'))).data;
    printId = catalog.products.find((p: { kind: string }) => p.kind === 'physical').id;
    expect(catalog.capabilities).toEqual({ receiptChannels: [], purchaseEnabled: true });
    expect(catalog.purchaseTerms).toBeNull();
    const photos = (await body(await page.request.get(gallery()))).data.children.flatMap(
      (child: { photos: { assignmentId: string }[] }) => child.photos
    );
    assignment = photos[0].assignmentId;
    await login(page);
    const institutions = (await body(await page.request.get('/api/v1/institutions?q=E4', { headers: await auth(page) }))).data.items;
    institutionId = institutions.find((item: { name: string }) => item.name === 'E4 Тестовый детский сад').id;
    // Assign the regular curator to the E4 institution without dropping the curator's earlier scope.
    const users = (
      await body(await page.request.get('/api/v1/users?q=' + encodeURIComponent('curator@example.invalid'), { headers: await auth(page) }))
    ).data;
    const curator = users.items.find((item: { email: string }) => item.email === 'curator@example.invalid');
    const detail = (await body(await page.request.get('/api/v1/users/' + curator.id, { headers: await auth(page) }))).data;
    const options = (await body(await page.request.get('/api/v1/users/assignment-options', { headers: await auth(page) }))).data;
    await body(
      await page.request.patch('/api/v1/users/' + curator.id, {
        headers: { ...(await auth(page)), 'Idempotency-Key': key() },
        data: {
          name: detail.name,
          email: detail.email,
          role: 'curator',
          active: true,
          institutionIds: [...new Set([...detail.institutionIds, institutionId])],
          groupIds: [],
          replaceAssignments: false,
          assignmentSignature: options.assignmentSignature,
          revision: detail.revision
        }
      })
    );
  } finally {
    await page.close();
  }
});

test('E5: checkout is idempotent, keeps one order per quote and hides secrets', async ({ page }) => {
  const lines = [{ assignmentId: assignment, productId: printId, quantity: 1 }];
  const priced = await quote(page, lines);
  const data = { lines, buyer, quoteToken: priced.quoteToken };
  await body(await page.request.post(gallery() + '/orders', { data }), 422);
  const replayKey = key();
  const response = await order(page, data, replayKey);
  const created = (await body(response, 201)).data;
  remember(created);
  expect(response.headers()['location']).toBe('/api/v1/public/orders/current');
  expect(response.headers()['cache-control']).toBe('no-store');
  expect(created.number).toMatch(/^MF-\d{6,}$/);
  expect(created.accessKey).toMatch(/^[a-f0-9]{64}$/);
  expect(created).toMatchObject({ paymentStatus: 'unpaid', productionStatus: 'not-started', groupId: fixture.open.groupId });
  expect(created.buyer).toEqual({
    name: 'Анна Тестовая',
    phone: '+79005550102',
    email: 'order.e5@example.test',
    comment: 'E5 проверка',
    receiptChannel: null
  });
  expect(created.quote.total).toBe(priced.quote.total);
  expect(created.quote.lines[0].id).toMatch(/^[a-f0-9-]{36}$/);
  expect(JSON.stringify(created)).not.toContain(fixture.open.token);
  expect(JSON.stringify(created.quote)).not.toContain('thumbSrc');
  const replay = (await body(await order(page, data, replayKey), 201)).data;
  expect([replay.id, replay.number, replay.accessKey]).toEqual([created.id, created.number, created.accessKey]);
  expect((await body(await order(page, { ...data, buyer: { ...buyer, name: 'Другое имя' } }, replayKey), 409)).error.code).toBe(
    'IDEMPOTENCY_CONFLICT'
  );
  expect((await body(await order(page, data), 409)).error.code).toBe('QUOTE_ALREADY_USED');

  const concurrent = await quote(page, lines);
  const sameKey = key();
  const same = await Promise.all([1, 2, 3, 4].map(() => order(page, { ...data, quoteToken: concurrent.quoteToken }, sameKey)));
  const results = await Promise.all(same.map((item) => body(item, 201)));
  expect(new Set(results.map((item) => item.data.id)).size).toBe(1);
  expect(new Set(results.map((item) => item.data.accessKey)).size).toBe(1);
  remember(results[0].data);

  const raced = await quote(page, lines);
  const race = await Promise.all([1, 2, 3].map(() => order(page, { ...data, quoteToken: raced.quoteToken })));
  const statuses = race.map((item) => item.status()).sort();
  expect(statuses).toEqual([201, 409, 409]);
  for (const item of race) {
    const payload = await item.json();
    if (item.status() === 201) remember(payload.data);
    else expect(payload.error.code).toBe('QUOTE_ALREADY_USED');
  }
});

test('E5: checkout rejects closed, unready, revoked, invalid and repriced requests', async ({ page }) => {
  const lines = [{ assignmentId: assignment, productId: printId, quantity: 2 }];
  const priced = await quote(page, lines);
  const data = { lines, buyer, quoteToken: priced.quoteToken };
  expect((await body(await order(page, data, key(), 'closed'), 409)).error.code).toBe('GALLERY_CLOSED');
  expect((await body(await order(page, data, key(), 'preparing'), 409)).error.code).toBe('GALLERY_NOT_READY');
  expect((await body(await order(page, data, key(), 'revoked'), 404)).error.code).toBe('GALLERY_NOT_FOUND');
  for (const [patch, code] of [
    [{ buyer: { ...buyer, email: 'broken' } }, 'INVALID_BUYER_EMAIL'],
    [{ buyer: { ...buyer, reviewed: false } }, 'REVIEW_REQUIRED'],
    [{ buyer: { ...buyer, receiptChannel: 'email' } }, 'RECEIPT_CHANNEL_UNAVAILABLE'],
    [{ buyer: { ...buyer, discount: 100 } }, 'UNKNOWN_FIELD'],
    [{ total: 1 }, 'UNKNOWN_FIELD']
  ] as const)
    expect((await body(await order(page, { ...data, ...patch }), 422)).error.code).toBe(code);
  const malformed = await page.request.post(gallery() + '/orders', { headers: { 'Idempotency-Key': 'not-a-key' }, data });
  expect((await body(malformed, 422)).error.code).toBe('INVALID_IDEMPOTENCY_KEY');

  await login(page);
  const before = (await body(await order(page, data), 201)).data;
  remember(before);
  const stale = await quote(page, lines);
  await setPrintPrice(page, 12000);
  try {
    const changed = await body(await order(page, { ...data, quoteToken: stale.quoteToken }), 409);
    expect(changed.error.code).toBe('PRICE_CHANGED');
    expect(changed.error.details.quoteToken).toMatch(/^[a-f0-9]{64}$/);
    expect(changed.error.details.quote.total).toBe(24000);
    const repriced = (await body(await order(page, { ...data, quoteToken: changed.error.details.quoteToken }), 201)).data;
    remember(repriced);
    expect(repriced.quote.total).toBe(24000);
    // The purchase snapshot keeps the price that was confirmed at checkout.
    const snapshot = (await body(await page.request.get('/api/v1/public/orders/current', { headers: { 'X-Order-Key': before.accessKey } })))
      .data;
    expect(snapshot.quote.total).toBe(before.quote.total);
    expect(snapshot.quote.lines[0].unitPrice).toBe(before.quote.lines[0].unitPrice);
  } finally {
    await setPrintPrice(page, 10000);
  }
});

test('E5: personal key opens only its own order', async ({ request }) => {
  const orderKey = secrets.accessKeys[0]!;
  const response = await request.get('/api/v1/public/orders/current', { headers: { 'X-Order-Key': orderKey } });
  const data = (await body(response)).data;
  expect(response.headers()['cache-control']).toBe('no-store');
  expect(data).toMatchObject({ id: secrets.orderIds[0], institutionName: 'E4 Тестовый детский сад', audience: 'regular' });
  expect(data.period).toMatchObject({ groupId: fixture.open.groupId, state: 'open', timezone: 'Europe/Moscow' });
  expect(Date.parse(data.accessKeyExpiresAt) - Date.now()).toBeGreaterThan(29 * 24 * 3600 * 1000);
  expect(data).not.toHaveProperty('institutionId');
  expect(data).not.toHaveProperty('accessKey');
  const headers: Record<string, string>[] = [
    {},
    { 'X-Order-Key': key() + key() },
    { 'X-Order-Key': data.number },
    { 'X-Order-Key': fixture.open.token }
  ];
  for (const header of headers)
    expect((await body(await request.get('/api/v1/public/orders/current', { headers: header }), 404)).error.code).toBe('ORDER_NOT_FOUND');
});

test('E5: staff see orders only in their scope', async ({ page, browser, baseURL }) => {
  await body(await page.request.get('/api/v1/orders'), 401);
  await login(page);
  const organizer = await auth(page);
  const number = (
    await body(await page.request.get('/api/v1/public/orders/current', { headers: { 'X-Order-Key': secrets.accessKeys[0]! } }))
  ).data.number;
  const listed = await body(await page.request.get('/api/v1/orders?q=' + number, { headers: organizer }));
  expect(listed.meta).toMatchObject({ page: 1, pageSize: 50, total: 1 });
  expect(listed.data.items[0]).toMatchObject({ id: secrets.orderIds[0], number, institutionId });
  expect(JSON.stringify(listed)).not.toMatch(/accessKey|galleryToken|idempotency/i);
  const all = await body(
    await page.request.get('/api/v1/orders?groupId=' + fixture.open.groupId + '&paymentStatus=unpaid&pageSize=2', { headers: organizer })
  );
  expect(all.meta.total).toBeGreaterThanOrEqual(secrets.orderIds.length);
  expect(all.data.items).toHaveLength(2);
  for (const [query, code] of [
    ['late=true', 'FILTER_UNAVAILABLE'],
    ['settlement=refund', 'FILTER_UNAVAILABLE'],
    ['pageSize=101', 'INVALID_PAGE'],
    ['paymentStatus=refunded', 'INVALID_FILTER'],
    ['unknown=1', 'UNKNOWN_FIELD']
  ])
    expect((await body(await page.request.get('/api/v1/orders?' + query, { headers: organizer }), 422)).error.code).toBe(code);
  const card = (await body(await page.request.get('/api/v1/orders/' + secrets.orderIds[0], { headers: organizer }))).data;
  expect(card.period.groupId).toBe(fixture.open.groupId);
  expect(card.correctionPhotos.map((photo: { code: string }) => photo.code)).toContain('A001');
  expect(JSON.stringify(card)).not.toMatch(/accessKey|galleryToken/);

  await asStaff(browser, baseURL, 'curator', async (viewer) => {
    const curator = await auth(viewer);
    expect((await body(await viewer.request.get('/api/v1/me', { headers: curator }))).data.permissions).toContain('order.read');
    expect((await body(await viewer.request.get('/api/v1/orders?q=' + number, { headers: curator }))).meta.total).toBe(1);
    await body(await viewer.request.get('/api/v1/orders/' + secrets.orderIds[0], { headers: curator }));
  });
  await asStaff(browser, baseURL, 'c4-curator', async (viewer) => {
    const foreign = await auth(viewer);
    expect((await body(await viewer.request.get('/api/v1/orders?q=' + number, { headers: foreign }))).meta.total).toBe(0);
    expect((await body(await viewer.request.get('/api/v1/orders/' + secrets.orderIds[0], { headers: foreign }), 404)).error.code).toBe(
      'ORDER_NOT_FOUND'
    );
  });
  for (const account of ['head', 'teacher'])
    await asStaff(browser, baseURL, account, async (viewer) => {
      const denied = await auth(viewer);
      expect((await body(await viewer.request.get('/api/v1/orders', { headers: denied }), 403)).error.code).toBe('FORBIDDEN');
      expect((await body(await viewer.request.get('/api/v1/orders/' + secrets.orderIds[0], { headers: denied }), 403)).error.code).toBe(
        'FORBIDDEN'
      );
      expect((await body(await viewer.request.get('/api/v1/me', { headers: denied }))).data.permissions).not.toContain('order.read');
    });
});

for (const viewport of [
  { name: 'desktop', width: 1280, height: 900 },
  { name: 'mobile', width: 390, height: 844 }
]) {
  test('E5: buyer checkout and staff orders on ' + viewport.name, async ({ page, browser, baseURL }, info) => {
    await page.setViewportSize(viewport);
    const errors: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));
    await page.goto('/g/' + fixture.open.token);
    await page.evaluate(({ groupId, line }) => localStorage.setItem('morefoto:cart:v1:' + groupId, JSON.stringify([line])), {
      groupId: fixture.open.groupId,
      line: { assignmentId: assignment, productId: printId, quantity: 1 }
    });
    await page.goto('/g/' + fixture.open.token + '/cart');
    await page.getByRole('link', { name: 'Оформить заказ', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Оформление заказа', exact: true })).toBeVisible();
    await expect(page.getByTestId('receipt-unavailable')).toBeVisible();
    await page.getByRole('textbox', { name: 'Имя покупателя', exact: true }).fill('Мария ' + viewport.name);
    await page.locator('[name="buyer-phone"]').fill('+7 900 555-03-04');
    await page.getByRole('textbox', { name: 'Email', exact: true }).fill(viewport.name + '.e5@example.test');
    await page.getByLabel('Состав и демонстрационные условия проверены').check();
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    await page.screenshot({ path: info.outputPath('e5-' + viewport.name + '-checkout.png'), fullPage: true, animations: 'disabled' });
    const created = page.waitForResponse((r) => r.url().endsWith('/orders') && r.request().method() === 'POST');
    await page.getByTestId('create-order').click();
    const payload = await (await created).json();
    remember(payload.data);
    await expect(page).toHaveURL(new RegExp('/orders/access/' + payload.data.accessKey + '$'));
    await expect(page.getByTestId('order-number')).toHaveText('Заказ ' + payload.data.number);
    await expect(page.getByTestId('order-payment-status')).toHaveText('Не оплачено');
    await expect(page.getByTestId('order-key-expiry')).toContainText('действует до');
    await expect(page.getByTestId('order-line')).toHaveCount(1);
    expect(await page.evaluate((groupId) => localStorage.getItem('morefoto:cart:v1:' + groupId), fixture.open.groupId)).toBeNull();
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    await page.screenshot({ path: info.outputPath('e5-' + viewport.name + '-order.png'), fullPage: true, animations: 'disabled' });

    const context = await browser.newContext({ baseURL, viewport });
    try {
      const staff = await context.newPage();
      staff.on('pageerror', (error) => errors.push(error.message));
      await login(staff);
      if (viewport.name === 'desktop') await staff.getByRole('link', { name: 'Заказы', exact: true }).first().click();
      else await staff.goto('/cabinet/orders');
      await staff.getByRole('textbox', { name: 'Номер, имя, email или телефон', exact: true }).fill(payload.data.number);
      await staff.getByRole('button', { name: 'Найти', exact: true }).click();
      await expect(staff.getByTestId('staff-order')).toHaveCount(1);
      expect(await staff.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
      await staff.screenshot({ path: info.outputPath('e5-' + viewport.name + '-staff-list.png'), fullPage: true, animations: 'disabled' });
      await staff.getByRole('link', { name: payload.data.number, exact: true }).click();
      await expect(staff.getByTestId('staff-order-number')).toHaveText(payload.data.number);
      await expect(staff.getByTestId('correction-photos')).toContainText('A001');
      await expect(staff.getByTestId('order-contacts')).toContainText(viewport.name + '.e5@example.test');
      expect(await staff.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
      await staff.screenshot({ path: info.outputPath('e5-' + viewport.name + '-staff-card.png'), fullPage: true, animations: 'disabled' });
      await staff.getByRole('link', { name: 'Все заказы', exact: true }).click();
      const search = staff.getByRole('textbox', { name: 'Номер, имя, email или телефон', exact: true });
      const reset = staff.getByRole('button', { name: 'Сбросить', exact: true });
      await expect(search).toHaveValue(payload.data.number);
      await expect(reset).toBeEnabled();
      await staff.getByRole('button', { name: 'Очистить Номер, имя, email или телефон', exact: true }).click();
      await staff.getByRole('button', { name: 'Найти', exact: true }).click();
      await expect(staff).not.toHaveURL(/[?&]q=/);
      await expect(reset).toBeDisabled();
      await expect(staff.getByTestId('staff-order').first()).toBeVisible();
    } finally {
      await context.close();
    }
    expect(errors).toEqual([]);
    expect(await page.evaluate(() => Object.keys(localStorage).some((k) => k.startsWith('morefoto:demo:')))).toBe(false);
  });
}

async function fillCheckout(page: Page, name: string, email: string, quantity: number) {
  await page.goto('/g/' + fixture.open.token);
  await page.evaluate(({ groupId, line }) => localStorage.setItem('morefoto:cart:v1:' + groupId, JSON.stringify([line])), {
    groupId: fixture.open.groupId,
    line: { assignmentId: assignment, productId: printId, quantity }
  });
  await page.goto('/g/' + fixture.open.token + '/checkout');
  await page.getByRole('textbox', { name: 'Имя покупателя', exact: true }).fill(name);
  await page.locator('[name="buyer-phone"]').fill('+7 900 555-05-06');
  await page.getByRole('textbox', { name: 'Email', exact: true }).fill(email);
  await page.getByLabel('Состав и демонстрационные условия проверены').check();
}
/**
 * Recovers an unconfirmed attempt on its own screen and proves the server replayed the one stored order. `whilePending`
 * runs while the replay is held back, before its answer reaches the page.
 */
async function recover(page: Page, key: string, email: string, whilePending?: () => Promise<void>) {
  await page.unroute('**/orders');
  await page.unroute(/\/api\/v1\/public\/galleries\/[a-f0-9]{64}$/);
  let release = () => {};
  if (whilePending) {
    const held = new Promise<void>((resolve) => (release = resolve));
    await page.route('**/orders', async (route) => {
      await held;
      await route.continue();
    });
  }
  const replayed = page.waitForResponse((r) => r.url().endsWith('/orders') && r.request().method() === 'POST');
  await page.getByTestId('recover-order').click();
  if (whilePending) {
    await whilePending();
    release();
  }
  const response = await replayed;
  expect(response.status()).toBe(201);
  expect(response.request().headers()['idempotency-key']).toBe(key);
  const payload = await response.json();
  remember(payload.data);
  await expect(page.getByTestId('order-number')).toHaveText('Заказ ' + payload.data.number);
  await login(page);
  const found = await body(await page.request.get('/api/v1/orders?q=' + encodeURIComponent(email), { headers: await auth(page) }));
  expect(found.meta.total).toBe(1);
  return { order: payload.data as { id: string; accessKey: string }, body: response.request().postData() };
}

test('E5: lost response survives group closure and reload, then recovers the same order', async ({ page }) => {
  await fillCheckout(page, 'Потерянный ответ', 'lost.e5@example.test', 3);
  const keys: string[] = [];
  await page.route('**/orders', async (route) => {
    keys.push(route.request().headers()['idempotency-key']!);
    await route.fetch();
    await route.abort();
  });
  await page.getByTestId('create-order').click();
  await expect(page.getByTestId('checkout-recovery')).toContainText('Результат отправки не подтверждён');
  // The group closes before the buyer returns: the stored attempt must not depend on a fresh quote.
  await page.route(/\/api\/v1\/public\/galleries\/[a-f0-9]{64}$/, async (route) => {
    const response = await route.fetch();
    const payload = await response.json();
    await route.fulfill({ response, json: { ...payload, data: { ...payload.data, state: 'closed' } } });
  });
  await page.reload();
  await expect(page.getByTestId('checkout-recovery')).toBeVisible();
  await expect(page.getByText('Сначала выберите фотографии')).toHaveCount(0);
  // #41: while the replay runs, the recovery screen stays with a loading button; neither the form nor an empty cart flashes.
  await recover(page, keys[0]!, 'lost.e5@example.test', async () => {
    await expect(page.getByTestId('recover-order')).toHaveClass(/v-btn--loading/);
    await expect(page.getByTestId('checkout-recovery')).toBeVisible();
    await expect(page.getByTestId('create-order')).toHaveCount(0);
    await expect(page.getByText('Сначала выберите фотографии')).toHaveCount(0);
  });
});

test('E5: 502 after a committed order keeps the attempt and recovers the same key', async ({ page }) => {
  await fillCheckout(page, 'Ответ прокси', 'gateway.e5@example.test', 4);
  const keys: string[] = [];
  await page.route('**/orders', async (route) => {
    keys.push(route.request().headers()['idempotency-key']!);
    const response = await route.fetch();
    expect(response.status()).toBe(201);
    await route.fulfill({ status: 502, contentType: 'text/html', body: '<html><body>Bad Gateway</body></html>' });
  });
  await page.getByTestId('create-order').click();
  await expect(page.getByTestId('checkout-recovery')).toContainText('Результат отправки не подтверждён');
  expect(
    await page.evaluate(
      (groupId) => JSON.parse(localStorage.getItem('morefoto:live:checkout:' + groupId) ?? '{}').requestId,
      fixture.open.groupId
    )
  ).toBe(keys[0]);
  await recover(page, keys[0]!, 'gateway.e5@example.test');
});

test('E5: refusals while recovering keep the attempt until the same order is replayed', async ({ page }, info) => {
  await fillCheckout(page, 'Отказ при повторе', 'refusal.e5@example.test', 5);
  const attempts: { key: string; body: string | null }[] = [];
  const created: { id: string; accessKey: string }[] = [];
  // PURCHASE_DISABLED is answered before the key lookup, 429 by an intermediate service: neither proves the key unused.
  const refusals = [
    { status: 403, json: { error: { code: 'PURCHASE_DISABLED', message: 'PURCHASE_DISABLED' } } },
    { status: 429, contentType: 'text/html', body: '<html><body>Too Many Requests</body></html>' }
  ];
  await page.route('**/orders', async (route) => {
    attempts.push({ key: route.request().headers()['idempotency-key']!, body: route.request().postData() });
    if (created.length) {
      await route.fulfill(refusals.shift()!);
      return;
    }
    const response = await route.fetch();
    expect(response.status()).toBe(201);
    created.push((await response.json()).data);
    await route.fulfill({ status: 502, contentType: 'text/html', body: '<html><body>Bad Gateway</body></html>' });
  });
  await page.getByTestId('create-order').click();
  await expect(page.getByTestId('checkout-recovery')).toContainText('Результат отправки не подтверждён');
  await page.getByTestId('recover-order').click();
  await expect(page.getByTestId('checkout-recovery')).toContainText('Оформление заказов сейчас недоступно');
  // Visual check of the kept refusal; the scenario continues on the desktop viewport.
  for (const viewport of [
    { name: 'mobile', width: 390, height: 844 },
    { name: 'desktop', width: 1280, height: 900 }
  ]) {
    await page.setViewportSize(viewport);
    await page.screenshot({ path: info.outputPath('e5-' + viewport.name + '-recovery.png'), fullPage: true, animations: 'disabled' });
  }
  await page.reload();
  await page.getByTestId('recover-order').click();
  await expect(page.getByTestId('checkout-recovery')).toContainText('Сервер пока не принял повтор');
  expect(refusals).toHaveLength(0);
  const replayed = await recover(page, attempts[0]!.key, 'refusal.e5@example.test');
  expect(replayed.order).toMatchObject({ id: created[0]!.id, accessKey: created[0]!.accessKey });
  // The committed attempt, both refused repeats and the replay carry the same key and the exact same body.
  for (const attempt of attempts) expect(attempt).toEqual(attempts[0]);
  expect(replayed.body).toBe(attempts[0]!.body);
});

test('E5: changed price at checkout asks the buyer to confirm the new total', async ({ page, browser, baseURL }) => {
  await page.goto('/g/' + fixture.open.token);
  await page.evaluate(({ groupId, line }) => localStorage.setItem('morefoto:cart:v1:' + groupId, JSON.stringify([line])), {
    groupId: fixture.open.groupId,
    line: { assignmentId: assignment, productId: printId, quantity: 1 }
  });
  await page.goto('/g/' + fixture.open.token + '/checkout');
  await page.getByRole('textbox', { name: 'Имя покупателя', exact: true }).fill('Новая цена');
  await page.locator('[name="buyer-phone"]').fill('+7 900 555-07-08');
  await page.getByRole('textbox', { name: 'Email', exact: true }).fill('price.e5@example.test');
  await page.getByLabel('Состав и демонстрационные условия проверены').check();
  const context = await browser.newContext({ baseURL });
  const organizer = await context.newPage();
  await login(organizer);
  await setPrintPrice(organizer, 11000);
  try {
    await page.getByTestId('create-order').click();
    await expect(page.locator('#checkout-error')).toContainText('Цена изменилась');
    await expect(page.locator('#checkout-error')).toContainText('Новый итог');
    await expect(page.getByLabel('Состав и демонстрационные условия проверены')).not.toBeChecked();
    await page.getByLabel('Состав и демонстрационные условия проверены').check();
    const created = page.waitForResponse((r) => r.url().endsWith('/orders') && r.request().method() === 'POST');
    await page.getByTestId('create-order').click();
    const payload = await (await created).json();
    remember(payload.data);
    expect(payload.data.quote.total).toBe(11000);
    await expect(page.getByTestId('order-total')).toContainText('110');
  } finally {
    await setPrintPrice(organizer, 10000);
    await context.close();
  }
});
