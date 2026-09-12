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
  if (account === 'teacher') await expect(page.getByRole('heading', { name: 'Профиль', exact: true })).toBeVisible();
  else if (account === 'unassigned') await expect(page.getByRole('heading', { name: 'Доступ к кабинету не назначен' })).toBeVisible();
  else await expect(page.getByRole('button', { name: 'Новая продукция', exact: true })).toBeEnabled();
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
