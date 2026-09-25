import { test, expect, type APIResponse, type Page, type Response } from '@playwright/test';
import { login, token } from './helpers.js';

const globalPath = '/api/v1/catalog/conditions';
const errors = new WeakMap<Page, string[]>();
const key = () => crypto.randomUUID().replace(/-/g, '');
type ProductKind = 'physical' | 'digital' | 'bundle';
type Conditions = {
  revision: number;
  catalogRevision: number;
  conditionsRevision?: number;
  inherit?: boolean;
  products: {
    id: string;
    name: string;
    kind: ProductKind;
    price: number;
    active: boolean;
    staffDiscount: boolean;
  }[];
  giftThreshold: number;
  giftForStaff: boolean;
};

async function body(response: APIResponse | Response, status = 200) {
  expect(response.status(), await response.text()).toBe(status);
  return response.json();
}
async function auth(page: Page, idempotencyKey?: string) {
  return {
    Authorization: 'Bearer ' + (await token(page)),
    ...(idempotencyKey ? { 'Idempotency-Key': idempotencyKey } : {})
  };
}
async function createProduct(page: Page, name: string, kind: ProductKind, price: number) {
  return (
    await body(
      await page.request.post('/api/v1/catalog/products', {
        headers: await auth(page, key()),
        data: {
          name,
          description: 'E3 реальные условия продаж',
          kind,
          price,
          printCount: kind === 'physical' ? 1 : 0,
          format: kind === 'physical' ? '10×15' : 'Электронный файл',
          unit: kind === 'bundle' ? 'комплект' : kind === 'digital' ? 'файл' : 'шт.',
          active: true,
          staffDiscount: kind !== 'bundle'
        }
      }),
      201
    )
  ).data as { id: string; revision: number };
}
function conditionBody(snapshot: Conditions, ids: { physical: string; digital: string; bundle: string }, group = false) {
  return {
    revision: snapshot.revision,
    catalogRevision: snapshot.catalogRevision,
    ...(group ? { conditionsRevision: snapshot.conditionsRevision, inherit: false } : {}),
    products: snapshot.products.map((product) => ({
      id: product.id,
      price:
        product.id === ids.physical
          ? group
            ? 15000
            : 10000
          : product.id === ids.digital
            ? 3000
            : product.id === ids.bundle
              ? 50000
              : product.price,
      active: product.kind === 'bundle' ? product.id === ids.bundle : product.active,
      staffDiscount: product.id === ids.physical || product.id === ids.digital
    })),
    giftEnabled: true,
    giftThreshold: group ? 30000 : 20000,
    giftForStaff: true
  };
}
async function createStructure(page: Page) {
  const institution = (
    await body(
      await page.request.post('/api/v1/institutions', {
        headers: await auth(page, key()),
        data: { name: 'E3 Детский сад', address: 'Москва, тестовая улица, 3' }
      }),
      201
    )
  ).data as { id: string };
  const shoot = (
    await body(
      await page.request.post(`/api/v1/institutions/${institution.id}/shoots`, {
        headers: await auth(page, key()),
        data: { name: 'E3 Осенняя съёмка', date: '2026-10-03' }
      }),
      201
    )
  ).data as { id: string };
  const group = (
    await body(
      await page.request.post(`/api/v1/shoots/${shoot.id}/groups`, {
        headers: await auth(page, key()),
        data: { name: 'E3 Ромашки', groupKind: 'regular' }
      }),
      201
    )
  ).data as { id: string };
  return { institution: institution.id, shoot: shoot.id, group: group.id };
}

test.beforeEach(async ({ page, request }) => {
  expect(await (await request.get('/__e2e')).json()).toEqual({ fixture: 'rabit-real-e2e' });
  const messages: string[] = [];
  errors.set(page, messages);
  page.on('pageerror', (error) => messages.push(error.message));
  page.on('console', (message) => {
    if (message.type() === 'error' && !message.text().startsWith('Failed to load resource:')) messages.push(message.text());
  });
  page.on('response', (response) => {
    const url = new URL(response.url());
    if (response.status() >= 500 && url.pathname.startsWith('/api/')) messages.push(`${response.status()} ${url.pathname}`);
  });
});
test.afterEach(async ({ page }) => {
  expect(errors.get(page)).toEqual([]);
  expect(await page.evaluate(() => '__MOREFOTO_MOCKS__' in window)).toBe(false);
  expect(await page.evaluate(() => Object.keys(localStorage).some((name) => name.startsWith('morefoto:demo:')))).toBe(false);
});

test('E3: общие и групповые условия проходят CAS, idempotency и live UI', async ({ page }, testInfo) => {
  test.setTimeout(90000);
  await page.setViewportSize({ width: 1440, height: 1000 });
  await login(page);
  const physical = await createProduct(page, 'E3 Печатный портрет', 'physical', 9000);
  const digital = await createProduct(page, 'E3 Электронный кадр', 'digital', 2500);
  const bundle = await createProduct(page, 'E3 Электронный комплект', 'bundle', 45000);
  const ids = { physical: physical.id, digital: digital.id, bundle: bundle.id };

  const initial = (await body(await page.request.get(globalPath, { headers: await auth(page) }))).data as Conditions;
  expect(initial.revision).toBe(1);
  expect(initial.products.find((product) => product.id === bundle.id)?.kind).toBe('bundle');
  const globalBody = conditionBody(initial, ids);
  const replayKey = key();
  const saved = await body(await page.request.put(globalPath, { headers: await auth(page, replayKey), data: globalBody }));
  expect(saved.data.revision).toBe(2);
  expect((await body(await page.request.put(globalPath, { headers: await auth(page, replayKey), data: globalBody }))).data).toEqual(
    saved.data
  );
  expect(
    (
      await page.request.put(globalPath, {
        headers: await auth(page, replayKey),
        data: { ...globalBody, giftThreshold: globalBody.giftThreshold + 1 }
      })
    ).status()
  ).toBe(409);
  expect((await page.request.put(globalPath, { headers: await auth(page, key()), data: globalBody })).status()).toBe(409);

  await page.goto('/cabinet/catalog');
  await page.getByRole('tab', { name: 'Общие условия', exact: true }).click();
  await expect(page).toHaveURL(/\/cabinet\/catalog\?tab=conditions$/);
  await expect(page.getByRole('heading', { name: 'Общие условия', exact: true })).toBeVisible();
  await expect(page.getByTestId('conditions-summary')).toContainText(/200\s*₽/);
  await page.screenshot({ path: testInfo.outputPath('desktop-global-conditions.png'), fullPage: true });
  await page.getByRole('button', { name: 'Изменить условия', exact: true }).click();
  await expect(page.getByLabel('Порог подарка, ₽', { exact: true })).toHaveValue('200');
  await page.getByLabel('Порог подарка, ₽', { exact: true }).fill('225');
  const globalSave = page.waitForResponse(
    (response) => new URL(response.url()).pathname === globalPath && response.request().method() === 'PUT'
  );
  await page.getByTestId('admin-dialog').getByRole('button', { name: 'Сохранить', exact: true }).click();
  expect((await globalSave).status()).toBe(200);
  await expect(page.getByTestId('admin-dialog')).not.toBeVisible();
  await expect(page.getByTestId('conditions-summary')).toContainText(/225\s*₽/);

  const structure = await createStructure(page);
  const groupPath = `/api/v1/groups/${structure.group}/conditions`;
  const inherited = (await body(await page.request.get(groupPath, { headers: await auth(page) }))).data as Conditions;
  expect(inherited.revision).toBe(0);
  expect(inherited.inherit).toBe(true);
  expect(inherited.giftThreshold).toBe(22500);
  const groupBody = conditionBody(inherited, ids, true);
  const groupSaved = await body(await page.request.put(groupPath, { headers: await auth(page, key()), data: groupBody }));
  expect(groupSaved.data.revision).toBe(1);
  expect((await page.request.put(groupPath, { headers: await auth(page, key()), data: groupBody })).status()).toBe(409);
  const globalBeforeConflict = (await body(await page.request.get(globalPath, { headers: await auth(page) }))).data as Conditions;
  const conflictingGlobal = conditionBody(globalBeforeConflict, ids);
  const bundleCondition = conflictingGlobal.products.find((product) => product.id === bundle.id);
  expect(bundleCondition).toBeDefined();
  bundleCondition!.active = false;
  conflictingGlobal.giftEnabled = false;
  conflictingGlobal.giftThreshold = 0;
  conflictingGlobal.giftForStaff = false;
  expect((await page.request.put(globalPath, { headers: await auth(page, key()), data: conflictingGlobal })).status()).toBe(422);
  const globalAfterConflict = (await body(await page.request.get(globalPath, { headers: await auth(page) }))).data as Conditions;
  expect(globalAfterConflict.revision).toBe(globalBeforeConflict.revision);
  expect(globalAfterConflict.catalogRevision).toBe(globalBeforeConflict.catalogRevision);
  expect(globalAfterConflict.products.find((product) => product.id === bundle.id)?.active).toBe(true);

  expect((await page.request.get(`/api/v1/groups/${crypto.randomUUID()}/conditions`, { headers: await auth(page) })).status()).toBe(404);

  const conditionsPage = `/cabinet/institutions/${structure.institution}/shoots/${structure.shoot}/conditions?group=${structure.group}`;
  await page.goto(conditionsPage);
  await expect(page.getByRole('heading', { name: 'Условия групп', exact: true })).toBeVisible();
  await expect(page.getByText('Использует собственные условия.', { exact: true })).toBeVisible();
  await expect(page.getByTestId('conditions-summary')).toContainText(/300\s*₽/);
  await page.getByRole('button', { name: 'Изменить условия группы', exact: true }).click();
  await expect(page.getByLabel('Цена: E3 Печатный портрет', { exact: true })).toHaveValue('150');
  await page.getByLabel('Цена: E3 Печатный портрет', { exact: true }).fill('175');
  const groupSave = page.waitForResponse(
    (response) => new URL(response.url()).pathname === groupPath && response.request().method() === 'PUT'
  );
  await page.getByTestId('admin-dialog').getByRole('button', { name: 'Сохранить', exact: true }).click();
  expect((await groupSave).status()).toBe(200);
  await expect(page.getByTestId('admin-dialog')).not.toBeVisible();
  expect(
    ((await body(await page.request.get(groupPath, { headers: await auth(page) }))).data as Conditions).products.find(
      (product) => product.id === physical.id
    )?.price
  ).toBe(17500);
  await page.screenshot({ path: testInfo.outputPath('desktop-group-conditions.png'), fullPage: true });
  await page.getByRole('button', { name: 'Изменить условия группы', exact: true }).click();
  await page.getByLabel('Цена: E3 Печатный портрет', { exact: true }).fill('invalid');
  await page.getByLabel('Общие условия каталога', { exact: true }).check();
  const inheritSave = page.waitForResponse(
    (response) => new URL(response.url()).pathname === groupPath && response.request().method() === 'PUT'
  );
  await page.getByTestId('admin-dialog').getByRole('button', { name: 'Сохранить', exact: true }).click();
  const inheritResponse = await inheritSave;
  expect(inheritResponse.status()).toBe(200);
  expect(inheritResponse.request().postDataJSON()).toMatchObject({
    inherit: true,
    products: [],
    giftEnabled: false,
    giftThreshold: 0,
    giftForStaff: false
  });
  await expect(page.getByTestId('admin-dialog')).not.toBeVisible();
  const inheritedAgain = (await body(await page.request.get(groupPath, { headers: await auth(page) }))).data as Conditions;
  expect(inheritedAgain.inherit).toBe(true);
  expect(inheritedAgain.giftThreshold).toBe(22500);
  expect(inheritedAgain.products.find((product) => product.id === physical.id)?.price).toBe(10000);
  await expect(page.getByText('Наследует общие условия.', { exact: true })).toBeVisible();
  await expect(page.getByTestId('conditions-summary')).toContainText(/225\s*₽/);

  await page.setViewportSize({ width: 390, height: 844 });
  await page.reload();
  await expect(page.getByRole('heading', { name: 'Условия групп', exact: true })).toBeVisible();
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
  await page.screenshot({ path: testInfo.outputPath('mobile-group-conditions.png'), fullPage: true });
});

test('E3: воспитатель не читает и не меняет условия прямым запросом', async ({ page }) => {
  await login(page, 'teacher');
  expect((await page.request.get(globalPath, { headers: await auth(page) })).status()).toBe(403);
  expect(
    (
      await page.request.put(globalPath, {
        headers: await auth(page, key()),
        data: {
          revision: 1,
          catalogRevision: 1,
          products: [],
          giftEnabled: false,
          giftThreshold: 0,
          giftForStaff: false
        }
      })
    ).status()
  ).toBe(403);
  await page.goto('/cabinet/catalog');
  await expect(page.getByRole('heading', { name: 'Недостаточно прав', exact: true })).toBeVisible();
});

test('E3: потерянный ответ условий повторяется с тем же ключом и телом', async ({ page }) => {
  await login(page);
  const snapshot = (await body(await page.request.get(globalPath, { headers: await auth(page) }))).data as Conditions;
  let lost = false;
  const requests: { key?: string; data: string | null }[] = [];
  await page.route('**/api/v1/catalog/conditions', async (route) => {
    if (route.request().method() !== 'PUT') return route.continue();
    requests.push({ key: route.request().headers()['idempotency-key'], data: route.request().postData() });
    if (!lost) {
      lost = true;
      expect((await route.fetch()).status()).toBe(200);
      return route.abort('failed');
    }
    return route.continue();
  });
  await page.goto('/cabinet/catalog?tab=conditions');
  await page.getByRole('button', { name: 'Изменить условия', exact: true }).click();
  await page.getByLabel('Порог подарка, ₽', { exact: true }).fill(String((snapshot.giftThreshold + 100) / 100));
  await page.getByTestId('admin-dialog').getByRole('button', { name: 'Сохранить', exact: true }).click();
  await expect(page.getByText(/Ответ на сохранение не получен/)).toBeVisible();
  await page.reload();
  await page.getByRole('button', { name: 'Изменить условия', exact: true }).click();
  await expect(page.getByText(/Ответ на сохранение не получен/)).toBeVisible();
  await page.getByTestId('admin-dialog').getByRole('button', { name: 'Сохранить', exact: true }).click();
  await expect(page.getByTestId('admin-dialog')).not.toBeVisible();
  expect(requests).toHaveLength(2);
  expect(requests[0]).toEqual(requests[1]);
});
