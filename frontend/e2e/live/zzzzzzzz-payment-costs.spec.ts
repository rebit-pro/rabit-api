import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { test, expect, type APIResponse, type Browser, type Page } from '@playwright/test';
import { login, orderConsents, password, token } from './helpers.js';

type Fixture = Record<'open' | 'preparing' | 'closed' | 'revoked', { token: string; groupId: string; photoId: string }>;
type Product = {
  id: string;
  name: string;
  kind: 'physical' | 'digital' | 'bundle';
  price: number;
  salePrice: number;
  active: boolean;
  staffDiscount: boolean;
};
type Conditions = {
  revision: number;
  catalogRevision: number;
  conditionsRevision?: number;
  inherit?: boolean;
  products: Product[];
  giftThreshold: number;
  giftForStaff: boolean;
  paymentCosts: { enabled: boolean; rateBps: number; roundingStep: number; maxRateBps: number };
};
const fixture = JSON.parse(readFileSync('var/e4-fixture.json', 'utf8')) as Fixture;
const gallery = '/api/v1/public/galleries/' + fixture.open.token;
const globalPath = '/api/v1/catalog/conditions';
const groupPath = '/api/v1/groups/' + fixture.open.groupId + '/conditions';
const key = () => crypto.randomUUID().replace(/-/g, '');
const buyer = {
  name: 'Ирина Расходова',
  phone: '8 (900) 555-06-06',
  email: 'e6.buyer@example.test',
  comment: 'E6 проверка',
  reviewed: true
};
/** Независимая копия формулы E6 для проверки ответов сервера. */
const sale = (base: number, rateBps: number) => Math.ceil((base * 10000) / ((10000 - rateBps) * 5000)) * 5000;
/** Сумма в рублях, как её показывает интерфейс: группы разрядов через любой пробел, знак рубля. */
const rub = (minor: number) => new RegExp(String(minor / 100).replace(/\B(?=(\d{3})+(?!\d))/g, '\\s?') + '\\s?₽');

/** The E5 verifier accounts for every order and checks its secrets, so E6 adds its order to the shared browser record. */
function rememberOrder(created: { id: string; accessKey: string }, idempotencyKeys: string[]) {
  const path = 'var/e5-orders.json';
  const record = existsSync(path)
    ? JSON.parse(readFileSync(path, 'utf8'))
    : { accessKeys: [], idempotencyKeys: [], galleryToken: fixture.open.token, orderIds: [] };
  record.orderIds.push(created.id);
  record.accessKeys.push(created.accessKey);
  record.idempotencyKeys.push(...idempotencyKeys);
  mkdirSync('var', { recursive: true });
  writeFileSync(path, JSON.stringify(record));
}
async function body(response: APIResponse, status = 200) {
  expect(response.status(), await response.text()).toBe(status);
  return response.json();
}
async function auth(page: Page, idempotencyKey?: string) {
  return { Authorization: 'Bearer ' + (await token(page)), ...(idempotencyKey ? { 'Idempotency-Key': idempotencyKey } : {}) };
}
async function conditions(page: Page, path = globalPath): Promise<Conditions> {
  return (await body(await page.request.get(path, { headers: await auth(page) }))).data;
}
function globalBody(snapshot: Conditions, paymentCosts: { enabled: boolean; rateBps: number }) {
  return {
    revision: snapshot.revision,
    catalogRevision: snapshot.catalogRevision,
    products: snapshot.products.map((product) => ({
      id: product.id,
      price: product.price,
      active: product.active,
      staffDiscount: product.staffDiscount
    })),
    giftEnabled: snapshot.giftThreshold > 0,
    giftThreshold: snapshot.giftThreshold,
    giftForStaff: snapshot.giftThreshold > 0 && snapshot.giftForStaff,
    paymentCosts
  };
}
async function setPolicy(page: Page, paymentCosts: { enabled: boolean; rateBps: number }) {
  const snapshot = await conditions(page);
  return (await body(await page.request.put(globalPath, { headers: await auth(page, key()), data: globalBody(snapshot, paymentCosts) })))
    .data as {
    revision: number;
  };
}
/** Signs a staff account in a fresh context: repeated logins in one tab would be redirected away from /login. */
async function asStaff(browser: Browser, baseURL: string | undefined, account: string, check: (page: Page) => Promise<void>) {
  const context = await browser.newContext({ baseURL });
  try {
    const page = await context.newPage();
    await page.goto('/login');
    await page.getByRole('textbox', { name: 'Email', exact: true }).fill(account + '@example.invalid');
    await page.getByLabel('Пароль', { exact: true }).fill(password);
    const profile = page.waitForResponse((response) => new URL(response.url()).pathname === '/api/v1/me');
    await page.getByRole('button', { name: 'Войти', exact: true }).click();
    expect((await profile).status()).toBe(200);
    await check(page);
  } finally {
    await context.close();
  }
}

test.beforeEach(async ({ request }) => {
  expect(await (await request.get('/__e2e')).json()).toEqual({ fixture: 'rabit-real-e2e' });
});
// Later groups and verifiers read the default prices: the policy is always switched off again.
test.afterAll(async ({ browser }) => {
  const page = await browser.newPage();
  try {
    await login(page);
    if ((await conditions(page)).paymentCosts.enabled) await setPolicy(page, { enabled: false, rateBps: 380 });
  } finally {
    await page.close();
  }
});

test('E6: расходы на оплату меняют цену витрины, расчёта и нового заказа, старый заказ неизменен', async ({ page, browser, baseURL }) => {
  test.setTimeout(180000);
  await login(page);
  const initial = await conditions(page);
  expect(initial.paymentCosts).toEqual({ enabled: false, rateBps: 380, roundingStep: 5000, maxRateBps: 1000 });
  expect(initial.products.every((product) => product.salePrice === product.price)).toBe(true);

  const group = await conditions(page, groupPath);
  const print = group.products.find((product) => product.kind === 'physical' && product.active)!;
  expect(print).toBeDefined();
  const catalogBefore = (await body(await page.request.get(gallery + '/catalog'))).data;
  expect(catalogBefore.products.find((product: Product) => product.id === print.id).price).toBe(print.price);
  const photos = (await body(await page.request.get(gallery))).data.children.flatMap(
    (child: { photos: { assignmentId: string }[] }) => child.photos
  );
  const lines = [{ assignmentId: photos[0].assignmentId, productId: print.id, quantity: 1 }];
  const stale = (await body(await page.request.post(gallery + '/quotes', { data: { lines } }))).data;
  const link = '/api/v1/groups/' + fixture.preparing.groupId + '/link';
  const signatureBefore = (await body(await page.request.get(link, { headers: await auth(page) }))).data.signature;

  // Idempotent save of the policy: the same key and body replay, a different rate under the same key is a conflict.
  const replayKey = key();
  const enabledBody = globalBody(initial, { enabled: true, rateBps: 380 });
  const saved = (await body(await page.request.put(globalPath, { headers: await auth(page, replayKey), data: enabledBody }))).data;
  expect(saved.revision).toBe(initial.revision + 1);
  expect((await body(await page.request.put(globalPath, { headers: await auth(page, replayKey), data: enabledBody }))).data).toEqual(saved);
  const conflict = await body(
    await page.request.put(globalPath, {
      headers: await auth(page, replayKey),
      data: { ...enabledBody, paymentCosts: { enabled: true, rateBps: 390 } }
    }),
    409
  );
  expect(conflict.error.code).toBe('IDEMPOTENCY_CONFLICT');

  const enabled = await conditions(page);
  expect(enabled.paymentCosts).toMatchObject({ enabled: true, rateBps: 380 });
  expect(enabled.catalogRevision).toBe(initial.catalogRevision + 1);
  for (const product of enabled.products) expect(product.salePrice).toBe(sale(product.price, 380));
  const groupEnabled = await conditions(page, groupPath);
  expect(groupEnabled.paymentCosts.enabled).toBe(true);
  expect(groupEnabled.products.find((product) => product.id === print.id)!.salePrice).toBe(sale(print.price, 380));
  expect((await body(await page.request.get(link, { headers: await auth(page) }))).data.signature).not.toBe(signatureBefore);

  // The contract rejects a rate above 10 %, a missing policy and a policy in the group body.
  const current = await conditions(page);
  const rejected = [
    { path: globalPath, data: globalBody(current, { enabled: true, rateBps: 1001 }), code: 'VALIDATION_FAILED' },
    {
      path: globalPath,
      data: { ...globalBody(current, { enabled: false, rateBps: 380 }), paymentCosts: undefined },
      code: 'UNKNOWN_FIELD'
    },
    {
      path: groupPath,
      data: {
        revision: groupEnabled.revision,
        catalogRevision: groupEnabled.catalogRevision,
        conditionsRevision: groupEnabled.conditionsRevision,
        inherit: true,
        products: [],
        giftEnabled: false,
        giftThreshold: 0,
        giftForStaff: false,
        paymentCosts: { enabled: false, rateBps: 380 }
      },
      code: 'UNKNOWN_FIELD'
    }
  ];
  for (const attempt of rejected) {
    expect(
      (await body(await page.request.put(attempt.path, { headers: await auth(page, key()), data: attempt.data }), 422)).error.code
    ).toBe(attempt.code);
  }

  // Buyer side: the showcase and a fresh quote use the published price; the old quote is reported as changed.
  const published = sale(print.price, 380);
  const catalogAfter = (await body(await page.request.get(gallery + '/catalog'))).data;
  expect(catalogAfter.products.find((product: Product) => product.id === print.id).price).toBe(published);
  expect(JSON.stringify(catalogAfter)).not.toContain('paymentCosts');
  const orderKey = key();
  const changed = await body(
    await page.request.post(gallery + '/orders', {
      headers: { 'Idempotency-Key': orderKey },
      data: { lines, buyer, quoteToken: stale.quoteToken, consents: await orderConsents(page) }
    }),
    409
  );
  expect(changed.error.code).toBe('PRICE_CHANGED');
  expect(changed.error.details.quote.lines[0].product.price).toBe(published);
  expect(changed.error.details.quote.total).toBe(published);
  const createKey = key();
  const created = (
    await body(
      await page.request.post(gallery + '/orders', {
        headers: { 'Idempotency-Key': createKey },
        data: { lines, buyer, quoteToken: changed.error.details.quoteToken, consents: await orderConsents(page) }
      }),
      201
    )
  ).data;
  rememberOrder(created, [orderKey, createKey]);
  expect(created.quote.lines[0].unitPrice).toBe(published);

  // Switching the policy off restores the catalogue prices; the purchase snapshot keeps the confirmed price.
  await setPolicy(page, { enabled: false, rateBps: 380 });
  const disabled = await conditions(page);
  expect(disabled.paymentCosts.enabled).toBe(false);
  expect(disabled.products.every((product) => product.salePrice === product.price)).toBe(true);
  expect(
    (await body(await page.request.get(gallery + '/catalog'))).data.products.find((product: Product) => product.id === print.id).price
  ).toBe(print.price);
  const purchase = (await body(await page.request.get('/api/v1/public/orders/current', { headers: { 'X-Order-Key': created.accessKey } })))
    .data;
  expect([purchase.quote.lines[0].unitPrice, purchase.quote.total]).toEqual([published, published]);

  // Only the organizer manages the policy; other roles are refused before the body is validated.
  for (const account of ['curator', 'head', 'teacher']) {
    await asStaff(browser, baseURL, account, async (staff) => {
      expect((await body(await staff.request.get(globalPath, { headers: await auth(staff) }), 403)).error.code).toBe('FORBIDDEN');
      const denied = await staff.request.put(globalPath, {
        headers: await auth(staff, key()),
        data: globalBody(disabled, { enabled: true, rateBps: 380 })
      });
      expect((await body(denied, 403)).error.code).toBe('FORBIDDEN');
    });
  }
  expect((await page.request.get(globalPath)).status()).toBe(401);
});

test('E6: организатор включает учёт расходов в общих условиях и видит цену для покупателя', async ({ page }, testInfo) => {
  test.setTimeout(120000);
  await page.setViewportSize({ width: 1440, height: 1000 });
  await login(page);
  const snapshot = await conditions(page);
  const product = snapshot.products.find((item) => item.active && item.kind === 'physical')!;
  await page.goto('/cabinet/catalog?tab=conditions');
  await expect(page.getByTestId('payment-costs-summary')).toContainText('Не учитываются');
  await page.getByRole('button', { name: 'Изменить условия', exact: true }).click();
  const dialog = page.getByTestId('admin-dialog');
  await expect(dialog.getByTestId('payment-costs')).toBeVisible();
  await dialog.getByLabel('Учитывать расходы на оплату в цене', { exact: true }).check();
  await dialog.getByLabel('Ставка расходов на оплату, %', { exact: true }).fill('3,8');
  await expect(dialog.getByTestId('sale-price-' + product.id)).toContainText(rub(sale(product.price, 380)));
  await page.screenshot({ path: testInfo.outputPath('e6-desktop-payment-costs-dialog.png'), fullPage: true, animations: 'disabled' });

  await dialog.getByLabel('Ставка расходов на оплату, %', { exact: true }).fill('10,5');
  await dialog.getByRole('button', { name: 'Сохранить', exact: true }).click();
  await expect(dialog.getByText('Ставка: от 0 до 10 %, до двух знаков после запятой.')).toBeVisible();
  // #68: switching the policy off is saved despite the invalid draft rate, and the saved rate is kept.
  await dialog.getByLabel('Учитывать расходы на оплату в цене', { exact: true }).uncheck();
  const off = page.waitForResponse((response) => new URL(response.url()).pathname === globalPath && response.request().method() === 'PUT');
  await dialog.getByRole('button', { name: 'Сохранить', exact: true }).click();
  const offResponse = await off;
  expect(offResponse.status()).toBe(200);
  expect(offResponse.request().postDataJSON().paymentCosts).toEqual({ enabled: false, rateBps: 380 });
  await expect(dialog).not.toBeVisible();
  await expect(page.getByTestId('payment-costs-summary')).toContainText('Не учитываются');
  await page.getByRole('button', { name: 'Изменить условия', exact: true }).click();
  await dialog.getByLabel('Учитывать расходы на оплату в цене', { exact: true }).check();
  await dialog.getByLabel('Ставка расходов на оплату, %', { exact: true }).fill('3,8');
  const save = page.waitForResponse((response) => new URL(response.url()).pathname === globalPath && response.request().method() === 'PUT');
  await dialog.getByRole('button', { name: 'Сохранить', exact: true }).click();
  const response = await save;
  expect(response.status()).toBe(200);
  expect(response.request().postDataJSON().paymentCosts).toEqual({ enabled: true, rateBps: 380 });
  await expect(dialog).not.toBeVisible();
  await expect(page.getByTestId('payment-costs-summary')).toContainText('Учитываются: 3,80 %');
  await page.screenshot({ path: testInfo.outputPath('e6-desktop-conditions-summary.png'), fullPage: true, animations: 'disabled' });

  // The group editor shows the buyer price of the shared policy next to the group price.
  const handoff = (await body(await page.request.get('/api/v1/groups/' + fixture.open.groupId + '/link', { headers: await auth(page) })))
    .data;
  await page.goto(`/cabinet/institutions/${handoff.institutionId}/shoots/${handoff.shootId}/conditions?group=${fixture.open.groupId}`);
  await expect(page.getByTestId('payment-costs-summary')).toContainText('Учитываются: 3,80 %');
  await page.getByRole('button', { name: 'Изменить условия группы', exact: true }).click();
  const groupPrint = (await conditions(page, groupPath)).products.find((item) => item.kind === 'physical' && item.active)!;
  await expect(page.getByTestId('admin-dialog').getByTestId('sale-price-' + groupPrint.id)).toContainText(rub(groupPrint.salePrice));
  await page.getByTestId('admin-dialog').getByRole('button', { name: 'Отмена', exact: true }).click();

  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto('/cabinet/catalog?tab=conditions');
  await page.getByRole('button', { name: 'Изменить условия', exact: true }).click();
  await expect(page.getByTestId('admin-dialog').getByTestId('payment-costs')).toBeVisible();
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
  await page.screenshot({ path: testInfo.outputPath('e6-mobile-payment-costs-dialog.png'), fullPage: true, animations: 'disabled' });
});
