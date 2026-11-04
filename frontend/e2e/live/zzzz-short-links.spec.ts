import { readFileSync } from 'node:fs';
import { test, expect, type APIResponse } from '@playwright/test';
import { productAdd } from './helpers.js';

// F3/#232: the short link stays in the address bar while internal API calls use the resolved key.
type Gallery = { token: string; alias: string; groupId: string };
const fixture = JSON.parse(readFileSync('var/e4-fixture.json', 'utf8')) as Record<'open' | 'digital' | 'revoked', Gallery>;
const api = (key: string) => '/api/v1/public/galleries/' + key;

async function body(response: APIResponse, status = 200) {
  expect(response.status(), await response.text()).toBe(status);
  return response.json();
}

test('F3-T06 F3-T07: the alias resolves like the long key and nothing else', async ({ request }) => {
  for (const kind of ['open', 'digital'] as const) {
    expect(fixture[kind].alias).toMatch(/^[2-9a-km-np-z]{10}$/);
    const [short, long] = [
      (await body(await request.get(api(fixture[kind].alias)))).data,
      (await body(await request.get(api(fixture[kind].token)))).data
    ];
    // E7 windows hold for the alias as for the key; the answer contains the internal token for later API calls.
    expect([short.state, short.groupId, short.token]).toEqual([long.state, fixture[kind].groupId, fixture[kind].token]);
    expect(short.children[0].photos[0].thumbSrc).toContain('/galleries/' + fixture[kind].token + '/');
  }
  expect(fixture.digital.alias).not.toBe(fixture.open.alias);
  // A revoked key takes its alias with it; an unknown or malformed alias is just not found.
  for (const key of [fixture.revoked.alias, 'zzzzzzzzzz', fixture.open.alias.toUpperCase(), fixture.open.alias + 'a', 'o0o0o0o0o0']) {
    expect((await body(await request.get(api(key)), 404)).error.code).toBe('GALLERY_NOT_FOUND');
  }
  // Every other public call keeps taking only the long key.
  expect((await body(await request.get(api(fixture.open.alias) + '/catalog'), 404)).error.code).toBe('GALLERY_NOT_FOUND');
});

for (const viewport of [
  { name: 'desktop', width: 1440, height: 1000 },
  { name: 'mobile', width: 390, height: 844 }
]) {
  test('F3-T06: a parent opens the short link on ' + viewport.name, async ({ page }, info) => {
    test.setTimeout(90000);
    await page.setViewportSize(viewport);
    const errors: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));
    await page.goto('/g/' + fixture.open.alias + '?child=A');
    await expect(page).toHaveURL(new RegExp('/g/' + fixture.open.alias + '\\?child=A$'));
    await expect(page.getByRole('button', { name: 'Открыть кадр A001', exact: true })).toBeVisible();
    await page.reload();
    await expect(page).toHaveURL(new RegExp('/g/' + fixture.open.alias + '\\?child=A$'));
    await expect(page.getByRole('button', { name: 'Открыть кадр A001', exact: true })).toBeVisible();
    await page.getByRole('button', { name: 'Открыть кадр A001', exact: true }).click();
    expect(new URL(page.url()).searchParams.has('photo')).toBe(true);
    await page.reload();
    await expect(page.getByRole('button', { name: 'Закрыть просмотр', exact: true })).toBeVisible();
    expect(new URL(page.url()).pathname).toBe('/g/' + fixture.open.alias);
    const catalog = (await body(await page.request.get(api(fixture.open.token) + '/catalog'))).data as {
      products: { name: string; kind: string; active: boolean }[];
    };
    const print = catalog.products.find((product) => product.kind === 'physical' && product.active);
    expect(print).toBeDefined();
    const priced = page.waitForResponse((response) => response.url().endsWith('/quotes') && response.request().method() === 'POST');
    await productAdd(page, print!.name).click();
    const quoteResponse = await priced;
    expect(quoteResponse.status()).toBe(200);
    expect(new URL(quoteResponse.url()).pathname).toBe(api(fixture.open.token) + '/quotes');
    await page.getByRole('button', { name: 'Закрыть просмотр', exact: true }).click();
    await page.getByRole('link', { name: 'Открыть корзину' }).click();
    await expect(page).toHaveURL(new RegExp('/g/' + fixture.open.alias + '/cart$'));
    await expect(page.getByRole('heading', { name: 'Корзина' })).toBeVisible();
    await expect(page.getByTestId('cart-total')).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    await page.screenshot({
      path: info.outputPath('f3-' + viewport.name + '-short-link.png'),
      fullPage: true,
      animations: 'disabled'
    });

    if (viewport.name === 'desktop') {
      await page.goto('/g/' + fixture.open.alias + '?child=A');
      await page.getByTestId('gallery-question-open').click();
      const dialog = page.getByTestId('gallery-question');
      await dialog.getByLabel('Как к вам обращаться', { exact: true }).fill('Проверка короткой ссылки');
      await dialog.getByLabel('Ваш вопрос', { exact: true }).fill('Доступен ли заказ по короткой ссылке?');
      const question = page.waitForResponse((response) => response.url().endsWith('/questions') && response.request().method() === 'POST');
      await dialog.getByTestId('question-send').click();
      const questionResponse = await question;
      expect(questionResponse.status()).toBe(201);
      expect(new URL(questionResponse.url()).pathname).toBe(api(fixture.open.token) + '/questions');
      expect(new URL(page.url()).pathname).toBe('/g/' + fixture.open.alias);
    }

    await page.goto('/g/zzzzzzzzzz');
    await expect(page.getByText('Ссылка недействительна')).toBeVisible();
    expect(errors).toEqual([]);
  });
}
