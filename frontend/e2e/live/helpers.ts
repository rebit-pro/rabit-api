import { expect, type Page } from '@playwright/test';
export const password = 'A8-test-only-password!42';
export const catalogPath = '/api/v1/catalog/products';
export async function login(page: Page, account = 'organizer'): Promise<void> {
  await page.goto('/login');
  await page.getByRole('textbox', { name: 'Email', exact: true }).fill(account + '@example.invalid');
  await page.getByLabel('Пароль', { exact: true }).fill(password);
  const response = page.waitForResponse((r) => r.url().endsWith('/api/v1/me'));
  await page.getByRole('button', { name: 'Войти', exact: true }).click();
  await response;
  if (account === 'unassigned') {
    await expect(page.getByRole('heading', { name: 'Доступ к кабинету не назначен' })).toBeVisible();
    return;
  }
  // U6: every staff role lands on the overview. Specs written before it start from the old home pages.
  await expect(page).toHaveURL(/\/cabinet\/overview$/);
  if (account === 'teacher' || account === 'curator' || account === 'head') {
    await page.goto('/cabinet/profile');
    await expect(page.getByRole('heading', { name: 'Профиль', exact: true })).toBeVisible();
  } else {
    await page.goto('/cabinet/catalog');
    await expect(page.getByRole('button', { name: 'Новая продукция', exact: true })).toBeEnabled();
  }
}
/** Signs out through the user menu of the cabinet shell. */
export async function logout(page: Page): Promise<void> {
  await page.getByRole('button', { name: 'Меню пользователя', exact: true }).click();
  await page.getByRole('button', { name: 'Выйти', exact: true }).click();
}
export function productRow(page: Page, name: string) {
  return page.getByRole('article').filter({ has: page.getByRole('heading', { name, exact: true }) });
}
export async function fillProduct(page: Page, name: string, price = '125,50'): Promise<void> {
  await page.getByRole('button', { name: 'Новая продукция', exact: true }).click();
  const dialog = page.getByTestId('admin-dialog');
  await dialog.getByLabel('Название продукции', { exact: true }).fill(name);
  await dialog.getByLabel('Формат', { exact: true }).fill('10×15');
  await dialog.getByLabel('Единица продажи', { exact: true }).fill('шт.');
  await dialog.getByLabel('Цена, ₽', { exact: true }).fill(price);
  await dialog.getByLabel('Описание', { exact: true }).fill('Сквозная проверка — настоящий API');
}
export async function saveProduct(page: Page, method: 'POST' | 'PATCH' = 'POST', status = 201): Promise<void> {
  const response = page.waitForResponse((r) => r.url().includes(catalogPath) && r.request().method() === method);
  await page.getByTestId('admin-dialog').getByRole('button', { name: 'Сохранить', exact: true }).click();
  expect((await response).status()).toBe(status);
  if (status < 300) await expect(page.getByTestId('admin-dialog')).not.toBeVisible();
}
export async function token(page: Page): Promise<string> {
  const value = await page.evaluate(() => localStorage.getItem('morefoto:live:auth:token'));
  expect(value).toBeTruthy();
  return value!;
}
export async function createViaApi(page: Page, name: string): Promise<void> {
  const response = await page.request.post(catalogPath, {
    headers: { Authorization: 'Bearer ' + (await token(page)), 'Idempotency-Key': crypto.randomUUID().replace(/-/g, '') },
    data: {
      name,
      description: '',
      kind: 'physical',
      price: 100,
      printCount: 1,
      format: '10×15',
      unit: 'шт.',
      active: true,
      staffDiscount: false
    }
  });
  expect(response.status()).toBe(201);
}
type Span = { start: number; end: number };
/** Peak number of requests in flight, rebuilt from browser network timings: Playwright delivers request events in its own order. */
export function peakOverlap(spans: Span[]): number {
  const edges = spans.flatMap(({ start, end }) => [
    { at: start, delta: 1 },
    { at: end, delta: -1 }
  ]);
  edges.sort((left, right) => left.at - right.at || left.delta - right.delta);
  let current = 0;

  return edges.reduce((peak, edge) => Math.max(peak, (current += edge.delta)), 0);
}
/**
 * Completed photo uploads to `path` from the Resource Timing of the page. It keeps start and end on one
 * sub-millisecond clock: the queue starts the next upload right after the previous one ends, and Playwright's
 * millisecond start times would overlap them. The frame list GET of the same path always carries a query, so an
 * upload is that path without one. Callers compare the count with the settled uploads, so no upload is missed or
 * mixed with the list. Navigation clears the buffer, so the batch is measured before it.
 */
export async function uploadSpans(page: Page, path: string): Promise<Span[]> {
  return page.evaluate(
    (uploads) =>
      (performance.getEntriesByType('resource') as PerformanceResourceTiming[])
        .filter((entry) => {
          const url = new URL(entry.name);
          return url.pathname === uploads && url.search === '' && entry.initiatorType === 'xmlhttprequest' && entry.responseEnd > 0;
        })
        .map((entry) => ({ start: entry.startTime, end: entry.responseEnd })),
    path
  );
}
