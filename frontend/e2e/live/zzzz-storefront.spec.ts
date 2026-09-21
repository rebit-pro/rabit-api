import { readFileSync } from 'node:fs';
import { test, expect, type APIResponse } from '@playwright/test';
import { login, token } from './helpers.js';

type Fixture = Record<'open' | 'preparing' | 'closed' | 'revoked', { token: string; groupId: string; photoId: string }>;
const fixture = JSON.parse(readFileSync('var/e4-fixture.json', 'utf8')) as Fixture;
type Photo = {
  id: string;
  assignmentId: string;
  code: string;
  thumbSrc: string;
};
type Gallery = { state: string; children: { code: string; photos: Photo[] }[] };
let printId = '';
let bundleId = '';
let assignments: Photo[] = [];
const path = (kind: keyof Fixture = 'open') => '/api/v1/public/galleries/' + fixture[kind].token;
async function body(response: APIResponse, status = 200) {
  expect(response.status(), await response.text()).toBe(status);
  return response.json();
}

test.beforeAll(async ({ browser }) => {
  const page = await browser.newPage();
  try {
    await login(page);
    const auth = { Authorization: 'Bearer ' + (await token(page)) };
    for (const kind of ['physical', 'bundle'] as const) {
      const created = await body(
        await page.request.post('/api/v1/catalog/products', {
          headers: {
            ...auth,
            'Idempotency-Key': crypto.randomUUID().replace(/-/g, '')
          },
          data: {
            name: kind === 'physical' ? 'E4 Печать 10×15' : 'E4 Электронный комплект',
            description: '',
            kind,
            price: kind === 'physical' ? 10000 : 50000,
            printCount: kind === 'physical' ? 1 : 0,
            format: kind === 'physical' ? '10×15' : 'Все кадры',
            unit: kind === 'physical' ? 'шт.' : 'комплект',
            staffDiscount: true,
            active: true
          }
        }),
        201
      );
      if (kind === 'physical') printId = created.data.id as string;
      else bundleId = created.data.id as string;
    }
    const conditionsPath = '/api/v1/groups/' + fixture.open.groupId + '/conditions';
    const snapshot = (await body(await page.request.get(conditionsPath, { headers: auth }))).data;
    await body(
      await page.request.put(conditionsPath, {
        headers: {
          ...auth,
          'Idempotency-Key': crypto.randomUUID().replace(/-/g, '')
        },
        data: {
          revision: snapshot.revision,
          catalogRevision: snapshot.catalogRevision,
          conditionsRevision: snapshot.conditionsRevision,
          inherit: false,
          giftEnabled: true,
          giftThreshold: 20000,
          giftForStaff: false,
          products: snapshot.products.map((p: { id: string; price: number }) => ({
            id: p.id,
            price: p.id === printId ? 10000 : p.id === bundleId ? 50000 : p.price,
            active: p.id === printId || p.id === bundleId,
            staffDiscount: true
          }))
        }
      })
    );
    const gallery = (await body(await page.request.get(path()))).data as Gallery;
    assignments = gallery.children.flatMap((child) => child.photos);
    expect(assignments).toHaveLength(2);
    expect(assignments[0]!.id).toBe(assignments[1]!.id);
    expect(assignments[0]!.assignmentId).not.toBe(assignments[1]!.assignmentId);
  } finally {
    await page.close();
  }
});

test('E4: capability states and protected derivatives enforce access', async ({ request }) => {
  const open = await request.get(path());
  expect(open.headers()['cache-control']).toBe('no-store');
  await body(open);
  const preparing = (await body(await request.get(path('preparing')))).data as Gallery;
  expect(preparing.state).toBe('preparing');
  expect(preparing.children).toEqual([]);
  expect(((await body(await request.get(path('closed')))).data as Gallery).state).toBe('closed');
  await body(await request.get(path('revoked')), 404);
  const image = await request.get(assignments[0]!.thumbSrc);
  expect(image.status()).toBe(200);
  expect(image.headers()['content-type']).toContain('image/webp');
  expect(image.headers()['cache-control']).toBe('no-store');
  await body(await request.get(path('closed') + '/photos/' + assignments[0]!.assignmentId + '/thumb'), 404);
  expect(
    (
      await request.get('/upload/morefoto/previews/' + fixture.open.photoId.slice(0, 2) + '/' + fixture.open.photoId + '-thumb.webp')
    ).status()
  ).toBe(404);
  await body(await request.post(path('closed') + '/quotes', { data: { lines: [] } }), 409);
});

test('E4: shared photo keeps separate child thresholds and server gift calculation', async ({ request }) => {
  const a = assignments[0]!.assignmentId;
  const b = assignments[1]!.assignmentId;
  const quote = async (lines: { assignmentId: string; productId: string; quantity: number }[]) =>
    (await body(await request.post(path() + '/quotes', { data: { lines } }))).data;
  const separate = await quote([
    { assignmentId: a, productId: printId, quantity: 1 },
    { assignmentId: b, productId: printId, quantity: 1 },
    { assignmentId: a, productId: bundleId, quantity: 1 }
  ]);
  expect(separate.quote.total).toBe(70000);
  expect(separate.quote.gifts).toEqual([]);
  const gift = await quote([
    { assignmentId: a, productId: printId, quantity: 2 },
    { assignmentId: a, productId: bundleId, quantity: 1 }
  ]);
  expect(gift.quote.total).toBe(20000);
  expect(gift.quote.giftSaving).toBe(50000);
  expect(gift.quoteToken).toMatch(/^[a-f0-9]{64}$/);
  expect(Date.parse(gift.expiresAt)).toBeGreaterThan(Date.now());
});

test('E4: quote rejects amount, identity and quantity tampering', async ({ request }) => {
  const line = {
    assignmentId: assignments[0]!.assignmentId,
    productId: printId,
    quantity: 1
  };
  for (const data of [
    { lines: [line], total: 1 },
    { lines: [{ ...line, staffDiscount: true }] },
    { lines: [{ ...line, photoId: assignments[0]!.id }] },
    { lines: [{ ...line, assignmentId: crypto.randomUUID() }] },
    { lines: [{ ...line, quantity: '1' }] },
    { lines: [{ ...line, quantity: 0 }] },
    { lines: [line, line] }
  ])
    await body(await request.post(path() + '/quotes', { data }), 422);
  const catalog = (await body(await request.get(path() + '/catalog'))).data;
  expect(catalog.capabilities.receiptChannels).toEqual([]);
  expect(catalog.capabilities.purchaseEnabled).toBe(false);
});

for (const viewport of [
  { name: 'desktop', width: 1280, height: 900 },
  { name: 'mobile', width: 390, height: 844 }
]) {
  test('E4: live gallery and server cart on ' + viewport.name, async ({ page }, info) => {
    await page.setViewportSize(viewport);
    const errors: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));
    const navigation = await page.goto('/g/' + fixture.open.token);
    expect(navigation?.headers()['referrer-policy']).toBe('no-referrer');
    await expect(page.getByRole('button', { name: 'Открыть кадр A001', exact: true })).toBeVisible();
    await expect(page.locator('[data-testid="photo-card"] img').first()).toHaveJSProperty('naturalWidth', 320);
    await page.screenshot({
      path: info.outputPath('e4-' + viewport.name + '-gallery.png'),
      fullPage: true,
      animations: 'disabled'
    });
    await page.getByRole('button', { name: 'Открыть кадр A001', exact: true }).click();
    await page.locator('.product-selector .v-select .v-field').click();
    await page.getByRole('option', { name: 'E4 Печать 10×15', exact: true }).click();
    const priced = page.waitForResponse((r) => r.url().endsWith('/quotes') && r.request().method() === 'POST');
    await page.getByTestId('add-to-cart').click();
    expect((await priced).status()).toBe(200);
    await page.getByRole('button', { name: 'Закрыть просмотр', exact: true }).click();
    await page.getByRole('link', { name: 'Открыть корзину', exact: true }).click();
    await expect(page.getByTestId('cart-total')).toContainText('100');
    await expect(page.getByRole('link', { name: 'Оформить заказ', exact: true })).toHaveCount(0);
    await page.reload();
    await expect(page.getByTestId('cart-total')).toContainText('100');
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    await page.screenshot({
      path: info.outputPath('e4-' + viewport.name + '-cart.png'),
      fullPage: true,
      animations: 'disabled'
    });
    expect(errors).toEqual([]);
    expect(await page.evaluate(() => Object.keys(localStorage).some((k) => k.startsWith('morefoto:demo:')))).toBe(false);
  });
}

test('E4: failed recalculation keeps selection and offers a real retry', async ({ page }) => {
  await page.goto('/g/' + fixture.open.token);
  await page.evaluate(
    ({ groupId, assignmentId, productId }) => {
      localStorage.setItem('morefoto:cart:v1:' + groupId, JSON.stringify([{ assignmentId, productId, quantity: 1 }]));
    },
    {
      groupId: fixture.open.groupId,
      assignmentId: assignments[0]!.assignmentId,
      productId: printId
    }
  );
  await page.route('**/quotes', (route) => route.abort());
  await page.goto('/g/' + fixture.open.token + '/cart');
  await expect(page.getByText('Не удалось проверить корзину.', { exact: false })).toBeVisible();
  await expect(page.getByTestId('cart-total')).toHaveCount(0);
  await page.unroute('**/quotes');
  await page.getByRole('button', { name: 'Повторить расчёт', exact: true }).click();
  await expect(page.getByTestId('cart-total')).toContainText('100');
});

test('E4: catalog outage does not hide an authorized gallery', async ({ page }) => {
  await page.route('**/catalog', (route) => route.fulfill({ status: 404, body: '{}' }));
  await page.goto('/g/' + fixture.open.token);
  await expect(page.getByRole('button', { name: 'Открыть кадр A001', exact: true })).toBeVisible();
  await expect(page.getByText('Ссылка недействительна')).toHaveCount(0);
});

test('E4: failed clear keeps the saved selection', async ({ page }) => {
  await page.goto('/g/' + fixture.open.token);
  await page.evaluate(
    ({ groupId, assignmentId, productId }) => {
      localStorage.setItem('morefoto:cart:v1:' + groupId, JSON.stringify([{ assignmentId, productId, quantity: 1 }]));
    },
    { groupId: fixture.open.groupId, assignmentId: assignments[0]!.assignmentId, productId: printId }
  );
  await page.goto('/g/' + fixture.open.token + '/cart');
  await expect(page.getByTestId('cart-total')).toContainText('100');
  await page.route('**/quotes', (route) => route.abort());
  await page.getByRole('button', { name: 'Очистить корзину группы' }).click();
  await page.getByRole('button', { name: 'Да, очистить' }).click();
  await expect(page.getByRole('dialog', { name: 'Очистить корзину этой группы?' }).getByRole('alert')).toContainText(
    'Не удалось проверить корзину'
  );
  expect(await page.evaluate((groupId) => localStorage.getItem('morefoto:cart:v1:' + groupId), fixture.open.groupId)).toContain(printId);
  await expect(page.getByTestId('cart-total')).toContainText('100');
});
