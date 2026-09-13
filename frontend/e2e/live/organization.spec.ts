import { test, expect, type Page, type APIResponse, type Response } from '@playwright/test';
import { login, password, token } from './helpers.js';

const institutionsApi = '/api/v1/institutions';
const institutionsPage = '/cabinet/institutions';
const errors = new WeakMap<Page, string[]>();
const key = () => crypto.randomUUID().replace(/-/g, '');
const row = (page: Page, name: string) => page.getByTestId('structure-row').filter({ hasText: name });
const edit = (page: Page, name: string) =>
  row(page, name).getByRole('button', {
    name: `Редактировать «${name}»`,
    exact: true
  });
const shootPage = (institutionId: string, shootId: string) => `${institutionsPage}/${institutionId}/shoots/${shootId}`;

type Mutation = { id: string; revision: number; assignmentSignature: string };
async function body(response: APIResponse | Response, status = 200) {
  expect(response.status(), await response.text()).toBe(status);
  return response.json();
}
async function headers(page: Page) {
  return {
    Authorization: 'Bearer ' + (await token(page)),
    'Idempotency-Key': key()
  };
}
async function create(page: Page, path: string, data: Record<string, unknown>): Promise<Mutation> {
  return (await body(await page.request.post(path, { headers: await headers(page), data }), 201)).data;
}
async function institution(page: Page, name: string): Promise<Mutation> {
  return create(page, institutionsApi, {
    name,
    address: 'Москва, тестовая улица, 1'
  });
}
async function shoot(page: Page, parent: string, name: string): Promise<Mutation> {
  return create(page, `${institutionsApi}/${parent}/shoots`, {
    name,
    date: null
  });
}
async function group(page: Page, parent: string, name: string): Promise<Mutation> {
  return create(page, `/api/v1/shoots/${parent}/groups`, {
    name,
    groupKind: 'regular'
  });
}
async function save(page: Page, method: 'POST' | 'PATCH', path: string, status = method === 'POST' ? 201 : 200) {
  const pending = page.waitForResponse((response) => new URL(response.url()).pathname === path && response.request().method() === method);
  await page.getByTestId('admin-dialog').getByRole('button', { name: 'Сохранить', exact: true }).click();
  const result = await body(await pending, status);
  if (status < 300) await expect(page.getByTestId('admin-dialog')).not.toBeVisible();
  return result.data as Mutation;
}
async function openGroup(page: Page, name: string) {
  await page.getByRole('button', { name: 'Новая группа', exact: true }).click();
  await page.getByLabel('Название группы', { exact: true }).fill(name);
}
async function loginRole(page: Page, account: 'curator' | 'head') {
  const logout = page.getByRole('button', { name: 'Выйти', exact: true });
  if (await logout.count()) {
    await logout.click();
    await expect(page).toHaveURL(/\/login$/);
  }
  await page.goto('/login');
  await page.getByRole('textbox', { name: 'Email', exact: true }).fill(account + '@example.invalid');
  await page.getByLabel('Пароль', { exact: true }).fill(password);
  const profile = page.waitForResponse((response) => new URL(response.url()).pathname === '/api/v1/me');
  await page.getByRole('button', { name: 'Войти', exact: true }).click();
  const data = (await body(await profile)).data;
  await expect(page.getByRole('heading', { name: 'Профиль', exact: true })).toBeVisible();
  return data;
}

test.beforeEach(async ({ page, request }) => {
  expect(await (await request.get('/__e2e')).json()).toEqual({
    fixture: 'rabit-real-e2e'
  });
  const messages: string[] = [];
  errors.set(page, messages);
  page.on('pageerror', (error) => messages.push(error.message));
  page.on('console', (message) => {
    if (message.type() === 'error' && !message.text().startsWith('Failed to load resource:')) messages.push(message.text());
  });
  page.on('response', (response) => {
    const url = new URL(response.url());
    if (response.status() >= 500 && url.pathname.startsWith('/api/')) messages.push(`${response.status()} ${url.pathname}`);
    if (response.status() >= 400 && url.pathname.startsWith('/assets/')) messages.push(`Asset ${response.status()} ${url.pathname}`);
  });
});
test.afterEach(async ({ page }) => {
  expect(errors.get(page)).toEqual([]);
  expect(await page.evaluate(() => '__MOREFOTO_MOCKS__' in window)).toBe(false);
  expect(await page.evaluate(() => Object.keys(localStorage).some((name) => name.startsWith('morefoto:demo:')))).toBe(false);
});

test('C3: учреждение, две съёмки и группы сохраняются через браузер и новую сессию', async ({ page, browser, baseURL }, testInfo) => {
  await page.setViewportSize({ width: 1440, height: 1000 });
  await login(page);
  await page.getByRole('link', { name: 'Учреждения', exact: true }).click();
  await page.getByRole('button', { name: 'Новое учреждение', exact: true }).click();
  await page.getByLabel('Название учреждения', { exact: true }).fill('C3 Детский сад');
  await page.getByLabel('Адрес', { exact: true }).fill('Москва, улица Осенняя, 1');
  const parent = await save(page, 'POST', institutionsApi);
  await edit(page, 'C3 Детский сад').click();
  await page.getByLabel('Адрес', { exact: true }).fill('Москва, улица Осенняя, 2');
  await save(page, 'PATCH', `${institutionsApi}/${parent.id}`);
  await expect(row(page, 'C3 Детский сад')).toContainText('улица Осенняя, 2');
  await page.screenshot({ path: testInfo.outputPath('desktop-institutions.png'), fullPage: true });
  await row(page, 'C3 Детский сад').getByRole('link').click();
  await page.getByRole('button', { name: 'Новая съёмка', exact: true }).click();
  await page.getByLabel('Название съёмки', { exact: true }).fill('C3 Осень');
  await page.getByLabel('Дата съёмки', { exact: true }).fill('2026-10-01');
  await page.screenshot({ path: testInfo.outputPath('desktop-shoot-editor.png'), fullPage: true });
  const first = await save(page, 'POST', `${institutionsApi}/${parent.id}/shoots`);
  await page.getByRole('button', { name: 'Новая съёмка', exact: true }).click();
  await page.getByLabel('Название съёмки', { exact: true }).fill('C3 Весна');
  const second = await save(page, 'POST', `${institutionsApi}/${parent.id}/shoots`);
  await page.screenshot({
    path: testInfo.outputPath('desktop-shoots.png'),
    fullPage: true
  });
  await row(page, 'C3 Осень').getByRole('link').click();
  await openGroup(page, 'C3 Ромашки');
  const regular = await save(page, 'POST', `/api/v1/shoots/${first.id}/groups`);
  await openGroup(page, 'C3 Сотрудники');
  await page.getByRole('combobox', { name: 'Тип группы', exact: true }).press('Enter');
  await page.getByRole('option', { name: 'Сотрудники', exact: true }).click();
  await save(page, 'POST', `/api/v1/shoots/${first.id}/groups`);
  await edit(page, 'C3 Ромашки').click();
  await page.getByLabel('Название группы', { exact: true }).fill('C3 Ромашки младшие');
  await save(page, 'PATCH', `/api/v1/groups/${regular.id}`);
  await expect(row(page, 'C3 Ромашки младшие')).toBeVisible();
  await page.screenshot({
    path: testInfo.outputPath('desktop-groups.png'),
    fullPage: true
  });
  const result = await body(
    await page.request.get(`/api/v1/shoots/${first.id}`, {
      headers: await headers(page)
    })
  );
  expect(result.data.groups.items.map((item: { name: string }) => item.name).sort()).toEqual(['C3 Ромашки младшие', 'C3 Сотрудники']);
  for (const item of result.data.groups.items) {
    expect(item.status).toBe('preparing');
    expect(item.timezone).toBe('Europe/Moscow');
    expect(item.sentAt).toBeNull();
    expect(item.closesAt).toBeNull();
    expect(item.deliveryDueAt).toBeNull();
    expect(item).not.toHaveProperty('galleryToken');
  }
  const fresh = await browser.newContext({ baseURL });
  try {
    const another = await fresh.newPage();
    await login(another);
    await another.goto(shootPage(parent.id, first.id));
    await expect(row(another, 'C3 Ромашки младшие')).toBeVisible();
    await expect(row(another, 'C3 Сотрудники')).toBeVisible();
    await another.goto(shootPage(parent.id, second.id));
    await expect(another.getByRole('button', { name: 'Новая группа', exact: true })).toBeEnabled();
    await expect(another.getByTestId('structure-row')).toHaveCount(0);
  } finally {
    await fresh.close();
  }
});

test('C3: очистка даты сохраняет null, переименование без даты сохраняет значение', async ({ page }) => {
  await login(page);
  const parent = await institution(page, 'C3 Даты');
  const event = await create(page, `${institutionsApi}/${parent.id}/shoots`, {
    name: 'C3 Дата',
    date: '2028-02-29'
  });
  await page.goto(`${institutionsPage}/${parent.id}`);
  await edit(page, 'C3 Дата').click();
  await page.getByLabel('Дата съёмки', { exact: true }).fill('');
  await save(page, 'PATCH', `/api/v1/shoots/${event.id}`);
  expect(
    (
      await body(
        await page.request.get(`/api/v1/shoots/${event.id}`, {
          headers: await headers(page)
        })
      )
    ).data.date
  ).toBeNull();
  const dated = await body(
    await page.request.patch(`/api/v1/shoots/${event.id}`, {
      headers: await headers(page),
      data: { revision: 2, date: '2028-02-29' }
    })
  );
  await body(
    await page.request.patch(`/api/v1/shoots/${event.id}`, {
      headers: await headers(page),
      data: {
        revision: dated.data.revision,
        name: 'C3 Дата после переименования'
      }
    })
  );
  const persisted = await body(await page.request.get(`/api/v1/shoots/${event.id}`, { headers: await headers(page) }));
  expect(persisted.data.date).toBe('2028-02-29');
  await page.reload();
  await edit(page, 'C3 Дата после переименования').click();
  await expect(page.getByLabel('Дата съёмки', { exact: true })).toHaveValue('2028-02-29');
});

test('C3: потерянный ответ группы повторяется после reload с тем же ключом и телом', async ({ page }) => {
  await login(page);
  const parent = await institution(page, 'C3 Повтор');
  const event = await shoot(page, parent.id, 'C3 Повтор');
  await page.goto(shootPage(parent.id, event.id));
  const sent: { key: string | undefined; data: string | null }[] = [];
  let lost = false;
  await page.route(`**/api/v1/shoots/${event.id}/groups`, async (route) => {
    if (route.request().method() !== 'POST') return route.continue();
    sent.push({
      key: route.request().headers()['idempotency-key'],
      data: route.request().postData()
    });
    if (!lost) {
      lost = true;
      expect((await route.fetch()).status()).toBe(201);
      return route.abort('failed');
    }
    return route.continue();
  });
  await openGroup(page, 'C3 Не дублировать');
  await page.getByTestId('admin-dialog').getByRole('button', { name: 'Сохранить', exact: true }).click();
  await expect(page.getByText(/Ответ на сохранение не получен/)).toBeVisible();
  await expect(page.getByLabel('Название группы', { exact: true })).toBeDisabled();
  await page.reload();
  await page.getByRole('button', { name: 'Новая группа', exact: true }).click();
  await expect(page.getByText(/Ответ на сохранение не получен/)).toBeVisible();
  await save(page, 'POST', `/api/v1/shoots/${event.id}/groups`);
  expect(sent).toHaveLength(2);
  expect(sent[0]).toEqual(sent[1]);
  expect(sent[0]!.key).toMatch(/^[a-f0-9]{32}$/);
  await expect(row(page, 'C3 Не дублировать')).toHaveCount(1);
  const detail = await body(
    await page.request.get(`/api/v1/shoots/${event.id}`, {
      headers: await headers(page)
    })
  );
  expect(detail.data.groups.meta.total).toBe(1);
});

test('C3: конфликт двух редакторов группы сохраняет ввод и не перезаписывает победителя', async ({ page, browser, baseURL }) => {
  await login(page);
  const parent = await institution(page, 'C3 Конфликт');
  const event = await shoot(page, parent.id, 'C3 Конфликт');
  const item = await group(page, event.id, 'C3 Исходная группа');
  await page.goto(shootPage(parent.id, event.id));
  const other = await browser.newContext({ baseURL });
  try {
    const second = await other.newPage();
    await login(second, 'another-organizer');
    await second.goto(shootPage(parent.id, event.id));
    for (const actor of [page, second]) await edit(actor, 'C3 Исходная группа').click();
    await page.getByLabel('Название группы', { exact: true }).fill('C3 Победитель');
    await save(page, 'PATCH', `/api/v1/groups/${item.id}`);
    await second.getByLabel('Название группы', { exact: true }).fill('C3 Мой ввод');
    await save(second, 'PATCH', `/api/v1/groups/${item.id}`, 409);
    await expect(second.getByLabel('Название группы', { exact: true })).toHaveValue('C3 Мой ввод');
    await second.getByRole('button', { name: 'Загрузить актуальные данные', exact: true }).click();
    await expect(second.getByLabel('Название группы', { exact: true })).toHaveValue('C3 Победитель');
    await second.getByLabel('Название группы', { exact: true }).fill('C3 После обновления');
    await save(second, 'PATCH', `/api/v1/groups/${item.id}`);
    await page.reload();
    await expect(row(page, 'C3 После обновления')).toBeVisible();
  } finally {
    await other.close();
  }
});

test('C3: пагинация съёмок и групп читает независимые страницы сервера', async ({ page }) => {
  await login(page);
  const parent = await institution(page, 'C3 Пагинация');
  const events: Mutation[] = [];
  for (let i = 0; i < 26; i++) events.push(await shoot(page, parent.id, `C3 Съёмка ${String(i).padStart(2, '0')}`));
  await page.goto(`${institutionsPage}/${parent.id}`);
  await expect(page.getByTestId('structure-row')).toHaveCount(25);
  let pending = page.waitForResponse((response) => response.url().includes(`/institutions/${parent.id}/shoots?page=2`));
  await page.getByRole('button', { name: 'Следующая', exact: true }).click();
  const shots = await body(await pending);
  expect(shots.meta.page).toBe(2);
  expect(shots.meta.total).toBe(26);
  await expect(page.getByTestId('structure-row')).toHaveCount(1);
  await expect(row(page, shots.data.items[0].name)).toBeVisible();
  const event = events[0]!;
  for (let i = 0; i < 26; i++) await group(page, event.id, `C3 Группа ${String(i).padStart(2, '0')}`);
  await page.goto(shootPage(parent.id, event.id));
  await expect(page.getByTestId('structure-row')).toHaveCount(25);
  pending = page.waitForResponse((response) => response.url().includes(`/shoots/${event.id}?page=2`));
  await page.getByRole('button', { name: 'Следующая', exact: true }).click();
  const groups = await body(await pending);
  expect(groups.data.groups.meta.page).toBe(2);
  expect(groups.data.groups.meta.total).toBe(26);
  await expect(page.getByTestId('structure-row')).toHaveCount(1);
  await expect(row(page, groups.data.groups.items[0].name)).toBeVisible();
});

test('C3: сервер отклоняет query-подмену, календарь в PATCH и отсутствующие сущности', async ({ page }) => {
  await login(page);
  const parent = await institution(page, 'C3 Границы');
  const event = await shoot(page, parent.id, 'C3 Границы');
  const item = await group(page, event.id, 'C3 Границы');
  for (const path of [
    `${institutionsApi}/${parent.id}/shoots?institution_id=${crypto.randomUUID()}`,
    `/api/v1/shoots/${event.id}?shoot_id=${crypto.randomUUID()}`
  ]) {
    expect((await page.request.get(path, { headers: await headers(page) })).status()).toBe(422);
  }
  expect(
    (
      await page.request.patch(`/api/v1/groups/${item.id}?group_id=${crypto.randomUUID()}`, {
        headers: await headers(page),
        data: { revision: 1, name: 'Подмена' }
      })
    ).status()
  ).toBe(422);
  for (const field of ['closesAt', 'sentAt', 'deliveryDueAt', 'status', 'timezone', 'shootId', 'groupKind']) {
    expect(
      (
        await page.request.patch(`/api/v1/groups/${item.id}`, {
          headers: await headers(page),
          data: { revision: 1, name: 'Обход', [field]: 'forbidden' }
        })
      ).status()
    ).toBe(422);
  }
  expect(
    (
      await page.request.get(`/api/v1/shoots/${crypto.randomUUID()}`, {
        headers: await headers(page)
      })
    ).status()
  ).toBe(404);
  expect(
    (
      await page.request.get(`/api/v1/shoots/${event.id}`, {
        headers: { Authorization: 'Bearer invalid' }
      })
    ).status()
  ).toBe(401);
  await page.goto(shootPage(parent.id, event.id));
  await expect(row(page, 'C3 Границы')).toBeVisible();
});

test('C3: воспитатель не получает редактор даже после подмены локальной роли', async ({ page }) => {
  await login(page, 'teacher');
  await expect(page.getByRole('link', { name: 'Учреждения', exact: true })).toHaveCount(0);
  expect((await page.request.get(institutionsApi, { headers: await headers(page) })).status()).toBe(403);
  expect(
    (
      await page.request.get(`/api/v1/shoots/${crypto.randomUUID()}`, {
        headers: await headers(page)
      })
    ).status()
  ).toBe(403);
  await page.evaluate(() => {
    const profile = JSON.parse(localStorage.getItem('morefoto:live:auth:user')!);
    profile.role = 'organizer';
    profile.permissions = ['organization.manage'];
    localStorage.setItem('morefoto:live:auth:user', JSON.stringify(profile));
  });
  await page.goto(institutionsPage);
  await expect(page.getByRole('heading', { name: 'Недостаточно прав' })).toBeVisible();
  expect(await page.evaluate(() => JSON.parse(localStorage.getItem('morefoto:live:auth:user')!).role)).toBe('teacher');
});

test('C3: мобильная форма и календарь группы доступны без горизонтального скролла', async ({ page }, testInfo) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await login(page);
  const parent = await institution(page, 'C3 Мобильный');
  const event = await shoot(page, parent.id, 'C3 Мобильный');
  await page.goto(shootPage(parent.id, event.id));
  await openGroup(page, 'C3 <img onerror=alert(1)>');
  await page.screenshot({
    path: testInfo.outputPath('mobile-group-editor.png'),
    fullPage: true
  });
  await save(page, 'POST', `/api/v1/shoots/${event.id}/groups`);
  await expect(row(page, 'C3 <img onerror=alert(1)>')).toBeVisible();
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
  expect(await page.locator('img[onerror]').count()).toBe(0);
  await page.screenshot({
    path: testInfo.outputPath('mobile-groups.png'),
    fullPage: true
  });
  await page.getByRole('button', { name: 'Открыть меню', exact: true }).click();
  await expect(page.getByLabel('Основная навигация').getByRole('link', { name: 'Учреждения', exact: true })).toBeVisible();
});

test('C3: куратор и руководитель видят только назначенное учреждение и его съёмки', async ({ page, browser, baseURL }) => {
  await login(page);
  const roles = await browser.newContext({ baseURL });
  try {
    const viewer = await roles.newPage();
    const curator = await loginRole(viewer, 'curator');
    const head = await loginRole(viewer, 'head');
    const signature = (
      await body(
        await page.request.get(institutionsApi, {
          headers: await headers(page)
        })
      )
    ).data.assignmentSignature;
    const parent = await create(page, institutionsApi, {
      name: 'C3 Назначенная область',
      curatorId: curator.id,
      headId: head.id,
      assignmentSignature: signature
    });
    const outside = await institution(page, 'C3 Чужая область');
    const event = await shoot(page, parent.id, 'C3 Доступная съёмка');
    await shoot(page, outside.id, 'C3 Чужая съёмка');
    for (const account of ['curator', 'head'] as const) {
      await loginRole(viewer, account);
      await viewer.goto(institutionsPage);
      await expect(row(viewer, 'C3 Назначенная область')).toBeVisible();
      await expect(row(viewer, 'C3 Чужая область')).toHaveCount(0);
      await expect(viewer.getByRole('button', { name: 'Новое учреждение', exact: true })).toHaveCount(0);
      await row(viewer, 'C3 Назначенная область').getByRole('link').click();
      await expect(row(viewer, 'C3 Доступная съёмка')).toBeVisible();
      await expect(viewer.getByRole('button', { name: 'Новая съёмка', exact: true })).toHaveCount(0);
      await expect(edit(viewer, 'C3 Доступная съёмка')).toHaveCount(0);
      const own = await body(
        await viewer.request.get(`${institutionsApi}/${parent.id}/shoots`, {
          headers: await headers(viewer)
        })
      );
      expect(own.data.items).toHaveLength(1);
      expect(
        (
          await viewer.request.get(`${institutionsApi}/${outside.id}/shoots`, {
            headers: await headers(viewer)
          })
        ).status()
      ).toBe(404);
      expect(
        (
          await viewer.request.get(`/api/v1/shoots/${event.id}`, {
            headers: await headers(viewer)
          })
        ).status()
      ).toBe(403);
      expect(
        (
          await viewer.request.post(`${institutionsApi}/${parent.id}/shoots`, {
            headers: await headers(viewer),
            data: { name: 'Запрещено' }
          })
        ).status()
      ).toBe(403);
    }
  } finally {
    await roles.close();
  }
});

test('C3: отзыв сессии во время редактора не сохраняет группу и возвращает ко входу', async ({ page, browser, baseURL }) => {
  await login(page);
  const parent = await institution(page, 'C3 Сессия');
  const event = await shoot(page, parent.id, 'C3 Сессия');
  await page.goto(shootPage(parent.id, event.id));
  await openGroup(page, 'C3 Не записано');
  const fresh = await browser.newContext({ baseURL });
  try {
    const second = await fresh.newPage();
    await login(second);
    await save(page, 'POST', `/api/v1/shoots/${event.id}/groups`, 401);
    await expect(page).toHaveURL(/\/login\?reason=session-expired$/);
    const detail = await body(
      await second.request.get(`/api/v1/shoots/${event.id}`, {
        headers: await headers(second)
      })
    );
    expect(detail.data.groups.meta.total).toBe(0);
    await page.getByRole('textbox', { name: 'Email', exact: true }).fill('organizer@example.invalid');
    await page.getByLabel('Пароль', { exact: true }).fill(password);
    await page.getByRole('button', { name: 'Войти', exact: true }).click();
    await expect(page).toHaveURL(shootPage(parent.id, event.id));
    await expect(page.getByRole('button', { name: 'Новая группа', exact: true })).toBeEnabled();
  } finally {
    await fresh.close();
  }
});

test('C3: повтор потерянного сохранения после 401 и нового входа не создаёт дубликат', async ({ page, browser, baseURL }) => {
  await login(page);
  const parent = await institution(page, 'C3 Повтор после входа');
  const event = await shoot(page, parent.id, 'C3 Повтор после входа');
  await page.goto(shootPage(parent.id, event.id));
  const attempts: { key: string | undefined; data: string | null }[] = [];
  await page.route(`**/api/v1/shoots/${event.id}/groups`, async (route) => {
    if (route.request().method() !== 'POST') return route.continue();
    attempts.push({ key: route.request().headers()['idempotency-key'], data: route.request().postData() });
    if (attempts.length === 1) {
      expect((await route.fetch()).status()).toBe(201);
      return route.abort('failed');
    }
    return route.continue();
  });
  await openGroup(page, 'C3 Сохранено до отзыва');
  await page.getByTestId('admin-dialog').getByRole('button', { name: 'Сохранить', exact: true }).click();
  await expect(page.getByText(/Ответ на сохранение не получен/)).toBeVisible();
  const fresh = await browser.newContext({ baseURL });
  try {
    await login(await fresh.newPage());
    await save(page, 'POST', `/api/v1/shoots/${event.id}/groups`, 401);
    await expect(page).toHaveURL(/\/login\?reason=session-expired$/);
    const pending = await page.evaluate(() => {
      const saved = Object.keys(localStorage).find(
        (name) =>
          name.startsWith('morefoto:live:structure-draft:') &&
          JSON.parse(localStorage.getItem(name)!).fields.name === 'C3 Сохранено до отзыва'
      );
      return saved ? JSON.parse(localStorage.getItem(saved)!).pending : null;
    });
    expect(pending.key).toBe(attempts[0]!.key);
    await page.getByRole('textbox', { name: 'Email', exact: true }).fill('organizer@example.invalid');
    await page.getByLabel('Пароль', { exact: true }).fill(password);
    await page.getByRole('button', { name: 'Войти', exact: true }).click();
    await expect(page).toHaveURL(shootPage(parent.id, event.id));
    await page.getByRole('button', { name: 'Новая группа', exact: true }).click();
    await expect(page.getByLabel('Название группы', { exact: true })).toHaveValue('C3 Сохранено до отзыва');
    await expect(page.getByLabel('Название группы', { exact: true })).toBeDisabled();
    await save(page, 'POST', `/api/v1/shoots/${event.id}/groups`);
    expect(attempts).toHaveLength(3);
    expect(attempts[1]).toEqual(attempts[0]);
    expect(attempts[2]).toEqual(attempts[0]);
    await expect(row(page, 'C3 Сохранено до отзыва')).toHaveCount(1);
    const detail = await body(await page.request.get(`/api/v1/shoots/${event.id}`, { headers: await headers(page) }));
    expect(detail.data.groups.meta.total).toBe(1);
  } finally {
    await fresh.close();
  }
});
