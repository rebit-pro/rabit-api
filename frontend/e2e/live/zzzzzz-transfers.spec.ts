import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { crc32, deflateSync } from 'node:zlib';
import { test, expect, type APIResponse, type Browser, type Page, type Response, type Route } from '@playwright/test';
import { login, token } from './helpers.js';

type Media = {
  items: { id: string; status: string; groupId: string; assignments: { childCode: string; sequence: number; code: string }[] }[];
  covers: Record<string, string>;
  revision: number;
};
type Result = {
  rowId: string;
  fromGroupId: string;
  fromChildCode: string;
  targetGroupId: string;
  targetChildCode: string;
  photoIds: string[];
};
const key = () => crypto.randomUUID().replace(/-/g, '');
const buyer = {
  name: 'Мария Тестовая',
  phone: '8 (900) 555-03-04',
  email: 'transfer.d3@example.test',
  comment: 'D3 проверка',
  reviewed: true
};

/** A distinct valid PNG per seed: identical files inside one shoot are de-duplicated by the server. */
function png(seed: number): Buffer {
  const width = 64;
  const height = 48;
  const raw = Buffer.alloc((width * 3 + 1) * height);
  for (let y = 0; y < height; y++) {
    for (let x = 0; x < width; x++) {
      const at = y * (width * 3 + 1) + 1 + x * 3;
      raw[at] = (seed * 53 + x * 3) & 255;
      raw[at + 1] = (seed * 97 + y * 5) & 255;
      raw[at + 2] = (seed * 31 + x + y) & 255;
    }
  }
  const chunk = (type: string, data: Buffer) => {
    const length = Buffer.alloc(4);
    length.writeUInt32BE(data.length);
    const crc = Buffer.alloc(4);
    crc.writeUInt32BE(crc32(Buffer.concat([Buffer.from(type), data])));
    return Buffer.concat([length, Buffer.from(type), data, crc]);
  };
  const header = Buffer.alloc(13);
  header.writeUInt32BE(width, 0);
  header.writeUInt32BE(height, 4);
  header[8] = 8;
  header[9] = 2;
  return Buffer.concat([
    Buffer.from([137, 80, 78, 71, 13, 10, 26, 10]),
    chunk('IHDR', header),
    chunk('IDAT', deflateSync(raw)),
    chunk('IEND', Buffer.alloc(0))
  ]);
}
/** The E5 verifier accounts for every order in the database, so D3 adds its orders to the shared browser record. */
function rememberOrder(created: { id: string; accessKey: string }, idempotencyKey: string) {
  const path = 'var/e5-orders.json';
  const record = existsSync(path)
    ? JSON.parse(readFileSync(path, 'utf8'))
    : { accessKeys: [], idempotencyKeys: [], galleryToken: '', orderIds: [] };
  record.orderIds.push(created.id);
  record.accessKeys.push(created.accessKey);
  record.idempotencyKeys.push(idempotencyKey);
  mkdirSync('var', { recursive: true });
  writeFileSync(path, JSON.stringify(record));
}
async function body(response: APIResponse | Response, status = 200) {
  expect(response.status(), await response.text()).toBe(status);
  return response.json();
}
async function auth(page: Page, idempotencyKey = key()) {
  return { Authorization: 'Bearer ' + (await token(page)), 'Idempotency-Key': idempotencyKey };
}
async function create(page: Page, path: string, data: Record<string, unknown>) {
  return (await body(await page.request.post(path, { headers: await auth(page), data }), 201)).data;
}
async function media(page: Page, shootId: string): Promise<Media> {
  return (await body(await page.request.get('/api/v1/shoots/' + shootId + '/photos', { headers: await auth(page) }))).data;
}
/** Uploads distinct frames and waits until the media consumer made all of them ready. */
async function upload<const T extends readonly (readonly [groupId: string, seed: number])[]>(
  page: Page,
  shootId: string,
  frames: T
): Promise<{ [K in keyof T]: string }> {
  const ids: string[] = [];
  for (const [groupId, seed] of frames) {
    const accepted = await body(
      await page.request.post('/api/v1/shoots/' + shootId + '/photos', {
        headers: { Authorization: 'Bearer ' + (await token(page)) },
        multipart: { groupId, file: { name: 'd3-' + seed + '.png', mimeType: 'image/png', buffer: png(seed) } }
      }),
      202
    );
    ids.push(accepted.data.id);
  }
  await expect
    .poll(async () => (await media(page, shootId)).items.filter((item) => ids.includes(item.id) && item.status === 'ready').length, {
      timeout: 60000
    })
    .toBe(ids.length);
  return ids as { [K in keyof T]: string };
}
async function label(page: Page, shootId: string, groupId: string, photoIds: string[], childCode: string) {
  const revision = (await media(page, shootId)).revision;
  await body(
    await page.request.post('/api/v1/groups/' + groupId + '/photo-assignments', {
      headers: await auth(page),
      data: { shootId, revision, photoIds, childCode }
    })
  );
}
async function assign(page: Page, email: string, role: 'teacher' | 'curator', institutionId: string, groupId?: string) {
  const listing = await body(await page.request.get('/api/v1/users?q=' + encodeURIComponent(email), { headers: await auth(page) }));
  const staff = listing.data.items.find((item: { email: string }) => item.email === email);
  const detail = (await body(await page.request.get('/api/v1/users/' + staff.id, { headers: await auth(page) }))).data;
  const options = (await body(await page.request.get('/api/v1/users/assignment-options', { headers: await auth(page) }))).data;
  await body(
    await page.request.patch('/api/v1/users/' + staff.id, {
      headers: await auth(page),
      data: {
        name: detail.name,
        email: detail.email,
        role,
        active: true,
        institutionIds: role === 'curator' ? [...new Set([...detail.institutionIds, institutionId])] : [],
        groupIds: role === 'teacher' && groupId ? [...new Set([...detail.groupIds, groupId])] : [],
        replaceAssignments: false,
        assignmentSignature: options.assignmentSignature,
        revision: detail.revision
      }
    })
  );
}
async function link(page: Page, groupId: string) {
  return (await body(await page.request.get('/api/v1/groups/' + groupId + '/link', { headers: await auth(page) }))).data as {
    revision: number;
    signature: string;
    prepared: boolean;
    problems: string[];
    galleryToken: string | null;
  };
}
async function prepare(page: Page, groupId: string) {
  const state = await link(page, groupId);
  expect(state.problems).toEqual([]);
  const review = { photosReviewed: true, conditionsReviewed: true, staffReviewed: true, confirmed: true };
  await body(
    await page.request.post('/api/v1/groups/' + groupId + '/link-preparations', {
      headers: await auth(page),
      data: { ...review, revision: state.revision, signature: state.signature }
    })
  );
  return link(page, groupId);
}
const moscowMinute = () => new Date(Date.now() + 3 * 3600000).toISOString().slice(0, 16) + ':00+03:00';
/** Prepares and hands over the group link; returns the public gallery token. */
async function openGallery(page: Page, groupId: string): Promise<string> {
  const ready = await prepare(page, groupId);
  await body(
    await page.request.post('/api/v1/groups/' + groupId + '/link-transmissions', {
      headers: await auth(page),
      data: { revision: ready.revision, signature: ready.signature, sentAt: moscowMinute(), confirmed: true }
    })
  );
  return ready.galleryToken!;
}
/** Group conditions without gifts keep the staff price deterministic whatever the global settings are. */
async function withoutGifts(page: Page, groupId: string) {
  const path = '/api/v1/groups/' + groupId + '/conditions';
  const snapshot = (await body(await page.request.get(path, { headers: await auth(page) }))).data;
  await body(
    await page.request.put(path, {
      headers: await auth(page),
      data: {
        revision: snapshot.revision,
        catalogRevision: snapshot.catalogRevision,
        conditionsRevision: snapshot.conditionsRevision,
        inherit: false,
        giftEnabled: false,
        giftThreshold: 0,
        giftForStaff: false,
        products: snapshot.products.map((item: { id: string; price: number; active: boolean; staffDiscount: boolean }) => ({
          id: item.id,
          price: item.price,
          active: item.active,
          staffDiscount: item.staffDiscount
        }))
      }
    })
  );
}
async function asStaff<T>(browser: Browser, baseURL: string | undefined, account: string, run: (page: Page) => Promise<T>): Promise<T> {
  const context = await browser.newContext({ baseURL });
  try {
    const page = await context.newPage();
    await login(page, account);
    return await run(page);
  } finally {
    await context.close();
  }
}
async function submitRequest(page: Page, institutionId: string, shootId: string, rows: { groupId: string; code: string }[]) {
  return create(page, '/api/v1/staff-requests', {
    institutionId,
    shootId,
    rows: rows.map((row) => ({ id: crypto.randomUUID(), ...row })),
    comment: 'Дети сотрудника'
  });
}
async function confirmTransfer(page: Page, requestId: string, data: Record<string, unknown>, status = 200, idempotencyKey = key()) {
  return body(
    await page.request.post('/api/v1/staff-requests/' + requestId + '/transfers', { headers: await auth(page, idempotencyKey), data }),
    status
  );
}
async function preview(page: Page, requestId: string, status = 200) {
  return body(await page.request.get('/api/v1/staff-requests/' + requestId + '/transfer-preview', { headers: await auth(page) }), status);
}
async function chooseChild(page: Page, code: string) {
  // The frames filter has no accessible name, so it is opened through its test id.
  await page.getByTestId('photo-filter').click();
  await page.getByRole('option', { name: 'Ребёнок ' + code, exact: true }).click();
  await page.getByRole('button', { name: 'Перенести весь набор', exact: true }).click();
  await expect(page.getByRole('heading', { name: 'Перенести набор ребёнка ' + code, exact: true })).toBeVisible();
}

test.beforeEach(async ({ page, request }) => {
  expect(await (await request.get('/__e2e')).json()).toEqual({ fixture: 'rabit-real-e2e' });
  page.on('response', (response) => {
    const path = new URL(response.url()).pathname;
    expect(response.status() >= 500 && path.startsWith('/api/'), response.status() + ' ' + path).toBe(false);
  });
});
test.afterEach(async ({ page }) => {
  expect(await page.evaluate(() => Object.keys(localStorage).some((name) => name.startsWith('morefoto:demo:')))).toBe(false);
});

test('D3: организатор переносит полный набор ребёнка между группами до передачи ссылки', async ({ page, browser, baseURL }, testInfo) => {
  test.setTimeout(240000);
  await page.setViewportSize({ width: 1440, height: 1000 });
  await login(page);
  const suffix = crypto.randomUUID().slice(0, 8);
  const institution = await create(page, '/api/v1/institutions', { name: 'D3 Детский сад ' + suffix, address: 'Москва' });
  const shoot = await create(page, '/api/v1/institutions/' + institution.id + '/shoots', { name: 'D3 Съёмка', date: '2026-10-22' });
  const from = await create(page, '/api/v1/shoots/' + shoot.id + '/groups', { name: 'D3 Ромашки', groupKind: 'regular' });
  const to = await create(page, '/api/v1/shoots/' + shoot.id + '/groups', { name: 'D3 Солнышко', groupKind: 'regular' });
  const staff = await create(page, '/api/v1/shoots/' + shoot.id + '/groups', { name: 'D3 Сотрудники', groupKind: 'staff' });
  const [a1, a2, b1, c1, t1] = await upload(page, shoot.id, [
    [from.id, 1],
    [from.id, 2],
    [from.id, 3],
    [from.id, 4],
    [to.id, 5]
  ]);
  await label(page, shoot.id, from.id, [a1, a2], 'A');
  await label(page, shoot.id, from.id, [b1], 'B');
  await label(page, shoot.id, from.id, [c1], 'C');
  // C001 is a joint frame of C and D.
  await label(page, shoot.id, from.id, [c1], 'D');
  await label(page, shoot.id, to.id, [t1], 'A');
  await body(
    await page.request.put('/api/v1/groups/' + from.id + '/cover', {
      headers: await auth(page),
      data: { revision: (await media(page, shoot.id)).revision, photoId: a1 }
    })
  );
  await assign(page, 'curator@example.invalid', 'curator', institution.id);
  const revision = (await media(page, shoot.id)).revision;
  const path = '/api/v1/shoots/' + shoot.id + '/child-transfers';
  const transfer = async (data: Record<string, unknown>, status: number, idempotencyKey = key(), viewer = page) =>
    body(await viewer.request.post(path, { headers: await auth(viewer, idempotencyKey), data }), status);
  const move = { fromGroupId: from.id, toGroupId: to.id, childCode: 'A', targetCode: 'E', expectedPhotoIds: [a1, a2], revision };

  expect((await transfer({ ...move, toGroupId: staff.id }, 409)).error.code).toBe('GROUP_KIND_MISMATCH');
  expect((await transfer({ ...move, childCode: 'C', expectedPhotoIds: [c1] }, 409)).error).toMatchObject({
    code: 'SHARED_PHOTO',
    details: { photoCodes: ['C001'] }
  });
  expect((await transfer({ ...move, revision: revision + 1 }, 409)).error.code).toBe('REVISION_CONFLICT');
  expect((await transfer({ ...move, expectedPhotoIds: [a1] }, 409)).error.code).toBe('SET_CHANGED');
  expect((await transfer({ ...move, targetCode: 'e' }, 422)).error.code).toBe('VALIDATION_FAILED');
  expect((await transfer({ ...move, extra: true }, 422)).error.code).toBe('UNKNOWN_FIELD');
  await asStaff(browser, baseURL, 'curator', async (curator) => {
    expect((await transfer(move, 403, key(), curator)).error.code).toBe('FORBIDDEN');
  });
  expect((await media(page, shoot.id)).revision).toBe(revision);

  await page.goto('/cabinet/institutions/' + institution.id + '/shoots/' + shoot.id + '/photos?group=' + from.id);
  await chooseChild(page, 'A');
  const submit = page.getByRole('dialog').getByRole('button', { name: 'Перенести набор', exact: true });
  await submit.click();
  await expect(page.getByRole('alert').filter({ hasText: 'Этот код уже занят в целевой группе' })).toBeVisible();
  await page.screenshot({ path: testInfo.outputPath('d3-desktop-child-transfer.png'), fullPage: true, animations: 'disabled' });
  await page.getByLabel('Код в целевой группе', { exact: true }).fill('E');
  const moved = page.waitForResponse((r) => r.url().endsWith('/child-transfers') && r.request().method() === 'POST' && r.status() === 200);
  await submit.click();
  expect((await (await moved).json()).data).toEqual({
    photoIds: [a1, a2],
    fromGroupId: from.id,
    toGroupId: to.id,
    childCode: 'E',
    revision: revision + 1
  });
  await expect(page.getByRole('status').filter({ hasText: 'Полный набор ребёнка перенесён.' })).toBeVisible();

  expect((await body(await page.request.get('/api/v1/photos/' + a1, { headers: await auth(page) }))).data).toMatchObject({
    id: a1,
    groupId: to.id,
    originalGroupId: from.id,
    childCode: 'E',
    code: 'E001'
  });
  const after = await media(page, shoot.id);
  expect(after.items.find((item) => item.id === a2)?.assignments).toMatchObject([{ childCode: 'E', sequence: 2, code: 'E002' }]);
  // The moved cover is replaced by the first remaining frame; the target without a cover gets the first moved frame.
  expect([after.covers[from.id], after.covers[to.id]]).toEqual([b1, a1]);

  const replayKey = key();
  const moveB = {
    fromGroupId: from.id,
    toGroupId: to.id,
    childCode: 'B',
    targetCode: 'F',
    expectedPhotoIds: [b1],
    revision: after.revision
  };
  const first = (await transfer(moveB, 200, replayKey)).data;
  expect((await transfer(moveB, 200, replayKey)).data).toEqual(first);
  expect((await transfer({ ...moveB, targetCode: 'G' }, 409, replayKey)).error.code).toBe('IDEMPOTENCY_CONFLICT');
  expect((await media(page, shoot.id)).revision).toBe(after.revision + 1);

  await page.setViewportSize({ width: 390, height: 844 });
  await page.reload();
  await chooseChild(page, 'C');
  await page.getByLabel('Код в целевой группе', { exact: true }).fill('H');
  await submit.click();
  await expect(page.getByRole('alert').filter({ hasText: 'Кадры C001 назначены ещё и другому ребёнку этой группы' })).toBeVisible();
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
  await page.screenshot({ path: testInfo.outputPath('d3-mobile-child-transfer.png'), fullPage: true, animations: 'disabled' });
});

test('D3: куратор переносит детей сотрудника после заказа, заказ и льгота сохраняются', async ({ page, browser, baseURL }, testInfo) => {
  test.setTimeout(300000);
  await page.setViewportSize({ width: 1440, height: 1000 });
  await login(page);
  const suffix = crypto.randomUUID().slice(0, 8);
  const institution = await create(page, '/api/v1/institutions', { name: 'D3 Сад сотрудников ' + suffix, address: 'Москва' });
  const shoot = await create(page, '/api/v1/institutions/' + institution.id + '/shoots', { name: 'D3 Осенняя съёмка', date: '2026-10-23' });
  const regular = await create(page, '/api/v1/shoots/' + shoot.id + '/groups', { name: 'D3 Звёздочки', groupKind: 'regular' });
  const staff = await create(page, '/api/v1/shoots/' + shoot.id + '/groups', { name: 'D3 Сотрудники', groupKind: 'staff' });
  await create(page, '/api/v1/catalog/products', {
    name: 'D3 Печать ' + suffix,
    description: '',
    kind: 'physical',
    price: 15000,
    printCount: 1,
    format: '10×15',
    unit: 'шт.',
    staffDiscount: true,
    active: true
  });
  const [r1, r2, r3, r4, s1] = await upload(page, shoot.id, [
    [regular.id, 11],
    [regular.id, 12],
    [regular.id, 13],
    [regular.id, 14],
    [staff.id, 15]
  ]);
  await label(page, shoot.id, regular.id, [r1, r2], 'A');
  await label(page, shoot.id, regular.id, [r3], 'B');
  await label(page, shoot.id, regular.id, [r4], 'C');
  await label(page, shoot.id, staff.id, [s1], 'A');
  await assign(page, 'teacher@example.invalid', 'teacher', institution.id, regular.id);
  await assign(page, 'curator@example.invalid', 'curator', institution.id);
  // Earlier specs may leave the global gift policy invalid (two active bundles); D3 prices must not depend on it.
  await withoutGifts(page, regular.id);
  await withoutGifts(page, staff.id);

  const gallery = '/api/v1/public/galleries/' + (await openGallery(page, regular.id));
  expect((await prepare(page, staff.id)).prepared).toBe(true);
  const product = (await body(await page.request.get(gallery + '/catalog'))).data.products.find(
    (item: { name: string }) => item.name === 'D3 Печать ' + suffix
  );
  const assignmentOf = async (path: string, photoId: string) =>
    (await body(await page.request.get(path))).data.children
      .flatMap((child: { photos: { id: string; assignmentId: string }[] }) => child.photos)
      .find((photo: { id: string }) => photo.id === photoId).assignmentId as string;
  const buy = async (path: string, photoId: string) => {
    const lines = [{ assignmentId: await assignmentOf(path, photoId), productId: product.id, quantity: 1 }];
    const priced = (await body(await page.request.post(path + '/quotes', { data: { lines } }))).data;
    const idempotencyKey = key();
    const created = await body(
      await page.request.post(path + '/orders', {
        headers: { 'Idempotency-Key': idempotencyKey },
        data: { lines, buyer, quoteToken: priced.quoteToken }
      }),
      201
    );
    rememberOrder(created.data, idempotencyKey);
    return created;
  };
  const order = (await buy(gallery, r1)).data;
  const cartLines = [{ assignmentId: await assignmentOf(gallery, r2), productId: product.id, quantity: 1 }];
  const cart = (await body(await page.request.post(gallery + '/quotes', { data: { lines: cartLines } }))).data;
  const orderBefore = (await body(await page.request.get('/api/v1/orders/' + order.id, { headers: await auth(page) }))).data;

  const request = await asStaff(browser, baseURL, 'teacher', async (teacher) => {
    const created = await submitRequest(teacher, institution.id, shoot.id, [
      { groupId: regular.id, code: 'A001' },
      { groupId: regular.id, code: 'C' }
    ]);
    expect((await preview(teacher, created.id, 403)).error.code).toBe('FORBIDDEN');
    return created;
  });

  const curatorContext = await browser.newContext({ baseURL });
  try {
    const curator = await curatorContext.newPage();
    await curator.setViewportSize({ width: 1440, height: 1000 });
    await login(curator, 'curator');
    const planned = (await preview(curator, request.id)).data;
    expect(planned).toMatchObject({ targetGroupId: staff.id, targetGroupName: 'D3 Сотрудники', hasOrders: true, revision: 1 });
    expect(planned.signature).toMatch(/^[a-f0-9]{64}$/);
    expect(
      planned.bundles.map((bundle: { childCode: string; targetCode: string; hasOrders: boolean; photos: { id: string }[] }) => [
        bundle.childCode,
        bundle.targetCode,
        bundle.hasOrders,
        bundle.photos.map((photo) => photo.id)
      ])
    ).toEqual([
      ['A', 'B', true, [r1, r2]],
      ['C', 'C', false, [r4]]
    ]);
    // An order placed after the preview changes the checked state: the old signature transfers nothing.
    await buy(gallery, r4);
    expect(
      (await confirmTransfer(curator, request.id, { reason: '', confirmed: true, revision: 1, signature: planned.signature }, 409)).error
        .code
    ).toBe('SIGNATURE_CONFLICT');
    expect(
      (await confirmTransfer(curator, request.id, { reason: '', confirmed: false, revision: 1, signature: planned.signature }, 422)).error
        .code
    ).toBe('CONFIRMATION_REQUIRED');

    await curator.goto('/cabinet/staff-requests/' + request.id);
    await expect(curator.getByTestId('request-detail')).toBeVisible();
    await curator.getByRole('button', { name: 'Проверить и перенести', exact: true }).click();
    const dialog = curator.getByTestId('admin-dialog');
    await expect(dialog.getByTestId('transfer-orders')).toBeVisible();
    await expect(dialog.getByTestId('transfer-bundle')).toHaveCount(2);
    await expect(dialog).toContainText('D3 Сотрудники');
    await expect(dialog.getByTestId('transfer-bundle').first()).toContainText('новый код ребёнка: B');
    await expect(dialog.getByTestId('transfer-bundle').last()).toContainText('есть заказы, они сохранятся');
    // Staff previews are organizer-only: the curator checks frame codes.
    await expect(dialog.locator('.handoff-previews img')).toHaveCount(0);
    await expect(dialog.getByTestId('transfer-bundle').first()).toContainText('A002');
    await curator.screenshot({ path: testInfo.outputPath('d3-desktop-staff-transfer.png'), fullPage: true, animations: 'disabled' });

    // #79: a refresh whose transfer preview fails keeps the confirmation and says so inside the dialog.
    await dialog.getByRole('button', { name: 'Отмена', exact: true }).click();
    await curator.getByRole('button', { name: 'Проверить и перенести', exact: true }).click();
    await expect(dialog.getByText('Восстановлен несохранённый черновик.', { exact: true })).toBeVisible();
    const previewPath = '/api/v1/staff-requests/' + request.id + '/transfer-preview';
    let previewBroken = false;
    await curator.route(
      (url) => url.pathname === previewPath,
      (route) => {
        if (previewBroken) return route.fallback();
        previewBroken = true;
        return route.abort('failed');
      }
    );
    const refresh = dialog.getByRole('button', { name: 'Загрузить актуальные данные', exact: true });
    await refresh.click();
    await expect(
      dialog.getByText('Не удалось загрузить актуальные данные. Проверьте соединение и повторите.', { exact: true })
    ).toBeVisible();
    await expect(dialog.getByRole('button', { name: 'Подтвердить перенос', exact: true })).toBeVisible();
    await expect(dialog.getByTestId('transfer-orders')).toHaveCount(0);
    expect(previewBroken).toBe(true);
    await refresh.click();
    await expect(dialog.getByTestId('transfer-orders')).toBeVisible();
    await expect(
      dialog.getByText('Не удалось загрузить актуальные данные. Проверьте соединение и повторите.', { exact: true })
    ).toHaveCount(0);
    await dialog.getByLabel('Проверены все кадры, подтверждаю перенос наборов', { exact: true }).check();
    const confirmed = curator.waitForResponse((r) => r.url().endsWith('/transfers') && r.request().method() === 'POST');
    await dialog.getByRole('button', { name: 'Подтвердить перенос', exact: true }).click();
    const transferred = (await body(await confirmed)).data as { id: string; revision: number; status: string; results: Result[] };
    expect(transferred).toMatchObject({ id: request.id, revision: 2, status: 'transferred' });
    expect(transferred.results.map((result) => [result.fromChildCode, result.targetChildCode, result.photoIds])).toEqual([
      ['A', 'B', [r1, r2]],
      ['C', 'C', [r4]]
    ]);
    await expect(curator.getByTestId('request-detail')).toContainText('Перенесено 2 фото · новый код B');

    // Any later confirmation returns the recorded result without a second move.
    expect(
      (await confirmTransfer(curator, request.id, { reason: 'Повтор', confirmed: true, revision: 1, signature: planned.signature })).data
    ).toEqual(transferred);
    const detail = (await body(await curator.request.get('/api/v1/staff-requests/' + request.id, { headers: await auth(curator) }))).data;
    expect(detail.results).toEqual(transferred.results);
    expect(detail.rows.map((row: { childCode: string }) => row.childCode)).toEqual(['A', 'C']);
    expect(detail.history.map((event: { kind: string }) => event.kind)).toEqual(['submitted', 'transferred']);
    expect((await preview(curator, request.id, 409)).error.code).toBe('REQUEST_TRANSFERRED');
    await asStaff(browser, baseURL, 'teacher', async (teacher) => {
      const edit = await teacher.request.put('/api/v1/staff-requests/' + request.id, {
        headers: await auth(teacher),
        data: {
          institutionId: institution.id,
          shootId: shoot.id,
          rows: [{ id: crypto.randomUUID(), groupId: regular.id, code: 'B' }],
          comment: '',
          revision: 2
        }
      });
      expect((await body(edit, 409)).error.code).toBe('REQUEST_TRANSFERRED');
    });

    expect((await body(await page.request.get('/api/v1/photos/' + r1, { headers: await auth(page) }))).data).toMatchObject({
      groupId: staff.id,
      originalGroupId: regular.id,
      childCode: 'B',
      code: 'B001'
    });
    const orderAfter = (await body(await page.request.get('/api/v1/orders/' + order.id, { headers: await auth(page) }))).data;
    expect([orderAfter.quote, orderAfter.version, orderAfter.paymentStatus]).toEqual([
      orderBefore.quote,
      orderBefore.version,
      orderBefore.paymentStatus
    ]);
    expect(orderAfter.correctionPhotos.map((photo: { photoId: string; code: string }) => [photo.photoId, photo.code])).toEqual([
      [r1, 'B001'],
      [r2, 'B002']
    ]);
    const staleCart = await page.request.post(gallery + '/orders', {
      headers: { 'Idempotency-Key': key() },
      data: { lines: cartLines, buyer, quoteToken: cart.quoteToken }
    });
    expect([409, 422]).toContain(staleCart.status());
    expect(['INVALID_CART', 'QUOTE_STALE']).toContain((await staleCart.json()).error.code);
    // Labeling and cover stay locked in the handed-over group with orders.
    const lockedRevision = (await media(page, shoot.id)).revision;
    expect(
      (
        await body(
          await page.request.post('/api/v1/groups/' + regular.id + '/photo-assignments', {
            headers: await auth(page),
            data: { shootId: shoot.id, revision: lockedRevision, photoIds: [r3], childCode: 'Z' }
          }),
          409
        )
      ).error.code
    ).toBe('GROUP_LOCKED');
    expect(
      (
        await body(
          await page.request.put('/api/v1/groups/' + regular.id + '/cover', {
            headers: await auth(page),
            data: { revision: lockedRevision, photoId: r3 }
          }),
          409
        )
      ).error.code
    ).toBe('GROUP_LOCKED');

    // The transfer reset the staff group preparation: the link needs a new check before the handover.
    const staffLink = await link(page, staff.id);
    expect(staffLink.prepared).toBe(false);
    expect(
      (
        await body(
          await page.request.post('/api/v1/groups/' + staff.id + '/link-transmissions', {
            headers: await auth(page),
            data: { revision: staffLink.revision, signature: staffLink.signature, sentAt: moscowMinute(), confirmed: true }
          }),
          409
        )
      ).error.code
    ).toBe('LINK_NOT_PREPARED');
    const staffGallery = '/api/v1/public/galleries/' + (await openGallery(page, staff.id));
    const staffLine = [{ assignmentId: await assignmentOf(staffGallery, r1), productId: product.id, quantity: 1 }];
    const staffQuote = (await body(await page.request.post(staffGallery + '/quotes', { data: { lines: staffLine } }))).data;
    expect(staffQuote.quote.total).toBe(7500);
    const directLine = [{ assignmentId: await assignmentOf(staffGallery, s1), productId: product.id, quantity: 1 }];
    expect((await body(await page.request.post(staffGallery + '/quotes', { data: { lines: directLine } }), 403)).error.code).toBe(
      'STAFF_ELIGIBILITY_REQUIRED'
    );

    await curator.setViewportSize({ width: 390, height: 844 });
    await curator.reload();
    await expect(curator.getByTestId('request-detail')).toContainText('Проверен и перенесён');
    expect(await curator.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    await curator.screenshot({ path: testInfo.outputPath('d3-mobile-staff-transfer.png'), fullPage: true, animations: 'disabled' });
  } finally {
    await curatorContext.close();
  }
});

test('D3: параллельные подтверждения переносят набор один раз, неоднозначная цель отклоняется', async ({ page, browser, baseURL }) => {
  test.setTimeout(240000);
  await login(page);
  const suffix = crypto.randomUUID().slice(0, 8);
  const institution = await create(page, '/api/v1/institutions', { name: 'D3 Параллельный сад ' + suffix, address: 'Москва' });
  const shoot = await create(page, '/api/v1/institutions/' + institution.id + '/shoots', { name: 'D3 Зимняя съёмка', date: '2026-10-24' });
  const regular = await create(page, '/api/v1/shoots/' + shoot.id + '/groups', { name: 'D3 Лучики', groupKind: 'regular' });
  const staff = await create(page, '/api/v1/shoots/' + shoot.id + '/groups', { name: 'D3 Сотрудники', groupKind: 'staff' });
  const [p1, p2] = await upload(page, shoot.id, [
    [regular.id, 21],
    [regular.id, 22]
  ]);
  await label(page, shoot.id, regular.id, [p1], 'A');
  await label(page, shoot.id, regular.id, [p2], 'B');
  const other = await create(page, '/api/v1/institutions/' + institution.id + '/shoots', { name: 'D3 Две папки', date: '2026-10-25' });
  const otherRegular = await create(page, '/api/v1/shoots/' + other.id + '/groups', { name: 'D3 Капельки', groupKind: 'regular' });
  await create(page, '/api/v1/shoots/' + other.id + '/groups', { name: 'D3 Сотрудники 1', groupKind: 'staff' });
  await create(page, '/api/v1/shoots/' + other.id + '/groups', { name: 'D3 Сотрудники 2', groupKind: 'staff' });
  const [q1] = await upload(page, other.id, [[otherRegular.id, 23]]);
  await label(page, other.id, otherRegular.id, [q1], 'A');
  await assign(page, 'teacher@example.invalid', 'teacher', institution.id, regular.id);
  await assign(page, 'teacher@example.invalid', 'teacher', institution.id, otherRegular.id);
  await assign(page, 'curator@example.invalid', 'curator', institution.id);
  const [first, second, ambiguous] = await asStaff(browser, baseURL, 'teacher', async (teacher) => [
    await submitRequest(teacher, institution.id, shoot.id, [{ groupId: regular.id, code: 'A' }]),
    await submitRequest(teacher, institution.id, shoot.id, [{ groupId: regular.id, code: 'B' }]),
    await submitRequest(teacher, institution.id, other.id, [{ groupId: otherRegular.id, code: 'A' }])
  ]);

  await asStaff(browser, baseURL, 'curator', async (curator) => {
    expect((await preview(curator, ambiguous.id, 409)).error.code).toBe('STAFF_GROUP_AMBIGUOUS');
    const planned = (await preview(curator, first.id)).data;
    const data = { reason: 'Параллельная проверка', confirmed: true, revision: 1, signature: planned.signature };
    const keys = [key(), key(), key(), key()];
    const answers = await Promise.all(keys.map((value) => confirmTransfer(curator, first.id, data, 200, value)));
    for (const answer of answers) expect(answer.data).toEqual(answers[0].data);
    expect(answers[0].data.results).toMatchObject([{ fromChildCode: 'A', targetChildCode: 'A', targetGroupId: staff.id, photoIds: [p1] }]);
    const detail = (await body(await curator.request.get('/api/v1/staff-requests/' + first.id, { headers: await auth(curator) }))).data;
    expect(detail.history.map((event: { kind: string }) => event.kind)).toEqual(['submitted', 'transferred']);
    expect((await confirmTransfer(curator, first.id, data, 200, keys[0])).data).toEqual(answers[0].data);
    expect((await confirmTransfer(curator, first.id, { ...data, reason: 'Другое тело' }, 409, keys[0])).error.code).toBe(
      'IDEMPOTENCY_CONFLICT'
    );
  });
  expect((await body(await page.request.get('/api/v1/photos/' + p1, { headers: await auth(page) }))).data).toMatchObject({
    groupId: staff.id,
    originalGroupId: regular.id
  });
  // The MySQL verifier injects a failure into the second request and then confirms it for real.
  mkdirSync('var', { recursive: true });
  writeFileSync(
    'var/d3-transfers.json',
    JSON.stringify({ requestId: second.id, shootId: shoot.id, regularId: regular.id, staffId: staff.id, photoId: p2 }) + '\n'
  );
});

/** Holds matching requests until the test decides to let them through or to break the connection. */
async function hold(page: Page, match: (url: URL) => boolean) {
  let arrive!: () => void;
  let decide!: (action: 'continue' | 'abort') => void;
  const reached = new Promise<void>((resolve) => (arrive = resolve));
  const decided = new Promise<'continue' | 'abort'>((resolve) => (decide = resolve));
  const handler = async (route: Route) => {
    arrive();
    if ((await decided) === 'abort') await route.abort('failed');
    else await route.continue();
  };
  await page.route(match, handler);
  return { reached, release: decide, stop: () => page.unroute(match, handler) };
}

test('#62/#63: смена группы не показывает кадры прежней группы и не смешивает контекст переноса', async ({ page }, testInfo) => {
  test.setTimeout(120000);
  await page.setViewportSize({ width: 1440, height: 1000 });
  await login(page);
  const suffix = crypto.randomUUID().slice(0, 8);
  const institution = await create(page, '/api/v1/institutions', {
    name: '#62 Детский сад ' + suffix,
    address: 'Москва'
  });
  const shoot = await create(page, '/api/v1/institutions/' + institution.id + '/shoots', { name: '#62 Съёмка', date: '2026-10-23' });
  const from = await create(page, '/api/v1/shoots/' + shoot.id + '/groups', {
    name: '#62 Ромашки',
    groupKind: 'regular'
  });
  const other = await create(page, '/api/v1/shoots/' + shoot.id + '/groups', {
    name: '#62 Солнышко',
    groupKind: 'regular'
  });
  const [a1, a2] = await upload(page, shoot.id, [
    [from.id, 21],
    [from.id, 22],
    [other.id, 23]
  ]);
  await label(page, shoot.id, from.id, [a1, a2], 'A');
  await body(
    await page.request.put('/api/v1/groups/' + from.id + '/cover', {
      headers: await auth(page),
      data: { revision: (await media(page, shoot.id)).revision, photoId: a1 }
    })
  );
  const list = (groupId: string, childCode?: string) => (url: URL) =>
    url.pathname === '/api/v1/shoots/' + shoot.id + '/photos' &&
    url.searchParams.get('groupId') === groupId &&
    (!childCode || (url.searchParams.get('childCode') === childCode && url.searchParams.get('pageSize') === '100'));
  // The group select has no accessible name, so it is opened through its test id like the frames filter.
  const groupSelect = page.getByTestId('photo-group');
  const chooseGroup = async (name: string) => {
    await groupSelect.click();
    await page.getByRole('option', { name: name + ' · Подготовка', exact: true }).click();
  };
  const cards = page.getByTestId('photo-card');
  const readiness = page.getByTestId('photo-readiness');
  const coverChosen = page.getByText('Обложка группы выбрана', { exact: true });

  await page.goto('/cabinet/institutions/' + institution.id + '/shoots/' + shoot.id + '/photos?group=' + from.id);
  await expect(cards).toHaveCount(2);
  await expect(coverChosen).toBeVisible();

  // #62: while the new group loads and after its load fails, nothing of the previous group is shown under its name.
  const otherList = await hold(page, list(other.id));
  await chooseGroup('#62 Солнышко');
  await otherList.reached;
  await expect(page.getByRole('heading', { name: '#62 Солнышко', exact: true })).toBeVisible();
  await expect(readiness).toHaveText('Загружаем кадры группы…');
  await expect(cards).toHaveCount(0);
  await expect(coverChosen).toHaveCount(0);
  otherList.release('abort');
  await expect(readiness).toHaveText('Кадры группы не загружены.');
  await expect(page.getByRole('alert').filter({ hasText: 'Сервер не завершил операцию. Повторите попытку.' })).toBeVisible();
  await expect(cards).toHaveCount(0);
  await expect(page.getByAltText('Обложка группы', { exact: true })).toHaveCount(0);
  await page.screenshot({
    path: testInfo.outputPath('issue62-failed-group.png'),
    fullPage: true,
    animations: 'disabled'
  });
  await otherList.stop();
  await chooseGroup('#62 Ромашки');
  await expect(cards).toHaveCount(2);
  await expect(readiness).toHaveText('Кадров: 2 · Детей: 1 · Без ребёнка: 0');
  await expect(coverChosen).toBeVisible();

  // #63: the set is loaded for a fixed group: the selector is locked, a failed load opens no dialog, a retry works.
  await page.getByTestId('photo-filter').click();
  await page.getByRole('option', { name: 'Ребёнок A', exact: true }).click();
  const moveButton = page.getByRole('button', {
    name: 'Перенести весь набор',
    exact: true
  });
  const failedSet = await hold(page, list(from.id, 'A'));
  await moveButton.click();
  await failedSet.reached;
  await expect(groupSelect.locator('input')).toBeDisabled();
  failedSet.release('abort');
  await expect(page.getByRole('alert').filter({ hasText: 'Сервер не завершил операцию. Повторите попытку.' })).toBeVisible();
  await expect(page.getByRole('dialog')).toHaveCount(0);
  await expect(groupSelect.locator('input')).toBeEnabled();
  await failedSet.stop();
  const slowSet = await hold(page, list(from.id, 'A'));
  await moveButton.click();
  await slowSet.reached;
  await expect(groupSelect.locator('input')).toBeDisabled();
  slowSet.release('continue');
  const dialog = page.getByRole('dialog');
  await expect(
    dialog.getByRole('heading', {
      name: 'Перенести набор ребёнка A',
      exact: true
    })
  ).toBeVisible();
  await expect(dialog).toContainText('Из группы «#62 Ромашки» будет перенесён весь набор. Кадров в наборе: 2.');
  await slowSet.stop();
  await dialog.getByRole('button', { name: 'Отмена', exact: true }).click();
  await expect(dialog).toHaveCount(0);
});
