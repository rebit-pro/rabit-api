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
async function organization(page: Page, suffix = '') {
  const institution = await create(page, '/api/v1/institutions', {
    name: 'B2 Детский сад' + suffix,
    address: 'Москва, Цветочная улица, 7'
  });
  const shoot = await create(page, '/api/v1/institutions/' + institution.id + '/shoots', {
    name: 'B2 Осенняя съёмка' + suffix,
    date: '2026-10-20'
  });
  const group = await create(page, '/api/v1/shoots/' + shoot.id + '/groups', { name: 'B2 Ромашки' + suffix, groupKind: 'regular' });
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
  const name = dialog.getByLabel('Имя сотрудника', { exact: true });
  const email = dialog.getByLabel('Email сотрудника', { exact: true });
  await name.fill('B2 Новый учитель');
  await email.fill('b2-teacher@example.invalid');
  await expect(name).toHaveValue('B2 Новый учитель');
  const role = dialog.getByRole('combobox', { name: 'Роль сотрудника', exact: true });
  await role.press('Enter');
  await page.getByRole('option', { name: 'Куратор', exact: true }).click();
  await expect(dialog.getByLabel('Назначенные учреждения', { exact: true })).toBeVisible();
  await expect(dialog.getByLabel('Назначенные группы', { exact: true })).toHaveCount(0);
  await role.press('Enter');
  await page.getByRole('option', { name: 'Ответственный группы', exact: true }).click();
  await dialog.getByLabel('Назначенные группы', { exact: true }).click();
  await page
    .getByRole('option', { name: /B2 Детский сад → B2 Осенняя съёмка → B2 Ромашки/ })
    .last()
    .click();
  await page.keyboard.press('Escape');
  await expect(dialog.locator('.v-autocomplete .v-chip')).toHaveCount(1);
  await expect(name).toHaveValue('B2 Новый учитель');
  await expect(email).toHaveValue('b2-teacher@example.invalid');
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

test('B2: форма показывает замену занятой группы и сохраняет видимые значения', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 1000 });
  await login(page);
  const suffix = ' ' + key().slice(0, 6);
  const { group } = await organization(page, suffix);
  const options = (await body(await page.request.get(usersApi + '/assignment-options', { headers: await auth(page) }))).data;
  const holderName = 'B2 Прежний педагог' + suffix;
  const holder = await create(page, usersApi, {
    name: holderName,
    email: 'b2-holder-' + key().slice(0, 8) + '@example.invalid',
    role: 'teacher',
    active: true,
    institutionIds: [],
    groupIds: [group.id],
    replaceAssignments: false,
    assignmentSignature: options.assignmentSignature
  });
  const successorName = 'B2 Новый педагог' + suffix;
  const successor = await create(page, usersApi, {
    name: successorName,
    email: 'b2-successor-' + key().slice(0, 8) + '@example.invalid',
    role: 'teacher',
    active: true,
    institutionIds: [],
    groupIds: [],
    replaceAssignments: false,
    assignmentSignature: holder.assignmentSignature
  });
  await page.goto('/cabinet/users');
  await page.getByLabel('Имя или email', { exact: true }).fill(successorName);
  await page.getByRole('button', { name: 'Найти', exact: true }).click();
  await page.getByRole('button', { name: 'Редактировать сотрудника ' + successorName, exact: true }).click();
  const dialog = page.getByTestId('admin-dialog');
  await expect(dialog.getByRole('heading', { name: 'Редактирование сотрудника', exact: true })).toBeVisible();
  await dialog.getByLabel('Назначенные группы', { exact: true }).click();
  await page.getByRole('option', { name: new RegExp('B2 Ромашки' + suffix) }).click();
  await page.keyboard.press('Escape');
  await expect(dialog.getByText('Вы заменяете: ' + holderName + '.', { exact: false })).toBeVisible();
  await dialog.getByRole('button', { name: 'Сохранить', exact: true }).click();
  await expect(dialog.getByText('Подтвердите замену ответственных и укажите причину.', { exact: true })).toBeVisible();
  await dialog.getByLabel('Подтвердить замену ответственных', { exact: true }).check();
  await dialog.getByLabel('Причина замены', { exact: true }).fill('Передача группы новому педагогу');
  const pending = page.waitForResponse(
    (response) => new URL(response.url()).pathname === usersApi + '/' + successor.id && response.request().method() === 'PATCH'
  );
  await dialog.getByRole('button', { name: 'Сохранить', exact: true }).click();
  const response = await pending;
  await body(response, 200);
  expect(response.request().postDataJSON()).toMatchObject({
    name: successorName,
    role: 'teacher',
    active: true,
    groupIds: [group.id],
    replaceAssignments: true,
    reason: 'Передача группы новому педагогу'
  });
  await expect(dialog).not.toBeVisible();
  expect((await detail(page, holder.id)).groupIds).toEqual([]);
  expect((await detail(page, successor.id)).groupIds).toEqual([group.id]);
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
