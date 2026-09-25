import { test, expect, type Page } from '@playwright/test';
import { password, token } from './helpers.js';

// OPS legal: public documents with the test-only requisites of tools/run-browser-e2e.py, the cookie notice and the
// consent of a staff member who has not accepted it yet (fixture legal-pending from tools/e2e/prepare.php).
const pageErrors = new WeakMap<object, string[]>();
test.beforeEach(async ({ page }) => {
  const errors: string[] = [];
  pageErrors.set(page, errors);
  page.on('pageerror', (error) => errors.push(error.message));
});
test.afterEach(async ({ page }) => {
  expect(pageErrors.get(page)).toEqual([]);
});

async function signIn(page: Page, email: string) {
  await page.goto('/login');
  await page.getByRole('textbox', { name: 'Email', exact: true }).fill(email);
  await page.getByLabel('Пароль', { exact: true }).fill(password);
  await page.getByRole('button', { name: 'Войти', exact: true }).click();
  await expect(page).toHaveURL(/\/cabinet\/overview$/);
}

test('LEG-T10/T21: документы и реквизиты открываются без входа, подстановки заполнены', async ({ page }) => {
  const catalog = await page.request.get('/api/v1/public/legal/documents');
  expect(catalog.status()).toBe(200);
  expect(catalog.headers()['cache-control']).toBe('no-store');
  const data = (await catalog.json()).data;
  expect(data.documents.map((document: { code: string }) => document.code)).toEqual(['privacy', 'offer', 'buyer-consent', 'staff-consent']);
  expect(data.seller).toMatchObject({ published: true, name: 'ИП Тестов Тест Тестович', ogrnip: '000000000000000' });

  await page.goto('/legal');
  await expect(page.getByRole('heading', { name: 'Документы и реквизиты', exact: true })).toBeVisible();
  await expect(page.getByTestId('legal-seller')).toContainText('ИП Тестов Тест Тестович');
  await expect(page.getByTestId('legal-seller')).toContainText('pd@example.invalid');
  for (const title of ['Политика обработки персональных данных', 'Публичная оферта о продаже фотографий и фотопродукции']) {
    await expect(page.getByRole('main').getByRole('link', { name: title, exact: true })).toBeVisible();
  }

  for (const [code, heading] of [
    ['privacy', 'Политика обработки персональных данных'],
    ['offer', 'Публичная оферта о продаже фотографий и фотопродукции'],
    ['buyer-consent', 'Согласие покупателя на обработку персональных данных'],
    ['staff-consent', 'Согласие сотрудника на обработку персональных данных']
  ]) {
    await page.goto('/legal/' + code);
    const article = page.getByTestId('legal-document');
    await expect(article.getByRole('heading', { name: heading, exact: true })).toBeVisible();
    await expect(article).toContainText('ИП Тестов Тест Тестович');
    await expect(article).not.toContainText('{{');
    await expect(article).not.toContainText('будет указано до начала продаж');
  }
  await expect(page).toHaveTitle('Согласие сотрудника на обработку персональных данных — Море фото');

  expect((await page.request.get('/api/v1/public/legal/documents/offer/versions/2000-01-01')).status()).toBe(404);
  await page.goto('/legal/offer/v/2000-01-01');
  await expect(page.getByText('Такого документа или редакции нет.')).toBeVisible();

  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto('/legal/privacy');
  await expect(page.getByTestId('legal-document')).toBeVisible();
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
});

test('LEG-T11/T15: футер со ссылками и плашка о cookie без сторонних запросов', async ({ page, baseURL }) => {
  const foreign: string[] = [];
  page.on('request', (request) => {
    if (new URL(request.url()).origin !== new URL(baseURL!).origin) foreign.push(request.url());
  });
  await page.goto('/login');
  const footer = page.getByTestId('legal-footer');
  await expect(footer).toContainText('ИП Тестов Тест Тестович · ИНН 000000000000 · ОГРНИП 000000000000000');
  await expect(footer.getByRole('link', { name: 'Оферта', exact: true })).toHaveAttribute('href', '/legal/offer');

  const notice = page.getByTestId('cookie-notice');
  await expect(notice).toContainText('только необходимые cookie');
  await expect(notice.getByRole('link', { name: 'политике обработки персональных данных', exact: true })).toHaveAttribute(
    'href',
    '/legal/privacy'
  );
  await notice.getByRole('button', { name: 'Понятно', exact: true }).click();
  await expect(notice).toHaveCount(0);
  await page.reload();
  await expect(page.getByTestId('legal-footer')).toBeVisible();
  await expect(page.getByTestId('cookie-notice')).toHaveCount(0);
  expect(foreign).toEqual([]);
});

test('LEG-T20: сотрудник без согласия принимает его в кабинете один раз', async ({ page }) => {
  expect((await page.request.get('/api/v1/legal/consents/pending')).status()).toBe(401);
  await signIn(page, 'legal-pending@example.invalid');
  const dialog = page.getByTestId('staff-consent-dialog');
  await expect(dialog).toBeVisible();
  await expect(dialog.getByRole('link', { name: 'согласие на обработку персональных данных', exact: true })).toHaveAttribute(
    'href',
    /^\/legal\/staff-consent\/v\/\d{4}-\d{2}-\d{2}$/
  );
  await dialog.getByRole('button', { name: 'Принять', exact: true }).click();
  await expect(dialog.getByText('Отметьте согласие, чтобы продолжить работу в кабинете.')).toBeVisible();

  const headers = { Authorization: 'Bearer ' + (await token(page)) };
  const outdated = await page.request.post('/api/v1/legal/consents', {
    headers,
    data: { consents: [{ code: 'staff-consent', version: '2000-01-01' }] }
  });
  expect(outdated.status()).toBe(422);
  expect((await outdated.json()).error.code).toBe('CONSENT_REQUIRED');

  await dialog.getByLabel('Даю согласие на обработку персональных данных').check();
  const accepted = page.waitForResponse((r) => r.url().endsWith('/api/v1/legal/consents') && r.request().method() === 'POST');
  await dialog.getByRole('button', { name: 'Принять', exact: true }).click();
  expect((await accepted).status()).toBe(200);
  await expect(dialog).toHaveCount(0);
  const pending = await page.request.get('/api/v1/legal/consents/pending', { headers });
  expect((await pending.json()).data.documents).toEqual([]);
  await page.reload();
  await expect(page.getByRole('heading', { level: 1 }).first()).toBeVisible();
  await expect(page.getByTestId('staff-consent-dialog')).toHaveCount(0);

  await page.getByRole('button', { name: 'Меню пользователя', exact: true }).click();
  await page.getByRole('link', { name: 'Документы', exact: true }).click();
  await expect(page).toHaveURL(/\/legal$/);
});
