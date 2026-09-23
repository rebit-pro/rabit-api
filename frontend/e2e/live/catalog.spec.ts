import { test, expect } from '@playwright/test';
import { catalogPath, createViaApi, fillProduct, login, logout, password, productRow, saveProduct, token } from './helpers.js';

const pageErrors = new WeakMap<object, string[]>();
test.beforeEach(async ({ page, request }) => {
  const marker = await request.get('/__e2e');
  expect(await marker.json()).toEqual({ fixture: 'rabit-real-e2e' });
  const errors: string[] = [];
  pageErrors.set(page, errors);
  page.on('pageerror', (error) => errors.push(error.message));
  page.on('console', (message) => {
    if (message.type() === 'error' && !message.text().startsWith('Failed to load resource:')) errors.push(message.text());
  });
  page.on('response', (response) => {
    if (new URL(response.url()).pathname.startsWith('/api/') && response.status() >= 500)
      errors.push('Unexpected API status ' + response.status());
  });
});
test.afterEach(async ({ page }) => {
  expect(pageErrors.get(page)).toEqual([]);
  expect(await page.evaluate(() => '__MOREFOTO_MOCKS__' in window)).toBe(false);
});

test('неверный пароль отклонён сервером; роль загружается через /me', async ({ page }) => {
  await page.goto('/login');
  await page.getByRole('textbox', { name: 'Email', exact: true }).fill('organizer@example.invalid');
  await page.getByLabel('Пароль', { exact: true }).fill('incorrect-password');
  const rejected = page.waitForResponse((r) => r.url().endsWith('/auth/login'));
  await page.getByRole('button', { name: 'Войти', exact: true }).click();
  expect((await rejected).status()).toBe(401);
  await expect(page.getByTestId('login-api-error')).toBeVisible();
  expect(await page.evaluate(() => localStorage.getItem('morefoto:live:auth:token'))).toBeNull();
  await login(page);
  await expect(page.getByText('Организатор', { exact: true })).toBeVisible();
  await expect(page.getByText('Ассортимент пуст. Добавьте первую продукцию.')).toBeVisible();
  const me = await page.request.get('/api/v1/me', {
    headers: { Authorization: 'Bearer ' + (await token(page)) }
  });
  expect((await me.json()).data.permissions).toContain('catalog.manage');
});

test('неподключённая оплата недоступна в настоящем режиме', async ({ page }) => {
  const apiRequests: string[] = [];
  page.on('request', (request) => {
    if (new URL(request.url()).pathname.startsWith('/api/')) apiRequests.push(request.url());
  });
  await page.goto('/orders/access/a8-disabled/payment');
  await expect(page).toHaveURL(/\/feature-unavailable$/);
  await expect(
    page.getByRole('heading', {
      name: 'Раздел пока недоступен',
      exact: true
    })
  ).toBeVisible();
  expect(apiRequests).toEqual([]);
  await page.getByRole('link', { name: 'На главную', exact: true }).click();
  await expect(page.getByRole('heading', { name: 'Вход в кабинет', exact: true })).toBeVisible();
});

test('оформление и заказ по ссылке честно сообщают о недействительном ключе', async ({ page }) => {
  await page.goto('/orders/access/a8-disabled');
  await expect(page.getByTestId('order-unavailable')).toContainText('Заказ недоступен');
  await page.goto('/g/a8-disabled/checkout');
  await expect(page.getByText('Ссылка на группу недействительна.')).toBeVisible();
  expect(await page.evaluate(() => Object.keys(localStorage).some((key) => key.startsWith('morefoto:demo:')))).toBe(false);
});

test('создание и изменение сохраняются в БД и видны в новой сессии', async ({ page, browser, baseURL }) => {
  await login(page);
  await fillProduct(page, 'A8 Портрет');
  await saveProduct(page);
  await expect(productRow(page, 'A8 Портрет')).toContainText(/125,5\s*₽/);
  await productRow(page, 'A8 Портрет').getByRole('button', { name: 'Редактировать A8 Портрет' }).click();
  await page.getByLabel('Цена, ₽', { exact: true }).fill('0');
  await page.getByLabel('Доступно для покупки', { exact: true }).uncheck();
  await saveProduct(page, 'PATCH', 200);
  await expect(productRow(page, 'A8 Портрет')).toContainText('Отключено');
  const fresh = await browser.newContext({ baseURL });
  try {
    const second = await fresh.newPage();
    await login(second);
    await expect(productRow(second, 'A8 Портрет')).toContainText('Отключено');
    await expect(productRow(second, 'A8 Портрет')).toContainText('0 ₽');
    expect(await second.evaluate(() => Object.keys(localStorage).some((key) => key.startsWith('morefoto:demo:')))).toBe(false);
  } finally {
    await fresh.close();
  }
});

test('ошибки полей и серверный 422 оставляют редактор открытым', async ({ page }) => {
  await login(page);
  await fillProduct(page, 'A8 Валидация', '-1');
  await page.getByTestId('admin-dialog').getByRole('button', { name: 'Сохранить', exact: true }).click();
  await expect(page.getByText('Цена: от 0 до 21 474 836,47 ₽, до двух знаков после запятой.')).toBeVisible();
  await page.getByLabel('Цена, ₽', { exact: true }).fill('100');
  await page.getByLabel('Описание', { exact: true }).fill('Недопустимый нулевой символ\u0000');
  await saveProduct(page, 'POST', 422);
  await expect(page.getByText('Сервер отклонил данные товара. Проверьте заполненные поля.')).toBeVisible();
  await expect(page.getByLabel('Название продукции', { exact: true })).toHaveValue('A8 Валидация');
  await page.getByLabel('Описание', { exact: true }).fill('Исправленное описание');
  await page.getByLabel('Отпечатков в единице', { exact: true }).fill('');
  await page.getByRole('combobox', { name: 'Тип продукции', exact: true }).press('Enter');
  await page.getByRole('option', { name: 'Электронный кадр', exact: true }).click();
  await expect(page.getByLabel('Отпечатков в единице', { exact: true })).toHaveCount(0);
  await saveProduct(page);
  const list = await page.request.get(catalogPath, {
    headers: { Authorization: 'Bearer ' + (await token(page)) }
  });
  const item = (await list.json()).data.items.find((value: { name: string }) => value.name === 'A8 Валидация');
  expect(item.kind).toBe('digital');
  expect(item.printCount).toBe(0);
});

test('PATCH принимает ID пути, но отклоняет настоящий query string', async ({ page }) => {
  await login(page);
  await createViaApi(page, 'A8 Query');
  const headers = { Authorization: 'Bearer ' + (await token(page)) };
  const list = await (await page.request.get(catalogPath, { headers })).json();
  const item = list.data.items.find((value: { name: string }) => value.name === 'A8 Query');
  const rejected = await page.request.patch(catalogPath + '/' + item.id + '?product_id=' + item.id, {
    headers: { ...headers, 'Idempotency-Key': 'b'.repeat(32) },
    data: { revision: list.data.revision, name: 'Unexpected change' }
  });
  expect(rejected.status()).toBe(422);
  await page.getByRole('button', { name: 'Обновить каталог' }).click();
  await expect(productRow(page, 'A8 Query')).toBeVisible();
});

test('воспитатель не может открыть или изменить каталог, включая прямой запрос', async ({ page }) => {
  await login(page, 'teacher');
  await expect(page.getByRole('link', { name: 'Каталог и цены' })).toHaveCount(0);
  const bearer = await token(page);
  const response = await page.request.get(catalogPath, {
    headers: { Authorization: 'Bearer ' + bearer }
  });
  expect(response.status()).toBe(403);
  const mutation = await page.request.post(catalogPath, {
    headers: {
      Authorization: 'Bearer ' + bearer,
      'Idempotency-Key': 'a'.repeat(32)
    },
    data: {
      name: 'Forbidden mutation',
      description: '',
      kind: 'physical',
      price: 100,
      printCount: 1,
      format: '',
      unit: 'шт.',
      active: true,
      staffDiscount: false
    }
  });
  expect(mutation.status()).toBe(403);
  await page.goto('/cabinet/catalog');
  await expect(page.getByRole('heading', { name: 'Недостаточно прав' })).toBeVisible();
});

test('изменённая роль в localStorage заменяется настоящим профилем', async ({ page }) => {
  await login(page, 'teacher');
  await page.evaluate(() => {
    const profile = JSON.parse(localStorage.getItem('morefoto:live:auth:user')!);
    profile.role = 'organizer';
    profile.permissions = ['catalog.manage'];
    localStorage.setItem('morefoto:live:auth:user', JSON.stringify(profile));
  });
  await page.goto('/cabinet/catalog');
  await expect(page.getByRole('heading', { name: 'Недостаточно прав' })).toBeVisible();
  expect(await page.evaluate(() => JSON.parse(localStorage.getItem('morefoto:live:auth:user')!).role)).toBe('teacher');
});

test('учётная запись без профиля сотрудника получает понятный отказ', async ({ page }) => {
  await login(page, 'unassigned');
  // Accounts without a staff role see the standalone access page with its own sign-out button.
  await page.getByRole('button', { name: 'Выйти', exact: true }).click();
  await expect(page).toHaveURL(/\/login$/);
});

test('выход отзывает серверный токен', async ({ page }) => {
  await login(page);
  const bearer = await token(page);
  const logoutResponse = page.waitForResponse((r) => r.url().endsWith('/auth/logout'));
  await logout(page);
  expect((await logoutResponse).status()).toBe(200);
  await expect(page).toHaveURL(/\/login$/);
  expect(
    (
      await page.request.get(catalogPath, {
        headers: { Authorization: 'Bearer ' + bearer }
      })
    ).status()
  ).toBe(401);
  await page.goto('/cabinet/catalog');
  await expect(page.getByRole('heading', { name: 'Вход в кабинет' })).toBeVisible();
});

test('отзыв сессии возвращает ко входу и затем в каталог', async ({ page, browser, baseURL }) => {
  await login(page);
  const other = await browser.newContext({ baseURL });
  try {
    await login(await other.newPage());
    await page.getByRole('button', { name: 'Обновить каталог', exact: true }).click();
    await expect(page).toHaveURL(/\/login\?reason=revoked$/);
    await page.getByRole('textbox', { name: 'Email', exact: true }).fill('organizer@example.invalid');
    await page.getByLabel('Пароль', { exact: true }).fill(password);
    await page.getByRole('button', { name: 'Войти', exact: true }).click();
    await expect(page).toHaveURL(/\/cabinet\/catalog$/);
  } finally {
    await other.close();
  }
});

test('конфликт двух редакторов не перезаписывает чужие изменения', async ({ page, browser, baseURL }) => {
  await login(page);
  await fillProduct(page, 'A8 Конфликт');
  await saveProduct(page);
  const other = await browser.newContext({ baseURL });
  try {
    const second = await other.newPage();
    await login(second, 'another-organizer');
    for (const actor of [page, second]) await productRow(actor, 'A8 Конфликт').getByRole('button').click();
    await page.getByLabel('Цена, ₽', { exact: true }).fill('200');
    await saveProduct(page, 'PATCH', 200);
    await second.getByLabel('Цена, ₽', { exact: true }).fill('300');
    await saveProduct(second, 'PATCH', 409);
    await expect(second.getByText(/Каталог изменён в другой вкладке/)).toBeVisible();
    await expect(second.getByLabel('Цена, ₽', { exact: true })).toHaveValue('300');
    await second.getByRole('button', { name: 'Загрузить актуальные данные' }).click();
    await expect(second.getByLabel('Цена, ₽', { exact: true })).toHaveValue('200');
    await second.getByLabel('Цена, ₽', { exact: true }).fill('300');
    await saveProduct(second, 'PATCH', 200);
    await page.getByRole('button', { name: 'Обновить каталог' }).click();
    await expect(productRow(page, 'A8 Конфликт')).toContainText('300 ₽');
  } finally {
    await other.close();
  }
});

test('потеря ответа после commit и повтор не создают дубликат', async ({ page }) => {
  await login(page);
  let lost = false;
  const keys: string[] = [];
  await page.route('**/api/v1/catalog/products', async (route) => {
    if (route.request().method() !== 'POST') return route.continue();
    const key = route.request().headers()['idempotency-key'];
    expect(key).toMatch(/^[a-f0-9]{32}$/);
    keys.push(key!);
    if (!lost) {
      lost = true;
      const response = await route.fetch(); // The real API commits; only delivery to the browser is interrupted.
      expect(response.status()).toBe(201);
      return route.abort('failed');
    }
    return route.continue();
  });
  await fillProduct(page, 'A8 Потерянный ответ');
  await page.getByTestId('admin-dialog').getByRole('button', { name: 'Сохранить', exact: true }).click();
  await expect(page.getByText(/Ответ на сохранение не получен/)).toBeVisible();
  await expect(page.getByLabel('Название продукции', { exact: true })).toBeDisabled();
  await page.reload();
  await page.getByRole('button', { name: 'Новая продукция', exact: true }).click();
  await expect(page.getByText(/Ответ на сохранение не получен/)).toBeVisible();
  await saveProduct(page);
  expect(keys).toHaveLength(2);
  expect(keys[0]).toBe(keys[1]);
  await expect(page.getByRole('heading', { name: 'A8 Потерянный ответ', exact: true })).toHaveCount(1);
});

test('пагинация загружает следующую страницу с сервера', async ({ page }) => {
  await login(page);
  for (let i = 0; i < 26; i++) await createViaApi(page, 'Я Пагинация ' + String(i).padStart(2, '0'));
  await page.getByRole('button', { name: 'Обновить каталог', exact: true }).click();
  await expect(page.getByRole('button', { name: 'Следующая' })).toBeEnabled();
  const next = page.waitForResponse((r) => r.url().includes('/catalog/products?page=2&pageSize=25'));
  await page.getByRole('button', { name: 'Следующая' }).click();
  const response = await next;
  expect(response.status()).toBe(200);
  const body = await response.json();
  expect(body.meta.page).toBe(2);
  await expect(page.getByRole('heading', { name: 'Я Пагинация 25', exact: true })).toBeVisible();
  await expect(page.getByRole('article')).toHaveCount(body.data.items.length);
});

test('мобильный редактор доступен, текст товара безопасно отображается', async ({ page }, testInfo) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await login(page);
  await fillProduct(page, 'A8 Мобильный <img onerror=alert(1)>');
  await page.getByLabel('Описание', { exact: true }).fill('<script>window.e2eUnexpected = true</script>');
  await saveProduct(page);
  await expect(productRow(page, 'A8 Мобильный <img onerror=alert(1)>')).toContainText('<script>');
  expect(await page.evaluate(() => 'e2eUnexpected' in window)).toBe(false);
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
  await page.screenshot({
    path: testInfo.outputPath('mobile-catalog.png'),
    fullPage: true
  });
  await page.getByRole('button', { name: 'Открыть меню' }).click();
  await expect(page.getByRole('link', { name: /^Профиль: / })).toBeVisible();
});
