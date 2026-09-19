import { test, expect, type APIResponse, type Page, type Response } from '@playwright/test';
import { login, password, token } from './helpers.js';

const usersApi = '/api/v1/users';
const key = () => crypto.randomUUID().replace(/-/g, '');
async function auth(page: Page) {
  return { Authorization: 'Bearer ' + (await token(page)), 'Idempotency-Key': key() };
}
async function body(response: APIResponse | Response, status = 200) {
  expect(response.status(), await response.text()).toBe(status);
  return response.json();
}
async function create(page: Page, path: string, data: Record<string, unknown>) {
  return (await body(await page.request.post(path, { headers: await auth(page), data }), 201)).data;
}
async function organization(page: Page) {
  const institution = await create(page, '/api/v1/institutions', { name: 'B2 Детский сад', address: 'Москва, Цветочная улица, 7' });
  const shoot = await create(page, '/api/v1/institutions/' + institution.id + '/shoots', { name: 'B2 Осенняя съёмка', date: '2026-10-20' });
  const group = await create(page, '/api/v1/shoots/' + shoot.id + '/groups', { name: 'B2 Ромашки', groupKind: 'regular' });
  return { institution, shoot, group };
}
async function staffList(page: Page) {
  return (await body(await page.request.get(usersApi, { headers: await auth(page) }))).data.items as Array<{
    id: number;
    name: string;
    email: string;
    role: string;
    active: boolean;
    revision: number;
  }>;
}
async function detail(page: Page, id: number) {
  return (await body(await page.request.get(usersApi + '/' + id, { headers: await auth(page) }))).data;
}
async function saveStaff(page: Page, id: number, data: Record<string, unknown>, status = 200) {
  return body(await page.request.patch(usersApi + '/' + id, { headers: await auth(page), data }), status);
}

test.beforeEach(async ({ request }) => {
  expect(await (await request.get('/__e2e')).json()).toEqual({ fixture: 'rabit-real-e2e' });
});
test.afterEach(async ({ page }) => {
  expect(await page.evaluate(() => '__MOREFOTO_MOCKS__' in window)).toBe(false);
});

test('B2: организатор приглашает учителя и назначает реальную группу', async ({ page }, testInfo) => {
  await page.setViewportSize({ width: 1440, height: 1000 });
  await login(page);
  await organization(page);
  await page.getByRole('link', { name: 'Сотрудники', exact: true }).click();
  await expect(page.getByRole('heading', { name: 'Сотрудники', exact: true })).toBeVisible();
  await page.getByRole('button', { name: 'Добавить сотрудника', exact: true }).click();
  const dialog = page.getByTestId('admin-dialog');
  await expect(dialog).toBeVisible();
  await dialog.getByLabel('Имя сотрудника', { exact: true }).fill('B2 Новый учитель');
  await dialog.getByLabel('Email сотрудника', { exact: true }).fill('b2-teacher@example.invalid');
  await dialog.getByLabel('Назначенные группы', { exact: true }).click();
  await page
    .getByRole('option', { name: /B2 Детский сад → B2 Осенняя съёмка → B2 Ромашки/ })
    .last()
    .click();
  await page.keyboard.press('Escape');
  const pending = page.waitForResponse(
    (response) => new URL(response.url()).pathname === usersApi && response.request().method() === 'POST'
  );
  await dialog.getByRole('button', { name: 'Сохранить', exact: true }).click();
  const result = await body(await pending, 201);
  expect(result.data).not.toHaveProperty('password');
  expect(result.data.accountStatus).toBe('pending');
  await expect(dialog).not.toBeVisible();
  await expect(page.getByRole('button', { name: 'Редактировать сотрудника B2 Новый учитель' })).toContainText('Ожидает регистрации');
  const created = await detail(page, result.data.id);
  expect(created.groupIds).toHaveLength(1);
  expect(created.institutionIds).toEqual([]);
  await page.screenshot({ path: testInfo.outputPath('b2-desktop-staff.png'), fullPage: true });
  expect((await page.request.post('/api/v1/auth/login', { data: { email: 'b2-teacher@example.invalid', password } })).status()).toBe(401);
});

test('B2: замена назначения требует подпись и отзывает старый токен', async ({ page, browser, baseURL }) => {
  await login(page);
  const { group } = await organization(page);
  const list = await staffList(page);
  const first = list.find((item) => item.email === 'teacher@example.invalid')!;
  const second = list.find((item) => item.email === 'another-teacher@example.invalid')!;
  const firstDetail = await detail(page, first.id);
  let options = (await body(await page.request.get(usersApi + '/assignment-options', { headers: await auth(page) }))).data;
  const assigned = await saveStaff(page, first.id, {
    name: firstDetail.name,
    email: firstDetail.email,
    role: 'teacher',
    active: true,
    institutionIds: [],
    groupIds: [group.id],
    assignmentSignature: options.assignmentSignature,
    revision: firstDetail.revision
  });
  const worker = await browser.newContext({ baseURL });
  try {
    const teacher = await worker.newPage();
    await login(teacher, 'teacher');
    const oldToken = await token(teacher);
    const secondDetail = await detail(page, second.id);
    const occupied = await saveStaff(
      page,
      second.id,
      {
        name: secondDetail.name,
        email: secondDetail.email,
        role: 'teacher',
        active: true,
        institutionIds: [],
        groupIds: [group.id],
        assignmentSignature: assigned.data.assignmentSignature,
        revision: secondDetail.revision
      },
      409
    );
    expect(occupied.error.code).toBe('ASSIGNMENT_OCCUPIED');
    options = (await body(await page.request.get(usersApi + '/assignment-options', { headers: await auth(page) }))).data;
    const replaced = await saveStaff(page, second.id, {
      name: secondDetail.name,
      email: secondDetail.email,
      role: 'teacher',
      active: true,
      institutionIds: [],
      groupIds: [group.id],
      replaceAssignments: true,
      reason: 'Передача группы другому педагогу',
      assignmentSignature: options.assignmentSignature,
      revision: secondDetail.revision
    });
    expect(replaced.data.accessRevision).toBeGreaterThan(secondDetail.accessRevision);
    expect((await detail(page, first.id)).groupIds).toEqual([]);
    expect((await page.request.get('/api/v1/me', { headers: { Authorization: 'Bearer ' + oldToken } })).status()).toBe(401);
  } finally {
    await worker.close();
  }
});

test('B2: teacher не читает управление, а последний организатор защищён', async ({ page, browser, baseURL }) => {
  await login(page);
  const context = await browser.newContext({ baseURL });
  try {
    const teacher = await context.newPage();
    await login(teacher, 'teacher');
    expect((await teacher.request.get(usersApi, { headers: await auth(teacher) })).status()).toBe(403);
  } finally {
    await context.close();
  }
  const list = await staffList(page);
  const actor = list.find((item) => item.email === 'organizer@example.invalid')!;
  const other = list.find((item) => item.email === 'another-organizer@example.invalid')!;
  const otherDetail = await detail(page, other.id);
  await saveStaff(page, other.id, {
    name: otherDetail.name,
    email: otherDetail.email,
    role: 'organizer',
    active: false,
    institutionIds: [],
    groupIds: [],
    assignmentSignature: otherDetail.assignmentSignature,
    revision: otherDetail.revision
  });
  const actorDetail = await detail(page, actor.id);
  const denied = await saveStaff(
    page,
    actor.id,
    {
      name: actorDetail.name,
      email: actorDetail.email,
      role: 'organizer',
      active: false,
      institutionIds: [],
      groupIds: [],
      assignmentSignature: actorDetail.assignmentSignature,
      revision: actorDetail.revision
    },
    409
  );
  expect(denied.error.code).toBe('LAST_ORGANIZER');
  const currentOther = await detail(page, other.id);
  await saveStaff(page, other.id, {
    name: currentOther.name,
    email: currentOther.email,
    role: 'organizer',
    active: true,
    institutionIds: [],
    groupIds: [],
    assignmentSignature: currentOther.assignmentSignature,
    revision: currentOther.revision
  });
});

test('B2: мобильный список не создаёт горизонтальную прокрутку', async ({ page }, testInfo) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await login(page);
  await page.goto('/cabinet/users');
  await expect(page.getByRole('heading', { name: 'Сотрудники', exact: true })).toBeVisible();
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
  await page.screenshot({ path: testInfo.outputPath('b2-mobile-staff.png'), fullPage: true });
});
