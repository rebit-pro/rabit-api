import { readFileSync } from 'node:fs';
import { test, expect, type APIResponse } from '@playwright/test';
import { login, productAdd, token } from './helpers.js';

/** E7: a gallery whose print sales closed a day ago and whose digital sales go on for six more days. */
type Fixture = Record<'digital' | 'closed', { token: string; groupId: string }>;
const fixture = JSON.parse(readFileSync('var/e4-fixture.json', 'utf8')) as Fixture;
const path = '/api/v1/public/galleries/' + fixture.digital.token;
type Gallery = {
  state: string;
  closesAt: string;
  digitalSalesUntil: string;
  referenceNow: string;
  children: { code: string; photos: { assignmentId: string; code: string }[] }[];
};
let printId = '';
let fileId = '';
let gallery: Gallery;

async function body(response: APIResponse, status = 200) {
  expect(response.status(), await response.text()).toBe(status);
  return response.json();
}

test.beforeAll(async ({ browser }) => {
  const page = await browser.newPage();
  try {
    await login(page);
    const auth = { Authorization: 'Bearer ' + (await token(page)) };
    for (const kind of ['physical', 'digital'] as const) {
      const created = await body(
        await page.request.post('/api/v1/catalog/products', {
          headers: { ...auth, 'Idempotency-Key': crypto.randomUUID().replace(/-/g, '') },
          data: {
            name: kind === 'physical' ? 'E7 Печать 15×21' : 'E7 Электронный кадр',
            description: '',
            kind,
            price: kind === 'physical' ? 20000 : 15000,
            printCount: kind === 'physical' ? 1 : 0,
            format: kind === 'physical' ? '15×21' : 'Файл',
            unit: 'шт.',
            staffDiscount: false,
            active: true
          }
        }),
        201
      );
      if (kind === 'physical') printId = created.data.id as string;
      else fileId = created.data.id as string;
    }
    gallery = (await body(await page.request.get(path))).data as Gallery;
  } finally {
    await page.close();
  }
});

test('E7: the digital window is served between the print and the digital deadlines', async ({ request }) => {
  expect(gallery.state).toBe('digital');
  const now = Date.parse(gallery.referenceNow);
  expect(Date.parse(gallery.closesAt)).toBeLessThanOrEqual(now);
  expect(Date.parse(gallery.digitalSalesUntil)).toBeGreaterThan(now);
  expect(((await body(await request.get('/api/v1/public/galleries/' + fixture.closed.token))).data as Gallery).state).toBe('closed');
});

test('E7: the server refuses print after its deadline and still sells digital photos', async ({ request }) => {
  const assignmentId = gallery.children[0]!.photos[0]!.assignmentId;
  const refused = await request.post(path + '/quotes', {
    data: { lines: [{ assignmentId, productId: printId, quantity: 1 }] }
  });
  expect(refused.status()).toBe(409);
  expect((await refused.json()).error.code).toBe('PRINT_SALES_CLOSED');
  const priced = (await body(await request.post(path + '/quotes', { data: { lines: [{ assignmentId, productId: fileId, quantity: 1 }] } })))
    .data;
  expect(priced.quote.lines).toHaveLength(1);
  expect(priced.quote.total).toBe(15000);
});

for (const viewport of [
  { name: 'desktop', width: 1440, height: 1000 },
  { name: 'mobile', width: 390, height: 844 }
]) {
  test('E7: digital-only gallery, cart and footer without developer credit on ' + viewport.name, async ({ page }, info) => {
    await page.setViewportSize(viewport);
    const errors: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));
    // A print line saved before the close is dropped from the stored cart with a notice.
    await page.addInitScript(
      ([groupId, assignmentId, print]) =>
        localStorage.setItem('morefoto:cart:v1:' + groupId, JSON.stringify([{ assignmentId, productId: print, quantity: 2 }])),
      [fixture.digital.groupId, gallery.children[0]!.photos[0]!.assignmentId, printId]
    );
    await page.goto('/g/' + fixture.digital.token);
    await expect(page.getByText('Печать закрыта', { exact: true })).toBeVisible();
    await expect(page.getByTestId('gallery-deadlines')).toContainText('Электронные фото');
    await expect(page.getByTestId('gallery-digital-only')).toBeVisible();
    // #219: the footer keeps the seller line and no longer credits or links the developer studio.
    const footer = page.getByTestId('legal-footer');
    await expect(footer).toBeVisible();
    await expect(footer).not.toContainText('ReBit Studio');
    await expect(footer.locator('a[href*="rebit-pro.ru"]')).toHaveCount(0);
    await page.screenshot({ path: info.outputPath('e7-' + viewport.name + '-gallery.png'), fullPage: true, animations: 'disabled' });

    const code = gallery.children[0]!.photos[0]!.code;
    await page.getByRole('button', { name: 'Открыть кадр ' + code, exact: true }).click();
    await expect(page.getByTestId('selector-digital-only')).toBeVisible();
    await expect(productAdd(page, 'E7 Печать 15×21')).toHaveCount(0);
    const priced = page.waitForResponse((r) => r.url().endsWith('/quotes') && r.request().method() === 'POST');
    await productAdd(page, 'E7 Электронный кадр').click();
    expect((await priced).status()).toBe(200);
    await expect(page.getByTestId('product-remove')).toHaveText('В корзине');
    await page.getByRole('button', { name: 'Закрыть просмотр', exact: true }).click();
    await page.getByRole('link', { name: 'Открыть корзину', exact: true }).click();
    await expect(page.getByTestId('cart-digital-only')).toContainText('Печатные позиции убраны из корзины: 1');
    await expect(page.getByTestId('cart-total')).toContainText('150');
    await expect(page.getByTestId('create-order')).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    await page.screenshot({ path: info.outputPath('e7-' + viewport.name + '-cart.png'), fullPage: true, animations: 'disabled' });
    expect(errors).toEqual([]);
  });
}
