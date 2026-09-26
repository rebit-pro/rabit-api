import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { test, expect, type APIResponse, type Browser, type Page } from '@playwright/test';
import { login, orderConsents, token } from './helpers.js';

// G1: payment of an existing order through the YooKassa test shop. The runner passes E2E_YOOKASSA=1 only when the
// test shop keys are present; without them the same spec proves the honest disabled state.
type Fixture = Record<'open', { token: string; groupId: string; photoId: string }>;
type Created = { id: string; number: string; accessKey: string; quote: { total: number } };
const fixture = JSON.parse(readFileSync('var/e4-fixture.json', 'utf8')) as Fixture;
const gallery = '/api/v1/public/galleries/' + fixture.open.token;
const sandbox = process.env.E2E_YOOKASSA === '1';
const key = () => crypto.randomUUID().replace(/-/g, '');
const buyer = {
  name: 'Ольга Платёжная',
  phone: '8 (900) 555-07-08',
  email: 'payment.g1@example.test',
  comment: 'G1 проверка',
  reviewed: true
};
const record = {
  sandbox,
  orderIds: [] as string[],
  accessKeys: [] as string[],
  idempotencyKeys: [] as string[],
  paidOrderId: '',
  providerPaymentId: ''
};
let paid: Created | null = null;
// #97: the buyer scenario remembers its attempts by id; the registry order is checked as a contract, not as positions.
const attempts = { sbp: '', card: '' };
const attemptOf = (url: string) => new URL(url).pathname.split('/').pop() ?? '';

async function body(response: APIResponse, status = 200) {
  expect(response.status(), await response.text()).toBe(status);
  return response.json();
}
/** The E5 and G1 verifiers account for every order and secret of the run. */
function remember(created: Created, idempotencyKey: string) {
  const path = 'var/e5-orders.json';
  const orders = existsSync(path)
    ? JSON.parse(readFileSync(path, 'utf8'))
    : { accessKeys: [], idempotencyKeys: [], galleryToken: '', orderIds: [] };
  orders.orderIds.push(created.id);
  orders.accessKeys.push(created.accessKey);
  orders.idempotencyKeys.push(idempotencyKey);
  mkdirSync('var', { recursive: true });
  writeFileSync(path, JSON.stringify(orders));
  record.orderIds.push(created.id);
  record.accessKeys.push(created.accessKey);
  save();
}
function save() {
  writeFileSync('var/g1-payments.json', JSON.stringify(record));
}
async function placeOrder(page: Page): Promise<Created> {
  const catalog = (await body(await page.request.get(gallery + '/catalog'))).data;
  const product = catalog.products.find((item: { kind: string }) => item.kind === 'physical');
  const photo = (await body(await page.request.get(gallery))).data.children.flatMap(
    (child: { photos: { assignmentId: string }[] }) => child.photos
  )[0];
  const lines = [{ assignmentId: photo.assignmentId, productId: product.id, quantity: 1 }];
  const priced = (await body(await page.request.post(gallery + '/quotes', { data: { lines } }))).data;
  const idempotencyKey = key();
  const created = (
    await body(
      await page.request.post(gallery + '/orders', {
        headers: { 'Idempotency-Key': idempotencyKey },
        data: { lines, buyer, quoteToken: priced.quoteToken, consents: await orderConsents(page) }
      }),
      201
    )
  ).data as Created;
  remember(created, idempotencyKey);
  return created;
}
const asBuyer = (orderKey: string) => ({ 'X-Order-Key': orderKey });
async function startAttempt(page: Page, orderKey: string, data: Record<string, unknown>, status = 202) {
  const idempotencyKey = key();
  record.idempotencyKeys.push(idempotencyKey);
  save();
  return body(
    await page.request.post('/api/v1/public/orders/current/payment-attempts', {
      headers: { ...asBuyer(orderKey), 'Idempotency-Key': idempotencyKey },
      data
    }),
    status
  );
}
async function staff(browser: Browser, baseURL: string | undefined, account: string) {
  const context = await browser.newContext({ baseURL });
  const page = await context.newPage();
  await login(page, account);
  return { page, headers: { Authorization: 'Bearer ' + (await token(page)) }, close: () => context.close() };
}
/** Language-independent: the provider page may open in Russian or English (review #80). */
async function payOnProvider(page: Page, number: string) {
  const fields: [string, string][] = [
    ['card-number', number],
    ['expiry-month', '12'],
    ['expiry-year', '30'],
    ['security-code', '123']
  ];
  // The provider page masks its inputs: typed characters, not a pasted value.
  for (const [name, value] of fields) {
    const input = page.locator(`input[name="${name}"]`);
    await input.fill('');
    await input.pressSequentially(value, { delay: 30 });
  }
  await page.locator('button[type="submit"]').first().click();
}

test.describe.configure({ mode: 'serial' });
test.use({ locale: 'ru-RU' });

// Each mode declares only its own scenarios: the gate accepts no skipped test.
if (!sandbox) {
  test('G1: without a test shop payment is honestly off and no attempt is created', async ({ page, browser, baseURL }) => {
    const created = await placeOrder(page);
    const quote = (
      await body(await page.request.get('/api/v1/public/orders/current/payment-quote', { headers: asBuyer(created.accessKey) }))
    ).data;
    expect(quote).toMatchObject({ canPay: false, activeAttemptId: null, precedingAttemptId: null, paymentMethods: [] });
    expect(quote.quote.total).toBe(created.quote.total);
    const refused = await startAttempt(
      page,
      created.accessKey,
      { orderVersion: quote.orderVersion, precedingAttemptId: null, quoteToken: quote.quoteToken, paymentMethod: 'bank_card' },
      403
    );
    expect(refused.error.code).toBe('PAYMENT_DISABLED');
    await page.goto('/orders/access/' + created.accessKey);
    await expect(page.getByTestId('payment-unavailable')).toBeVisible();
    const ack = await page.request.post('/api/v1/webhooks/yookassa/payments', {
      data: { type: 'notification', event: 'payment.succeeded', object: { id: '00000000-000f-5000-9000-000000000000' } }
    });
    expect((await body(ack)).data).toEqual({ acknowledged: true });
    const organizer = await staff(browser, baseURL, 'organizer');
    try {
      const list = await organizer.page.request.get('/api/v1/payments', {
        headers: organizer.headers,
        params: { orderNumber: created.number }
      });
      expect((await body(list)).data.items).toEqual([]);
    } finally {
      await organizer.close();
    }
  });
}

if (sandbox) {
  test('G1-T14: buyer — refused SBP, left and continued card payment on the YooKassa page, confirmed by the server', async ({
    page
  }, testInfo) => {
    test.setTimeout(240000);
    await page.setViewportSize({ width: 1440, height: 1000 });
    const created = await placeOrder(page);
    await page.goto('/orders/access/' + created.accessKey);
    const panel = page.getByTestId('order-payment');
    await expect(panel.getByTestId('pay-bank_card')).toBeVisible();
    await expect(panel.getByTestId('pay-sbp')).toBeVisible();
    await page.screenshot({ path: testInfo.outputPath('g1-desktop-order-payment.png'), fullPage: true, animations: 'disabled' });

    // The test shop refuses SBP: a real canceled attempt, the order is declined and can be paid again.
    await panel.getByTestId('pay-sbp').click();
    await page.waitForURL(/\/orders\/payment\/[a-f0-9-]{36}$/);
    attempts.sbp = attemptOf(page.url());
    await expect(page.getByTestId('payment-return-title')).toHaveText('Оплата не прошла');
    await page.getByTestId('payment-return-order').click();
    await expect(page.getByTestId('order-payment-status')).toHaveText('Оплата отклонена');

    await page.getByTestId('pay-bank_card').click();
    await page.waitForURL(/yoomoney\.ru/, { timeout: 30000 });
    // Review #80: leaving the provider page before paying keeps the attempt open, and the buyer continues the same payment.
    await page.goto('/orders/access/' + created.accessKey);
    await page.getByTestId('payment-follow').click();
    await page.waitForURL(/\/orders\/payment\/[a-f0-9-]{36}$/);
    await expect(page.getByTestId('payment-return-title')).toHaveText('Оплата не завершена');
    await page.screenshot({ path: testInfo.outputPath('g1-desktop-payment-continue.png'), fullPage: true, animations: 'disabled' });
    await page.getByTestId('payment-continue').click();
    await page.waitForURL(/yoomoney\.ru/, { timeout: 30000 });
    await payOnProvider(page, '5555555555554444');
    await page.waitForURL(/\/checkout\/payments\/v2\/success/, { timeout: 60000 });
    // The provider's return link points to our return URL; its text depends on the page language.
    await page.locator('a[href^="http://127.0.0.1/orders/payment/"]').click();
    await page.waitForURL(/127\.0\.0\.1\/orders\/payment\/[a-f0-9-]{36}$/, { timeout: 30000 });
    attempts.card = attemptOf(page.url());
    await expect(page.getByTestId('payment-return-title')).toHaveText('Заказ оплачен', { timeout: 30000 });
    await page.screenshot({ path: testInfo.outputPath('g1-desktop-payment-return.png'), fullPage: true, animations: 'disabled' });
    await page.getByTestId('payment-return-order').click();
    await expect(page.getByTestId('order-payment-status')).toHaveText('Оплачено');
    await expect(page.getByTestId('order-paid')).toContainText('Оплачено');
    await expect(page.getByTestId('pay-bank_card')).toHaveCount(0);
    await page.setViewportSize({ width: 390, height: 844 });
    await page.screenshot({ path: testInfo.outputPath('g1-mobile-order-paid.png'), fullPage: true, animations: 'disabled' });
    paid = created;
    record.paidOrderId = created.id;
    save();
  });

  test('G1-T09, G1-T10: paid order, repeat refusals, registry scope and provider notification replay', async ({
    page,
    browser,
    baseURL
  }, testInfo) => {
    test.setTimeout(120000);
    expect(paid, 'the buyer scenario paid an order').not.toBeNull();
    const order = paid as Created;
    const quote = (await body(await page.request.get('/api/v1/public/orders/current/payment-quote', { headers: asBuyer(order.accessKey) })))
      .data;
    expect(quote).toMatchObject({ canPay: false, activeAttemptId: null, paymentMethods: ['bank_card', 'sbp'] });
    const refused = await startAttempt(
      page,
      order.accessKey,
      {
        orderVersion: quote.orderVersion,
        precedingAttemptId: quote.precedingAttemptId,
        quoteToken: quote.quoteToken,
        paymentMethod: 'bank_card'
      },
      409
    );
    expect(refused.error.code).toBe('ORDER_ALREADY_PAID');
    const current = (await body(await page.request.get('/api/v1/public/orders/current', { headers: asBuyer(order.accessKey) }))).data;
    expect(current).toMatchObject({ paymentStatus: 'paid', latePayment: false });
    expect(current.paidAt).toMatch(/^\d{4}-\d{2}-\d{2}T/);
    for (const path of [
      '/api/v1/public/orders/current/payment-quote',
      '/api/v1/public/orders/current/payment-attempts/' + quote.precedingAttemptId
    ]) {
      expect((await body(await page.request.get(path), 404)).error.code).toBe('ORDER_NOT_FOUND');
    }
    const foreign = await page.request.get('/api/v1/public/orders/current/payment-attempts/00000000-0000-4000-8000-000000000000', {
      headers: asBuyer(order.accessKey)
    });
    expect((await body(foreign, 404)).error.code).toBe('PAYMENT_ATTEMPT_NOT_FOUND');

    const organizer = await staff(browser, baseURL, 'organizer');
    try {
      const list = await organizer.page.request.get('/api/v1/payments', {
        headers: organizer.headers,
        params: { orderNumber: order.number }
      });
      const text = await list.text();
      const listed = (await body(list)).data.items as { id: string; status: string; paymentMethod: string; createdAt: string }[];
      // #97: attempts are found by the ids the buyer scenario saw; positions depend on creation times of the stand.
      expect(attempts.card).not.toBe('');
      expect(attempts.sbp).not.toBe('');
      const cardId = attempts.card;
      expect(Object.fromEntries(listed.map((item) => [item.id, [item.paymentMethod, item.status]]))).toEqual({
        [attempts.card]: ['bank_card', 'succeeded'],
        [attempts.sbp]: ['sbp', 'canceled']
      });
      // The registry contract: newest first by creation time.
      const created = listed.map((item) => Date.parse(item.createdAt));
      expect(created).toEqual([...created].sort((left, right) => right - left));
      for (const secret of [order.accessKey, ...record.idempotencyKeys, 'confirmation', 'yoomoney', 'test_'])
        expect(text).not.toContain(secret);
      const card = (await body(await organizer.page.request.get('/api/v1/payments/' + cardId, { headers: organizer.headers }))).data;
      expect(card.facts).toHaveLength(1);
      expect(card.facts[0]).toMatchObject({ amount: order.quote.total, latePayment: false, confirmedBy: 'return' });
      expect(card.facts[0].incomeAmount).toBeLessThan(order.quote.total);
      expect(card.attempts).toHaveLength(2);
      record.providerPaymentId = card.providerPaymentId;
      save();

      // A notification is only a signal: replay, a foreign payment and a malformed body never change money.
      const webhook = (provider: string, data: unknown) => page.request.post('/api/v1/webhooks/' + provider + '/payments', { data });
      const event = { type: 'notification', event: 'payment.succeeded', object: { id: card.providerPaymentId, status: 'succeeded' } };
      expect((await body(await webhook('yookassa', event))).data).toEqual({ acknowledged: true });
      expect((await body(await webhook('yookassa', event))).data).toEqual({ acknowledged: true });
      expect((await body(await webhook('yookassa', { ...event, object: { id: '00000000-000f-5000-9000-000000000000' } }))).data).toEqual({
        acknowledged: true
      });
      expect((await body(await webhook('yookassa', { event: 'payment.succeeded', object: {} }), 422)).error.code).toBe(
        'INVALID_NOTIFICATION'
      );
      expect((await body(await webhook('sberpay', event), 404)).error.code).toBe('PROVIDER_NOT_FOUND');
      const after = (await body(await organizer.page.request.get('/api/v1/payments/' + cardId, { headers: organizer.headers }))).data;
      expect(after.facts).toHaveLength(1);

      const orders = (q: Record<string, string>) =>
        organizer.page.request.get('/api/v1/orders', { headers: organizer.headers, params: { q: order.number, ...q } });
      expect((await body(await orders({ late: 'false' }))).data.items.map((item: { id: string }) => item.id)).toEqual([order.id]);
      expect((await body(await orders({ late: 'true' }))).data.items).toEqual([]);
      const staffOrder = (await body(await organizer.page.request.get('/api/v1/orders/' + order.id, { headers: organizer.headers }))).data;
      expect(staffOrder).toMatchObject({ paymentStatus: 'paid', latePayment: false });

      // Registry screens: desktop list and card, mobile card.
      await organizer.page.setViewportSize({ width: 1440, height: 1000 });
      await organizer.page.goto('/cabinet/payments?orderNumber=' + encodeURIComponent(order.number));
      await expect(organizer.page.getByTestId('payment-row')).toHaveCount(2);
      await organizer.page.screenshot({ path: testInfo.outputPath('g1-desktop-registry.png'), fullPage: true, animations: 'disabled' });
      await organizer.page.getByTestId('payment-row').first().getByRole('link').click();
      await expect(organizer.page.getByTestId('payment-card-status')).toHaveText('Оплачено');
      await expect(organizer.page.getByTestId('payment-facts')).toContainText('после возврата покупателя');
      // The layout picks the mobile navigation on load: reload after resizing.
      await organizer.page.setViewportSize({ width: 390, height: 844 });
      await organizer.page.reload();
      await expect(organizer.page.getByTestId('payment-card-status')).toHaveText('Оплачено');
      await organizer.page.screenshot({ path: testInfo.outputPath('g1-mobile-payment-card.png'), fullPage: true, animations: 'disabled' });
    } finally {
      await organizer.close();
    }
    for (const account of ['head', 'teacher']) {
      const other = await staff(browser, baseURL, account);
      try {
        expect((await body(await other.page.request.get('/api/v1/payments', { headers: other.headers }), 403)).error.code).toBe(
          'FORBIDDEN'
        );
      } finally {
        await other.close();
      }
    }
    expect((await page.request.get('/api/v1/payments')).status()).toBe(401);
  });
}

// #83: a status answer that outlives its page or its attempt changes nothing and plans no new request. The provider is
// not needed: the test answers the attempt statuses and holds chosen answers. Leaving by «Вернуться к заказу» reloads
// the document, so the in-app cases are reached through the router's history listener, as the browser Back does.
test('#83: a late status answer of a left page or a previous attempt stops its polling', async ({ page }) => {
  const orderKey = 'a'.repeat(64);
  const ids = { first: crypto.randomUUID(), second: crypto.randomUUID() };
  const amounts = { [ids.first]: 100000, [ids.second]: 250000 };
  await page.addInitScript(({ keys, value }) => keys.forEach((key) => localStorage.setItem('morefoto:payment:' + key, value)), {
    keys: [ids.first, ids.second],
    value: orderKey
  });
  const requests: Record<string, number> = { [ids.first]: 0, [ids.second]: 0 };
  const holds = new Map<string, Promise<void>>();
  const releases = new Map<string, () => void>();
  const hold = (id: string) => holds.set(id, new Promise<void>((resolve) => releases.set(id, resolve)));
  const release = (id: string) => {
    releases.get(id)?.();
    holds.delete(id);
  };
  await page.route(
    (url) => url.pathname.startsWith('/api/v1/public/orders/current/payment-attempts/'),
    async (route) => {
      const id = new URL(route.request().url()).pathname.split('/').pop()!;
      requests[id] = (requests[id] ?? 0) + 1;
      await holds.get(id);
      await route.fulfill({
        json: {
          data: {
            id,
            status: 'pending',
            amount: amounts[id] ?? 0,
            paymentMethod: 'bank_card',
            orderVersion: 'v1',
            latePayment: false,
            redirectUrl: null,
            created: false
          }
        }
      });
    }
  );
  const navigate = (path: string) =>
    page.evaluate((to) => {
      history.pushState(history.state, '', to);
      dispatchEvent(new PopStateEvent('popstate', { state: history.state }));
    }, path);
  const status = page.getByTestId('payment-return');

  hold(ids.first);
  await page.goto('/orders/payment/' + ids.first);
  await expect.poll(() => requests[ids.first]).toBe(1);
  await page.evaluate(() => ((window as unknown as { stay: number }).stay = 1));

  // Another attempt opens while the first answer is on its way: the late answer neither shows nor polls.
  await navigate('/orders/payment/' + ids.second);
  await expect(status).toContainText('2 500');
  release(ids.first);
  await expect.poll(() => requests[ids.second], { timeout: 6000 }).toBeGreaterThanOrEqual(2);
  await expect(status).toContainText('2 500');
  expect(requests[ids.first]).toBe(1);

  // The page is left while its request waits: no request follows the answer.
  hold(ids.second);
  const seen = requests[ids.second]!;
  await expect.poll(() => requests[ids.second], { timeout: 6000 }).toBe(seen + 1);
  await navigate('/orders/access/' + orderKey);
  await expect(page.getByTestId('payment-return')).toHaveCount(0);
  release(ids.second);
  await page.waitForTimeout(4000);
  expect(requests[ids.second]).toBe(seen + 1);
  expect(requests[ids.first]).toBe(1);
  // Both transitions stayed inside the application: a reload would have hidden the defect.
  expect(await page.evaluate(() => (window as unknown as { stay?: number }).stay)).toBe(1);
});
