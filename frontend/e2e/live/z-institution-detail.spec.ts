import { test, expect, type Page, type APIResponse } from '@playwright/test';
import { login, password, token } from './helpers.js';

const api = '/api/v1/institutions';
const cabinet = '/cabinet/institutions';
const problems = new WeakMap<Page, string[]>();
const shoots = (page: Page) => page.getByTestId('institution-shoots');
const groups = (page: Page) => page.getByTestId('institution-groups');
const row = (page: Page, name: string) => page.getByTestId('structure-row').filter({ hasText: name });
async function headers(page: Page) {
  return {
    Authorization: 'Bearer ' + (await token(page)),
    'Idempotency-Key': crypto.randomUUID().replace(/-/g, '')
  };
}
async function response(response: APIResponse, status = 200) {
  expect(response.status(), await response.text()).toBe(status);
  return response.json();
}
async function create(page: Page, path: string, data: Record<string, unknown>): Promise<{ id: string; revision: number }> {
  return (await response(await page.request.post(path, { headers: await headers(page), data }), 201)).data;
}
async function institution(page: Page, name: string) {
  return create(page, api, { name, address: 'Москва, Садовая улица, 12' });
}
async function shoot(page: Page, institutionId: string, name: string) {
  return create(page, `${api}/${institutionId}/shoots`, {
    name,
    date: '2026-10-15'
  });
}
async function group(page: Page, shootId: string, name: string) {
  return create(page, `/api/v1/shoots/${shootId}/groups`, {
    name,
    groupKind: 'regular'
  });
}
async function detail(page: Page, id: string, query = '') {
  return (
    await response(
      await page.request.get(`${api}/${id}${query}`, {
        headers: await headers(page)
      })
    )
  ).data;
}
async function role(page: Page, name: 'c4-curator' | 'c4-head') {
  const userMenu = page.getByRole('button', { name: 'Меню пользователя', exact: true });
  if (await userMenu.count()) {
    await userMenu.click();
    await page.getByRole('button', { name: 'Выйти', exact: true }).click();
    await expect(page).toHaveURL(/\/login$/);
  }
  await page.goto('/login');
  await page.getByRole('textbox', { name: 'Email', exact: true }).fill(name + '@example.invalid');
  await page.getByLabel('Пароль', { exact: true }).fill(password);
  const profile = page.waitForResponse((r) => new URL(r.url()).pathname === '/api/v1/me');
  await page.getByRole('button', { name: 'Войти', exact: true }).click();
  const data = (await (await profile).json()).data;
  // U6: every staff role lands on the overview; the scenario continues from the profile as before.
  await expect(page).toHaveURL(/\/cabinet\/overview$/);
  await page.goto('/cabinet/profile');
  await expect(page.getByRole('heading', { name: 'Профиль', exact: true })).toBeVisible();
  return data;
}
async function screenshot(page: Page, path: string, fullPage = true) {
  await page.evaluate(() => document.fonts.ready);
  await page.waitForTimeout(500); // Capture the settled UI; functional actions keep normal animations.
  await page.screenshot({ path, fullPage });
}

function watchPage(page: Page, errors: string[]): void {
  page.on('pageerror', (error) => errors.push(error.message));
  page.on('console', (message) => {
    if (message.type() === 'error' && !message.text().startsWith('Failed to load resource:')) errors.push(message.text());
  });
  page.on('response', (r) => {
    const path = new URL(r.url()).pathname;
    if ((r.status() >= 500 && path.startsWith('/api/')) || (r.status() >= 400 && path.startsWith('/assets/')))
      errors.push(`${r.status()} ${path}`);
  });
}
test.beforeEach(async ({ page, request }) => {
  expect(await (await request.get('/__e2e')).json()).toEqual({
    fixture: 'rabit-real-e2e'
  });
  const errors: string[] = [];
  problems.set(page, errors);
  watchPage(page, errors);
});
test.afterEach(async ({ page }) => {
  expect(problems.get(page)).toEqual([]);
  expect(await page.evaluate(() => '__MOREFOTO_MOCKS__' in window)).toBe(false);
  expect(await page.evaluate(() => Object.keys(localStorage).some((key) => key.startsWith('morefoto:demo:')))).toBe(false);
});

test('C4: полная карточка показывает сохранённые съёмки и группы из разных съёмок', async ({ page }, testInfo) => {
  await page.setViewportSize({ width: 1440, height: 1000 });
  await login(page);
  const parent = await institution(page, 'C4 Детский сад на Садовой');
  const first = await shoot(page, parent.id, 'C4 Осенние портреты');
  const second = await shoot(page, parent.id, 'C4 Зимние портреты');
  await group(page, first.id, 'C4 Ромашки');
  await group(page, second.id, 'C4 Васильки');
  const pending = page.waitForResponse((r) => new URL(r.url()).pathname === `${api}/${parent.id}`);
  await page.goto(`${cabinet}/${parent.id}`);
  const data = (await (await pending).json()).data;
  expect(data.summary).toEqual({
    availability: 'unavailable',
    reason: 'dependenciesNotReady'
  });
  expect(data.curatorId).toBeNull();
  expect(data.headId).toBeNull();
  expect(data.assignmentSignature).toBeTruthy();
  expect(data.groups.items.map((item: { shootId: string }) => item.shootId).sort()).toEqual([first.id, second.id].sort());
  await expect(
    page.getByRole('heading', {
      name: 'C4 Детский сад на Садовой',
      exact: true
    })
  ).toBeVisible();
  await expect(page.getByText('Москва, Садовая улица, 12', { exact: true })).toBeVisible();
  await page.getByRole('button', { name: 'Редактировать учреждение', exact: true }).click();
  await page.getByLabel('Адрес', { exact: true }).fill('Москва, Садовая улица, 14');
  const saved = page.waitForResponse((r) => new URL(r.url()).pathname === `${api}/${parent.id}` && r.request().method() === 'PATCH');
  await page.getByTestId('admin-dialog').getByRole('button', { name: 'Сохранить', exact: true }).click();
  expect((await saved).status()).toBe(200);
  await expect(page.getByTestId('admin-dialog')).not.toBeVisible();
  await expect(page.getByTestId('institution-overview')).toContainText('Москва, Садовая улица, 14');
  expect((await detail(page, parent.id)).revision).toBe(2);
  await expect(shoots(page).getByTestId('structure-row')).toHaveCount(2);
  await expect(groups(page).getByTestId('structure-row')).toHaveCount(2);
  await expect(page.getByText('Финансовая сводка пока недоступна.', { exact: true })).toBeVisible();
  await screenshot(page, testInfo.outputPath('c4-desktop-institution.png'));
  await groups(page).getByTestId('structure-row').filter({ hasText: 'C4 Васильки' }).getByRole('link').click();
  await expect(page).toHaveURL(`${cabinet}/${parent.id}/shoots/${second.id}`);
  await expect(row(page, 'C4 Васильки')).toBeVisible();
  await expect(row(page, 'C4 Ромашки')).toHaveCount(0);
  await page.goto(`${cabinet}/${parent.id}`);
  await page.reload();
  await expect(groups(page).getByTestId('structure-row')).toHaveCount(2);
});

test('C4: две страницы переключаются независимо и используют реальные totals', async ({ page }) => {
  await login(page);
  const parent = await institution(page, 'C4 Независимые страницы');
  const events: string[] = [];
  for (let i = 0; i < 26; i++) events.push((await shoot(page, parent.id, `C4 Съёмка ${String(i).padStart(2, '0')}`)).id);
  for (let i = 0; i < 26; i++) await group(page, events[i % 2]!, `C4 Группа ${String(i).padStart(2, '0')}`);
  await page.goto(`${cabinet}/${parent.id}`);
  await expect(shoots(page).getByTestId('structure-row')).toHaveCount(25);
  await expect(groups(page).getByTestId('structure-row')).toHaveCount(25);
  const shootIds = await shoots(page)
    .getByTestId('structure-row')
    .evaluateAll((rows) => rows.map((r) => r.getAttribute('data-entity-id')));
  let pending = page.waitForResponse(
    (r) => new URL(r.url()).pathname === `${api}/${parent.id}` && new URL(r.url()).searchParams.get('groupsPage') === '2'
  );
  await groups(page).getByRole('button', { name: 'Следующая', exact: true }).click();
  const first = (await (await pending).json()).data;
  expect(first.shoots.meta).toMatchObject({ page: 1, pageSize: 25, total: 26 });
  expect(first.groups.meta).toMatchObject({ page: 2, pageSize: 25, total: 26 });
  await expect(groups(page).getByTestId('structure-row')).toHaveCount(1);
  expect(
    await shoots(page)
      .getByTestId('structure-row')
      .evaluateAll((rows) => rows.map((r) => r.getAttribute('data-entity-id')))
  ).toEqual(shootIds);
  const groupId = first.groups.items[0].id;
  pending = page.waitForResponse(
    (r) => new URL(r.url()).pathname === `${api}/${parent.id}` && new URL(r.url()).searchParams.get('shootsPage') === '2'
  );
  await shoots(page).getByRole('button', { name: 'Следующая', exact: true }).click();
  const second = (await (await pending).json()).data;
  expect(second.shoots.meta.page).toBe(2);
  expect(second.groups.meta.page).toBe(2);
  expect(second.groups.items[0].id).toBe(groupId);
  await expect(shoots(page).getByTestId('structure-row')).toHaveCount(1);
  await expect(groups(page).getByTestId('structure-row')).toHaveCount(1);
});

test('C4: куратор и руководитель читают только свою карточку без mutation signature', async ({ page, browser, baseURL }, testInfo) => {
  await login(page);
  const context = await browser.newContext({
    baseURL,
    viewport: { width: 1440, height: 1000 }
  });
  context.on('page', (child) => watchPage(child, problems.get(page)!));
  try {
    const viewer = await context.newPage();
    const curator = await role(viewer, 'c4-curator');
    const head = await role(viewer, 'c4-head');
    const list = await response(await page.request.get(api, { headers: await headers(page) }));
    const own = await create(page, api, {
      name: 'C4 Назначенное учреждение',
      curatorId: curator.id,
      headId: head.id,
      assignmentSignature: list.data.assignmentSignature
    });
    const foreign = await institution(page, 'C4 Чужое учреждение');
    const event = await shoot(page, own.id, 'C4 Доступная съёмка');
    await group(page, event.id, 'C4 Доступная группа');
    const outside = await shoot(page, foreign.id, 'C4 Скрытая съёмка');
    await group(page, outside.id, 'C4 Скрытая группа');
    for (const name of ['c4-curator', 'c4-head'] as const) {
      await role(viewer, name);
      const data = await detail(viewer, own.id);
      expect(data).not.toHaveProperty('assignmentSignature');
      expect(data.curatorId).toBe(curator.id);
      expect(data.headId).toBe(head.id);
      // DS-11: the card names the responsible staff instead of their numbers.
      expect([data.curatorName, data.headName]).toEqual([curator.name, head.name]);
      expect(
        (
          await viewer.request.get(`${api}/${foreign.id}`, {
            headers: await headers(viewer)
          })
        ).status()
      ).toBe(404);
      await viewer.goto(`${cabinet}/${own.id}`);
      await expect(viewer.getByTestId('institution-curator')).toContainText(curator.name);
      await expect(viewer.getByTestId('institution-head')).toContainText(head.name);
      await expect(viewer.getByTestId('institution-overview')).not.toContainText('Сотрудник №');
      await expect(shoots(viewer).getByTestId('structure-row')).toHaveCount(1);
      await expect(groups(viewer).getByTestId('structure-row')).toHaveCount(1);
      await expect(viewer.getByRole('button', { name: 'Новая съёмка', exact: true })).toHaveCount(0);
      await expect(groups(viewer).getByRole('link')).toHaveCount(0);
      await expect(viewer.getByRole('button', { name: /Редактировать «/ })).toHaveCount(0);
      if (name === 'c4-curator') await screenshot(viewer, testInfo.outputPath('c4-desktop-curator.png'));
      await viewer.goto(`${cabinet}/${foreign.id}`);
      await expect(viewer.getByRole('alert')).toBeVisible();
      await expect(viewer.getByText('C4 Скрытая группа', { exact: true })).toHaveCount(0);
      await expect(
        viewer.getByRole('heading', {
          name: 'C4 Назначенное учреждение',
          exact: true
        })
      ).toHaveCount(0);
    }
  } finally {
    await context.close();
  }
});

test('C4: teacher и невалидный токен не открывают полный endpoint', async ({ page, browser, baseURL }) => {
  await login(page);
  const parent = await institution(page, 'C4 Только для сотрудников области');
  const context = await browser.newContext({ baseURL });
  context.on('page', (child) => watchPage(child, problems.get(page)!));
  try {
    const teacher = await context.newPage();
    await login(teacher, 'teacher');
    expect(
      (
        await teacher.request.get(`${api}/${parent.id}`, {
          headers: await headers(teacher)
        })
      ).status()
    ).toBe(403);
    await teacher.goto(`${cabinet}/${parent.id}`);
    await expect(teacher.getByRole('heading', { name: 'Недостаточно прав', exact: true })).toBeVisible();
    await expect(
      teacher.getByRole('heading', {
        name: 'C4 Только для сотрудников области',
        exact: true
      })
    ).toHaveCount(0);
    expect(
      (
        await page.request.get(`${api}/${parent.id}`, {
          headers: { Authorization: 'Bearer invalid' }
        })
      ).status()
    ).toBe(401);
  } finally {
    await context.close();
  }
});

test('C4: query границы, path spoof и пустые дальние страницы проверяет сервер', async ({ page }) => {
  await login(page);
  const parent = await institution(page, 'C4 Query');
  const event = await shoot(page, parent.id, 'C4 Query съёмка');
  await group(page, event.id, 'C4 Query группа');
  for (const query of [
    'shootsPage=0',
    'groupsPage=-1',
    'pageSize=101',
    'pageSize=0',
    'shootsPage[]=1',
    'groupsPage=1.5',
    'institution_id=' + crypto.randomUUID(),
    'summary=ready'
  ]) {
    expect(
      (
        await page.request.get(`${api}/${parent.id}?${query}`, {
          headers: await headers(page)
        })
      ).status(),
      query
    ).toBe(422);
  }
  expect(
    (
      await page.request.get(`${api}/${crypto.randomUUID()}`, {
        headers: await headers(page)
      })
    ).status()
  ).toBe(404);
  const far = await detail(page, parent.id, '?shootsPage=2&groupsPage=3&pageSize=1');
  expect(far.shoots.items).toEqual([]);
  expect(far.groups.items).toEqual([]);
  expect(far.shoots.meta).toMatchObject({ page: 2, pageSize: 1, total: 1 });
  expect(far.groups.meta).toMatchObject({ page: 3, pageSize: 1, total: 1 });
  expect(far.summary).toEqual({
    availability: 'unavailable',
    reason: 'dependenciesNotReady'
  });
});

test('C4: ошибка загрузки не подменяется пустой карточкой, повтор читает сервер', async ({ page }) => {
  await login(page);
  const parent = await institution(page, 'C4 Повтор загрузки');
  const event = await shoot(page, parent.id, 'C4 Сохранённая съёмка');
  await group(page, event.id, 'C4 Сохранённая группа');
  await page.goto(`${cabinet}/${parent.id}`);
  await expect(groups(page).getByTestId('structure-row')).toHaveCount(1);
  let lost = false;
  await page.route(`**${api}/${parent.id}?*`, async (route) => {
    if (!lost) {
      lost = true;
      expect((await route.fetch()).status()).toBe(200);
      return route.abort('failed');
    }
    return route.continue();
  });
  await page.getByRole('button', { name: 'Обновить список', exact: true }).click();
  await expect(page.getByRole('alert')).toBeVisible();
  await expect(groups(page)).toHaveCount(0);
  await page.getByRole('button', { name: 'Обновить список', exact: true }).click();
  await expect(groups(page).getByTestId('structure-row')).toHaveCount(1);
  await expect(row(page, 'C4 Сохранённая группа')).toBeVisible();
});

test('C4: мобильная карточка безопасно отображает длинный текст и доступные действия', async ({ page }, testInfo) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await login(page);
  const name = 'C4 Детский сад «Солнышко» на длинной улице';
  const parent = await institution(page, name);
  const first = await shoot(page, parent.id, 'C4 Осенняя съёмка');
  const second = await shoot(page, parent.id, 'C4 Зимняя съёмка');
  await group(page, first.id, 'C4 <img onerror=alert(1)>');
  await group(page, second.id, 'C4 Подготовительная группа');
  await page.goto(`${cabinet}/${parent.id}`);
  await expect(page.getByRole('heading', { name, exact: true })).toBeVisible();
  await expect(row(page, 'C4 <img onerror=alert(1)>')).toBeVisible();
  expect(await page.locator('img[onerror]').count()).toBe(0);
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
  await screenshot(page, testInfo.outputPath('c4-mobile-institution.png'));
  await page.getByRole('button', { name: 'Новая съёмка', exact: true }).click();
  await page.getByLabel('Название съёмки', { exact: true }).fill('C4 Новая мобильная съёмка');
  await screenshot(page, testInfo.outputPath('c4-mobile-shoot-editor.png'), false);
  await page.getByRole('button', { name: 'Закрыть редактор', exact: true }).click();
  await expect(groups(page).getByTestId('structure-row')).toHaveCount(2);
});

test('C4: отзыв сессии при обновлении карточки возвращает к входу и обратно', async ({ page, browser, baseURL }) => {
  await login(page);
  const parent = await institution(page, 'C4 Сессия карточки');
  await page.goto(`${cabinet}/${parent.id}`);
  await expect(page.getByRole('heading', { name: 'C4 Сессия карточки', exact: true })).toBeVisible();
  const context = await browser.newContext({ baseURL });
  context.on('page', (child) => watchPage(child, problems.get(page)!));
  try {
    await login(await context.newPage());
    await page.getByRole('button', { name: 'Обновить список', exact: true }).click();
    await expect(page).toHaveURL(/\/login\?reason=revoked$/);
    await page.getByRole('textbox', { name: 'Email', exact: true }).fill('organizer@example.invalid');
    await page.getByLabel('Пароль', { exact: true }).fill(password);
    await page.getByRole('button', { name: 'Войти', exact: true }).click();
    await expect(page).toHaveURL(`${cabinet}/${parent.id}`);
    await expect(page.getByRole('heading', { name: 'C4 Сессия карточки', exact: true })).toBeVisible();
  } finally {
    await context.close();
  }
});
