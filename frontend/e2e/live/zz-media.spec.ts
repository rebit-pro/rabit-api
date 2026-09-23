import { test, expect, type APIResponse, type Page, type Request, type Route } from '@playwright/test';
import { login, logout, token } from './helpers.js';

const png = Buffer.from(
  'iVBORw0KGgoAAAANSUhEUgAAAUAAAADICAIAAAAWZq/8AAABvElEQVR42u3TQQ0AMAgAsTE1CEMiAhHBi6SVcMlFVj/gpi8BGBgwMGBgMDBgYMDAgIHBwICBAQODgQEDAwYGDAwGBgwMGBgwMBgYMDBgYDAwYGDAwICBwcCAgQEDAwYGAwMGBgwMBgYMDBgYMDAYGDAwYGAwMGBgwMCAgcHAgIEBAwMGBgMDBgYMDAYGDAwYGDAwGBgwMGBgwMBgYMDAgIHBwICBAQMDBgYDAwYGDAwYGAwMGBgwMBgYMDBgYMDAYGDAwICBwcCAgQEDAwYGAwMGBgwMGBgMDBgYMDAYGDAwYGDAwGBgwMCAgQEDg4EBAwMGBgMDBgYMDBgYDAwYGDAwYGAwMGBgwMBgYMDAgIEBA4OBAQMDBgYDAwYGDAwYGAwMGBgwMGBgMDBgYMDAYGDAwICBAQODgQEDAwYGDAwGBgwMGBgMDBgYMDBgYDAwYGDAwGBgwMCAgQEDg4EBAwMGBgwMBgYMDBgYDAwYGDAwYGAwMGBgwMCAgcHAgIEBA4OBAQMDBgYMDAYGDAwYGDAwGBgwMGBgMDBgYMDAgIHBwICBAQODgQEDAwYGDAwGBgwMGBgwMBgYMDCwMUuEAtA7HouzAAAAAElFTkSuQmCC',
  'base64'
);
const problems = new WeakMap<Page, string[]>();

function crc32(bytes: Buffer): number {
  let crc = 0xffffffff;
  for (const byte of bytes) {
    crc ^= byte;
    for (let bit = 0; bit < 8; bit++) crc = (crc >>> 1) ^ (0xedb88320 & -(crc & 1));
  }
  return (crc ^ 0xffffffff) >>> 0;
}

// A tEXt chunk before IEND gives a valid PNG with its own SHA-256, so the server does not treat it as a duplicate.
function pngVariant(label: string): Buffer {
  const type = Buffer.from('tEXt', 'latin1');
  const text = Buffer.from('Comment\0' + label, 'latin1');
  const length = Buffer.alloc(4);
  length.writeUInt32BE(text.length);
  const checksum = Buffer.alloc(4);
  checksum.writeUInt32BE(crc32(Buffer.concat([type, text])));
  const end = png.length - 12;
  return Buffer.concat([png.subarray(0, end), length, type, text, checksum, png.subarray(end)]);
}

// Peak number of requests in flight, rebuilt from browser network timings: Playwright delivers request events in its own order.
function peakOverlap(spans: { start: number; end: number }[]): number {
  const edges = spans.flatMap(({ start, end }) => [
    { at: start, delta: 1 },
    { at: end, delta: -1 }
  ]);
  edges.sort((left, right) => left.at - right.at || left.delta - right.delta);
  let current = 0;

  return edges.reduce((peak, edge) => Math.max(peak, (current += edge.delta)), 0);
}

function span(request: Request): { start: number; end: number } {
  const timing = request.timing();

  return { start: timing.startTime, end: timing.startTime + Math.max(timing.responseEnd, 0) };
}

async function result(response: APIResponse, status: number) {
  expect(response.status(), await response.text()).toBe(status);
  return response.json();
}

async function headers(page: Page) {
  return {
    Authorization: 'Bearer ' + (await token(page)),
    'Idempotency-Key': crypto.randomUUID().replace(/-/g, '')
  };
}

test.beforeEach(async ({ page, request }) => {
  expect(await (await request.get('/__e2e')).json()).toEqual({ fixture: 'rabit-real-e2e' });
  const errors: string[] = [];
  problems.set(page, errors);
  page.on('pageerror', (error) => errors.push(error.message));
  page.on('console', (message) => {
    if (message.type() === 'error' && !message.text().startsWith('Failed to load resource:')) errors.push(message.text());
  });
  page.on('response', (response) => {
    const path = new URL(response.url()).pathname;
    if (path.startsWith('/api/') && response.status() >= 500) errors.push(response.status() + ' ' + path);
  });
});

test.afterEach(async ({ page }) => {
  expect(problems.get(page)).toEqual([]);
  expect(await page.evaluate(() => '__MOREFOTO_MOCKS__' in window)).toBe(false);
  expect(await page.evaluate(() => Object.keys(localStorage).some((key) => key.startsWith('morefoto:demo:')))).toBe(false);
});

test('D3: ошибочный ответ списка кадров показывает понятное сообщение', async ({ page }) => {
  await login(page);
  const institution = (
    await result(
      await page.request.post('/api/v1/institutions', {
        headers: await headers(page),
        data: { name: 'D3 Детский сад ' + crypto.randomUUID().slice(0, 8), address: 'Москва' }
      }),
      201
    )
  ).data;
  const shoot = (
    await result(
      await page.request.post('/api/v1/institutions/' + institution.id + '/shoots', {
        headers: await headers(page),
        data: { name: 'D3 Съёмка', date: '2026-10-20' }
      }),
      201
    )
  ).data;
  await result(
    await page.request.post('/api/v1/shoots/' + shoot.id + '/groups', {
      headers: await headers(page),
      data: { name: 'D3 Группа', groupKind: 'regular' }
    }),
    201
  );
  await page.route('**/api/v1/shoots/' + shoot.id + '/photos?*', async (route) => {
    await route.fulfill({ status: 200, json: { status: 'error', data: null, errors: [{ message: 'Internal error' }] } });
  });
  await page.goto('/cabinet/institutions/' + institution.id + '/shoots/' + shoot.id + '/photos');
  await expect(page.getByText('Не удалось загрузить список кадров. Повторите попытку.', { exact: true })).toBeVisible();
  await expect(page.getByText("Cannot read properties of null (reading 'items')")).toHaveCount(0);
});

test('D1/D2: приватное фото получает M:N-разметку и обложку без потери атомарности', async ({ page, browser, baseURL }, testInfo) => {
  test.setTimeout(120000);
  await page.setViewportSize({ width: 1440, height: 1000 });
  await login(page);
  const suffix = crypto.randomUUID().slice(0, 8);
  const institution = (
    await result(
      await page.request.post('/api/v1/institutions', {
        headers: await headers(page),
        data: { name: 'D1 Детский сад ' + suffix, address: 'Москва' }
      }),
      201
    )
  ).data;
  const shoot = (
    await result(
      await page.request.post('/api/v1/institutions/' + institution.id + '/shoots', {
        headers: await headers(page),
        data: { name: 'D1 Приватная съёмка', date: '2026-10-20' }
      }),
      201
    )
  ).data;
  const group = (
    await result(
      await page.request.post('/api/v1/shoots/' + shoot.id + '/groups', {
        headers: await headers(page),
        data: { name: 'D1 Ромашки', groupKind: 'regular' }
      }),
      201
    )
  ).data;

  await page.goto('/cabinet/institutions/' + institution.id + '/shoots/' + shoot.id);
  await page.getByRole('link', { name: 'Фотографии', exact: true }).click();
  await expect(page).toHaveURL(new RegExp('/shoots/' + shoot.id + '/photos'));

  await expect(page.getByText('D1 Ромашки', { exact: true })).toBeVisible();
  await page
    .locator('input[type="file"][aria-label="Выбрать фотографии"]')
    .setInputFiles({ name: 'portrait.png', mimeType: 'image/png', buffer: png });
  await page.getByRole('button', { name: 'Загрузить на сервер', exact: true }).click();
  const first = page.locator('[data-upload-id]').filter({ hasText: 'portrait.png' }).first();
  await expect(first).toContainText('Готово', { timeout: 30000 });
  await expect(first).toContainText('Защищённые превью готовы');
  await expect(page.getByTestId('photo-card')).toHaveCount(1);

  const listing = await result(
    await page.request.get('/api/v1/shoots/' + shoot.id + '/photos', {
      headers: { Authorization: 'Bearer ' + (await token(page)) }
    }),
    200
  );
  expect(listing.data.items).toHaveLength(1);
  const photo = listing.data.items[0];
  expect(photo).not.toHaveProperty('originalPath');
  expect(photo.status).toBe('ready');
  expect(photo.groupId).toBe(group.id);
  const anonymousPreview = await page.request.get(photo.previewSrc);
  expect(anonymousPreview.status()).toBe(401);
  const preview = await page.request.get(photo.previewSrc, {
    headers: { Authorization: 'Bearer ' + (await token(page)) }
  });
  expect(preview.status()).toBe(200);
  expect(preview.headers()['content-type']).toContain('image/webp');
  await page.screenshot({ path: testInfo.outputPath('d1-desktop-media.png'), fullPage: true });

  await page.getByTestId('photo-card').getByRole('checkbox').check();
  let assignmentRequests = 0;
  page.on('request', (request) => {
    if (request.method() === 'POST' && new URL(request.url()).pathname.endsWith('/photo-assignments')) assignmentRequests++;
  });
  await page.getByLabel('Код ребёнка', { exact: true }).fill('A01');
  await expect(page.getByRole('button', { name: 'Назначить ребёнку', exact: true })).toBeDisabled();
  await expect(page.getByText('Код ребёнка — от 1 до 3 латинских букв.', { exact: false })).toBeVisible();
  expect(assignmentRequests).toBe(0);
  await page.screenshot({ path: testInfo.outputPath('d3-assignment-code-desktop.png'), fullPage: true });
  await page.setViewportSize({ width: 390, height: 844 });
  await expect(page.getByText('Код ребёнка — от 1 до 3 латинских букв.', { exact: false })).toBeVisible();
  await page.screenshot({ path: testInfo.outputPath('d3-assignment-code-mobile.png'), fullPage: true });
  await page.setViewportSize({ width: 1440, height: 1000 });
  await page.getByLabel('Код ребёнка', { exact: true }).fill('A');
  await page.getByRole('button', { name: 'Назначить ребёнку', exact: true }).click();
  expect(assignmentRequests).toBe(1);
  await expect(page.getByRole('status').filter({ hasText: 'Кадры назначены ребёнку.' })).toBeVisible();
  await expect(page.getByTestId('photo-card')).toContainText('A001');

  const afterA = await result(
    await page.request.get('/api/v1/shoots/' + shoot.id + '/photos', {
      headers: { Authorization: 'Bearer ' + (await token(page)) }
    }),
    200
  );
  expect(afterA.data.revision).toBe(2);
  expect(afterA.data.items[0].assignments).toMatchObject([{ childCode: 'A', sequence: 1, code: 'A001' }]);

  const assignmentKey = crypto.randomUUID().replace(/-/g, '');
  const assignmentHeaders = {
    Authorization: 'Bearer ' + (await token(page)),
    'Idempotency-Key': assignmentKey
  };
  const assignmentBody = { shootId: shoot.id, revision: 2, photoIds: [photo.id], childCode: 'B' };
  const assignedB = await result(
    await page.request.post('/api/v1/groups/' + group.id + '/photo-assignments', {
      headers: assignmentHeaders,
      data: assignmentBody
    }),
    200
  );
  expect(assignedB.data).toMatchObject({ photoIds: [photo.id], childCode: 'B', revision: 3 });
  expect(
    (
      await result(
        await page.request.post('/api/v1/groups/' + group.id + '/photo-assignments', {
          headers: assignmentHeaders,
          data: assignmentBody
        }),
        200
      )
    ).data
  ).toEqual(assignedB.data);
  expect(
    (
      await result(
        await page.request.post('/api/v1/groups/' + group.id + '/photo-assignments', {
          headers: assignmentHeaders,
          data: { ...assignmentBody, childCode: 'C' }
        }),
        409
      )
    ).error.code
  ).toBe('IDEMPOTENCY_CONFLICT');
  expect(
    (
      await result(
        await page.request.post('/api/v1/groups/' + group.id + '/photo-assignments', {
          headers: await headers(page),
          data: { ...assignmentBody, childCode: 'C' }
        }),
        409
      )
    ).error.code
  ).toBe('REVISION_CONFLICT');
  expect(
    (
      await result(
        await page.request.post('/api/v1/groups/' + group.id + '/photo-assignments', {
          headers: await headers(page),
          data: { ...assignmentBody, revision: 3, photoIds: [photo.id, photo.id] }
        }),
        422
      )
    ).error.code
  ).toBe('INVALID_PHOTO_IDS');

  const assignedA = await result(
    await page.request.get('/api/v1/shoots/' + shoot.id + '/photos?groupId=' + group.id + '&childCode=A', {
      headers: { Authorization: 'Bearer ' + (await token(page)) }
    }),
    200
  );
  const assignedToB = await result(
    await page.request.get('/api/v1/shoots/' + shoot.id + '/photos?groupId=' + group.id + '&childCode=B', {
      headers: { Authorization: 'Bearer ' + (await token(page)) }
    }),
    200
  );
  const unassigned = await result(
    await page.request.get('/api/v1/shoots/' + shoot.id + '/photos?groupId=' + group.id + '&assigned=false', {
      headers: { Authorization: 'Bearer ' + (await token(page)) }
    }),
    200
  );
  expect(assignedA.data.items).toHaveLength(1);
  expect(assignedToB.data.items).toHaveLength(1);
  expect(unassigned.data.items).toHaveLength(0);
  expect(assignedToB.data.items[0].assignments.map((item: { childCode: string }) => item.childCode)).toEqual(['A', 'B']);

  await expect(page.getByTestId('photo-card')).toContainText('A001');
  await page.getByRole('button', { name: 'Сделать обложкой', exact: true }).click();
  await expect(page.getByRole('alert').filter({ hasText: 'Список обновлён — повторите действие.' })).toBeVisible();
  await expect(page.getByTestId('photo-card')).toContainText('A001 · B001');
  await page.getByRole('button', { name: 'Сделать обложкой', exact: true }).click();
  await expect(page.getByRole('status').filter({ hasText: 'Обложка группы сохранена.' })).toBeVisible();
  await expect(page.getByTestId('photo-card')).toContainText('Обложка');

  const afterCover = await result(
    await page.request.get('/api/v1/shoots/' + shoot.id + '/photos', {
      headers: { Authorization: 'Bearer ' + (await token(page)) }
    }),
    200
  );
  expect(afterCover.data.revision).toBe(4);
  expect(afterCover.data.covers[group.id]).toBe(photo.id);
  const coverKey = crypto.randomUUID().replace(/-/g, '');
  const coverHeaders = { Authorization: 'Bearer ' + (await token(page)), 'Idempotency-Key': coverKey };
  const coverBody = { revision: 4, photoId: photo.id };
  const coverReplay = await result(
    await page.request.put('/api/v1/groups/' + group.id + '/cover', { headers: coverHeaders, data: coverBody }),
    200
  );
  expect(coverReplay.data).toEqual({ photoId: photo.id, revision: 4 });
  expect(
    (await result(await page.request.put('/api/v1/groups/' + group.id + '/cover', { headers: coverHeaders, data: coverBody }), 200)).data
  ).toEqual(coverReplay.data);
  expect(
    (
      await result(
        await page.request.put('/api/v1/groups/' + group.id + '/cover', {
          headers: coverHeaders,
          data: { ...coverBody, revision: 3 }
        }),
        409
      )
    ).error.code
  ).toBe('IDEMPOTENCY_CONFLICT');

  const foreignGroup = (
    await result(
      await page.request.post('/api/v1/shoots/' + shoot.id + '/groups', {
        headers: await headers(page),
        data: { name: 'D2 Васильки', groupKind: 'regular' }
      }),
      201
    )
  ).data;
  expect(
    (
      await result(
        await page.request.post('/api/v1/groups/' + foreignGroup.id + '/photo-assignments', {
          headers: await headers(page),
          data: { shootId: shoot.id, revision: 4, photoIds: [photo.id], childCode: 'C' }
        }),
        409
      )
    ).error.code
  ).toBe('PHOTO_NOT_ASSIGNABLE');
  await page.screenshot({ path: testInfo.outputPath('d2-desktop-assignments.png'), fullPage: true });

  await page
    .locator('input[type="file"][aria-label="Выбрать фотографии"]')
    .setInputFiles({ name: 'portrait-copy.png', mimeType: 'image/png', buffer: png });
  await page.getByRole('button', { name: 'Загрузить на сервер', exact: true }).click();
  await expect(page.locator('[data-upload-id]').filter({ hasText: 'portrait-copy.png' })).toContainText('Повтор файла');
  const afterDuplicate = await result(
    await page.request.get('/api/v1/shoots/' + shoot.id + '/photos', {
      headers: { Authorization: 'Bearer ' + (await token(page)) }
    }),
    200
  );
  expect(afterDuplicate.data.items).toHaveLength(2);
  expect(afterDuplicate.data.items.filter((item: { status: string }) => item.status === 'ready')).toHaveLength(1);
  expect(afterDuplicate.data.items.filter((item: { status: string }) => item.status === 'duplicate')).toHaveLength(1);

  const duplicate = afterDuplicate.data.items.find((item: { status: string }) => item.status === 'duplicate');
  expect(
    (
      await result(
        await page.request.post('/api/v1/groups/' + group.id + '/photo-assignments', {
          headers: await headers(page),
          data: { shootId: shoot.id, revision: 4, photoIds: [photo.id, duplicate.id], childCode: 'C' }
        }),
        409
      )
    ).error.code
  ).toBe('PHOTO_NOT_ASSIGNABLE');
  const afterRejectedBatch = await result(
    await page.request.get('/api/v1/shoots/' + shoot.id + '/photos?groupId=' + group.id + '&childCode=C', {
      headers: { Authorization: 'Bearer ' + (await token(page)) }
    }),
    200
  );
  expect(afterRejectedBatch.data.items).toHaveLength(0);

  await page.locator('input[type="file"][aria-label="Выбрать фотографии"]').setInputFiles({
    name: 'broken.png',
    mimeType: 'image/png',
    buffer: Buffer.from('not an image')
  });
  await page.getByRole('button', { name: 'Загрузить на сервер', exact: true }).click();
  await expect(page.locator('[data-upload-id]').filter({ hasText: 'broken.png' })).toContainText('Ошибка');
  await expect(page.locator('[data-upload-id]').filter({ hasText: 'broken.png' })).toContainText('Сервер отклонил файл');

  const teacher = await browser.newContext({ baseURL });
  try {
    const other = await teacher.newPage();
    await login(other, 'teacher');
    expect(
      (
        await other.request.get('/api/v1/shoots/' + shoot.id + '/photos', {
          headers: { Authorization: 'Bearer ' + (await token(other)) }
        })
      ).status()
    ).toBe(403);
    expect(
      (
        await other.request.post('/api/v1/groups/' + group.id + '/photo-assignments', {
          headers: await headers(other),
          data: { shootId: shoot.id, revision: 4, photoIds: [photo.id], childCode: 'C' }
        })
      ).status()
    ).toBe(403);
  } finally {
    await teacher.close();
  }

  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto('/cabinet/institutions/' + institution.id + '/shoots/' + shoot.id + '/photos?group=' + group.id);
  await expect(page.getByTestId('photo-card')).toHaveCount(1);
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
  await expect(page.getByTestId('photo-card')).toContainText('A001 · B001');
  await expect(page.getByTestId('photo-card')).toContainText('Обложка');
  await page.screenshot({ path: testInfo.outputPath('d2-mobile-assignments.png'), fullPage: true });
});

test('#33: партия отправляется по два файла без ожидания превью и переживает обновление страницы', async ({ page }) => {
  test.setTimeout(120000);
  await page.setViewportSize({ width: 1440, height: 1000 });
  await login(page);
  const suffix = crypto.randomUUID().slice(0, 8);
  const institution = (
    await result(
      await page.request.post('/api/v1/institutions', {
        headers: await headers(page),
        data: { name: 'Q33 Детский сад ' + suffix, address: 'Москва' }
      }),
      201
    )
  ).data;
  const shoot = (
    await result(
      await page.request.post('/api/v1/institutions/' + institution.id + '/shoots', {
        headers: await headers(page),
        data: { name: 'Q33 Съёмка', date: '2026-10-20' }
      }),
      201
    )
  ).data;
  const group = (
    await result(
      await page.request.post('/api/v1/shoots/' + shoot.id + '/groups', {
        headers: await headers(page),
        data: { name: 'Q33 Ромашки', groupKind: 'regular' }
      }),
      201
    )
  ).data;
  await page.goto('/cabinet/institutions/' + institution.id + '/shoots/' + shoot.id + '/photos?group=' + group.id);
  await expect(page.getByText('Q33 Ромашки', { exact: true })).toBeVisible();

  const uploads = '/api/v1/shoots/' + shoot.id + '/photos';
  const isUpload = (request: Request) => request.method() === 'POST' && new URL(request.url()).pathname === uploads;
  const spans: { start: number; end: number }[] = [];
  const finished: number[] = [];
  const checks: number[] = [];
  page.on('request', (request) => {
    if (!isUpload(request) && request.method() === 'GET' && /^\/api\/v1\/photos\/[0-9a-f-]{36}$/.test(new URL(request.url()).pathname))
      checks.push(Date.now());
  });
  const settle = (request: Request) => {
    if (!isUpload(request)) return;
    finished.push(Date.now());
    spans.push(span(request));
  };
  page.on('requestfinished', settle);
  page.on('requestfailed', settle);

  const input = page.locator('input[type="file"][aria-label="Выбрать фотографии"]');
  const files = [1, 2, 3, 4, 5].map((index) => ({
    name: 'batch-' + index + '.png',
    mimeType: 'image/png',
    buffer: pngVariant(suffix + '-' + index)
  }));
  await input.setInputFiles([
    ...files.slice(0, 2),
    { name: 'batch-broken.png', mimeType: 'image/png', buffer: Buffer.from('not an image') },
    ...files.slice(2)
  ]);
  await page.getByRole('button', { name: 'Загрузить на сервер', exact: true }).click();
  const rows = page.locator('[data-upload-id]');
  for (const file of files) await expect(rows.filter({ hasText: file.name })).toContainText('Готово', { timeout: 30000 });
  await expect(rows.filter({ hasText: 'batch-broken.png' })).toContainText('Ошибка');
  await expect(rows.filter({ hasText: 'batch-broken.png' })).toContainText('Сервер отклонил файл');
  expect(finished).toHaveLength(6);
  expect(peakOverlap(spans)).toBeLessThanOrEqual(2);
  // Statuses are checked from two seconds after acceptance, so more POSTs than the parallel limit end before the first check.
  expect(finished.filter((time) => time < Math.min(...checks)).length).toBeGreaterThanOrEqual(3);
  await expect(page.getByTestId('photo-card')).toHaveCount(5);

  await input.setInputFiles({ name: 'after-reload.png', mimeType: 'image/png', buffer: pngVariant(suffix + '-reload') });
  await page.getByRole('button', { name: 'Загрузить на сервер', exact: true }).click();
  await expect(rows.filter({ hasText: 'after-reload.png' })).toContainText('Обрабатывается');
  await expect(page.getByRole('button', { name: 'Пауза', exact: true })).toHaveCount(0);
  const sent = finished.length;
  await page.reload();
  await expect(page.locator('[data-upload-id]').filter({ hasText: 'after-reload.png' })).toContainText('Готово', { timeout: 30000 });
  expect(finished).toHaveLength(sent);
  await expect(page.getByTestId('photo-card')).toHaveCount(6);
});

test('#54/#55: большая группа открывается страницей, превью идут очередью и переживают сбой', async ({ page }, testInfo) => {
  test.setTimeout(240000);
  await page.setViewportSize({ width: 1440, height: 1000 });
  await login(page);
  const suffix = crypto.randomUUID().slice(0, 8);
  const institution = (
    await result(
      await page.request.post('/api/v1/institutions', {
        headers: await headers(page),
        data: { name: 'Q54 Детский сад ' + suffix, address: 'Москва' }
      }),
      201
    )
  ).data;
  const shoot = (
    await result(
      await page.request.post('/api/v1/institutions/' + institution.id + '/shoots', {
        headers: await headers(page),
        data: { name: 'Q54 Съёмка', date: '2026-10-20' }
      }),
      201
    )
  ).data;
  const group = (
    await result(
      await page.request.post('/api/v1/shoots/' + shoot.id + '/groups', {
        headers: await headers(page),
        data: { name: 'Q54 Ромашки', groupKind: 'regular' }
      }),
      201
    )
  ).data;
  const listing = '/api/v1/shoots/' + shoot.id + '/photos';
  const authorization = { Authorization: 'Bearer ' + (await token(page)) };
  // 50 frames: a full page of 48 and a second page of two.
  for (let first = 0; first < 50; first += 5)
    await Promise.all(
      Array.from({ length: 5 }, async (_, offset) =>
        result(
          await page.request.post(listing, {
            headers: authorization,
            multipart: {
              groupId: group.id,
              file: { name: 'q54-' + (first + offset) + '.png', mimeType: 'image/png', buffer: pngVariant(suffix + '-' + (first + offset)) }
            }
          }),
          202
        )
      )
    );
  await expect
    .poll(
      async () =>
        (await result(await page.request.get(listing + '?status=ready&pageSize=1&groupId=' + group.id, { headers: authorization }), 200))
          .data.meta.total,
      { timeout: 120000 }
    )
    .toBe(50);

  const lists: URL[] = [];
  const thumbs: string[] = [];
  const thumbPath = /^\/api\/v1\/photos\/[0-9a-f-]{36}\/thumb$/;
  const isThumb = (request: Request) => thumbPath.test(new URL(request.url()).pathname);
  page.on('request', (request) => {
    const url = new URL(request.url());
    if (request.method() === 'GET' && url.pathname === listing) lists.push(url);
  });
  const settle = (request: Request) => {
    if (isThumb(request)) thumbs.push(request.url());
  };
  page.on('requestfinished', settle);
  page.on('requestfailed', settle);
  // One network failure of the first preview must be recovered by the automatic retry, without «Повторить».
  let broken = '';
  await page.route(
    (url) => thumbPath.test(url.pathname),
    async (route) => {
      if (broken) return route.continue();
      broken = route.request().url();
      await route.abort('failed');
    }
  );
  const cards = page.getByTestId('photo-card');
  // Frames load only near the viewport, so the list is walked the way a person scrolls it.
  const showAll = async (count: number) => {
    for (let index = 0; index < count; index++) {
      const image = cards.nth(index).locator('img');
      await image.scrollIntoViewIfNeeded();
      await expect.poll(() => image.evaluate((node) => (node as HTMLImageElement).naturalWidth), { timeout: 30000 }).toBeGreaterThan(0);
    }
  };
  const pageButton = (number: number) =>
    page.getByTestId('photo-pagination').getByRole('button', { name: 'Перейти на страницу ' + number, exact: true });

  await page.addInitScript(() => performance.setResourceTimingBufferSize(1000));
  await page.goto('/cabinet/institutions/' + institution.id + '/shoots/' + shoot.id + '/photos?group=' + group.id);
  await expect(page.getByTestId('photo-page-status')).toHaveText('Показано 48 из 50');
  await expect(cards).toHaveCount(48);
  await expect(page.getByTestId('photo-readiness')).toHaveText('Кадров: 50 · Детей: 0 · Без ребёнка: 50');
  expect(lists).toHaveLength(1);
  expect(Object.fromEntries(lists[0]!.searchParams)).toEqual({ groupId: group.id, status: 'ready', page: '1', pageSize: '48' });
  await showAll(48);
  await expect(page.getByText('Кадр не загрузился', { exact: true })).toHaveCount(0);
  expect(thumbs.filter((url) => url === broken)).toHaveLength(2);
  // Resource Timing keeps start and end on one sub-millisecond clock of the page: the queue starts the next
  // preview right after the previous one ends, and Playwright's millisecond start times would overlap them.
  const previewSpans = await page.evaluate(() =>
    (performance.getEntriesByType('resource') as PerformanceResourceTiming[])
      .filter((entry) => /^\/api\/v1\/photos\/[0-9a-f-]{36}\/thumb$/.test(new URL(entry.name).pathname) && entry.responseEnd > 0)
      .map((entry) => ({ start: entry.startTime, end: entry.responseEnd }))
  );
  expect(previewSpans.length).toBeGreaterThanOrEqual(48);
  expect(peakOverlap(previewSpans)).toBeLessThanOrEqual(6);
  await page.screenshot({ path: testInfo.outputPath('q54-desktop-photos.png'), fullPage: true, animations: 'disabled' });

  await pageButton(2).click();
  await expect(page).toHaveURL(/[?&]page=2(&|$)/);
  await expect(cards).toHaveCount(2);
  await expect(page.getByTestId('photo-page-status')).toHaveText('Показано 2 из 50');
  await showAll(2);
  expect(lists).toHaveLength(2);
  // Frames already shown come from the page cache when the list returns to them.
  const downloaded = thumbs.length;
  await pageButton(1).click();
  await expect(cards).toHaveCount(48);
  await showAll(48);
  await pageButton(2).click();
  await expect(cards).toHaveCount(2);
  await showAll(2);
  expect(thumbs).toHaveLength(downloaded);

  await page.reload();
  await expect(page).toHaveURL(/[?&]page=2(&|$)/);
  await expect(cards).toHaveCount(2);
  const beforeAssignment = lists.length;
  await cards.first().getByRole('checkbox').check();
  await page.getByTestId('child-code').locator('input').fill('A');
  await page.getByRole('button', { name: 'Назначить ребёнку', exact: true }).click();
  await expect(page.getByRole('status').filter({ hasText: 'Кадры назначены ребёнку.' })).toBeVisible();
  await expect(page.getByTestId('photo-readiness')).toHaveText('Кадров: 50 · Детей: 1 · Без ребёнка: 49');
  expect(lists).toHaveLength(beforeAssignment + 1);
  expect(lists[lists.length - 1]!.searchParams.get('page')).toBe('2');

  await page.getByLabel('Хлебные крошки').getByRole('link', { name: 'Q54 Съёмка', exact: true }).click();
  await expect(page).toHaveURL(new RegExp('/shoots/' + shoot.id + '$'));
  await page.goBack();
  await expect(page).toHaveURL(/[?&]page=2(&|$)/);
  await expect(cards).toHaveCount(2);

  // A fresh mobile render, as in the other specs: resizing a live page first animates the desktop drawer away.
  await page.setViewportSize({ width: 390, height: 844 });
  await page.reload();
  await expect(page).toHaveURL(/[?&]page=2(&|$)/);
  await expect(cards).toHaveCount(2);
  await expect(page.getByTestId('photo-pagination')).toBeVisible();
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
  await page.screenshot({ path: testInfo.outputPath('q54-mobile-photos.png'), fullPage: true, animations: 'disabled' });
});

test('#55: превью прежней сессии отменяются при выходе и не сбрасывают новый вход', async ({ page }) => {
  test.setTimeout(120000);
  await page.setViewportSize({ width: 1440, height: 1000 });
  await login(page);
  const suffix = crypto.randomUUID().slice(0, 8);
  const institution = (
    await result(
      await page.request.post('/api/v1/institutions', {
        headers: await headers(page),
        data: { name: 'Q55 Детский сад ' + suffix, address: 'Москва' }
      }),
      201
    )
  ).data;
  const shoot = (
    await result(
      await page.request.post('/api/v1/institutions/' + institution.id + '/shoots', {
        headers: await headers(page),
        data: { name: 'Q55 Съёмка', date: '2026-10-20' }
      }),
      201
    )
  ).data;
  const group = (
    await result(
      await page.request.post('/api/v1/shoots/' + shoot.id + '/groups', {
        headers: await headers(page),
        data: { name: 'Q55 Ромашки', groupKind: 'regular' }
      }),
      201
    )
  ).data;
  const listing = '/api/v1/shoots/' + shoot.id + '/photos';
  const authorization = { Authorization: 'Bearer ' + (await token(page)) };
  await result(
    await page.request.post(listing, {
      headers: authorization,
      multipart: { groupId: group.id, file: { name: 'q55-session.png', mimeType: 'image/png', buffer: pngVariant(suffix + '-session') } }
    }),
    202
  );
  await expect
    .poll(
      async () =>
        (await result(await page.request.get(listing + '?status=ready&pageSize=1&groupId=' + group.id, { headers: authorization }), 200))
          .data.meta.total,
      { timeout: 60000 }
    )
    .toBe(1);

  // The preview of the first session stays in flight until that session ends and the next one begins.
  const thumbPath = /^\/api\/v1\/photos\/[0-9a-f-]{36}\/thumb$/;
  const held: Route[] = [];
  const cancelled: string[] = [];
  await page.route(
    (url) => thumbPath.test(url.pathname),
    (route) => {
      held.push(route);
    }
  );
  page.on('requestfailed', (request) => {
    if (thumbPath.test(new URL(request.url()).pathname)) cancelled.push(request.failure()?.errorText ?? '');
  });
  await page.goto('/cabinet/institutions/' + institution.id + '/shoots/' + shoot.id + '/photos?group=' + group.id);
  await expect(page.getByTestId('photo-card')).toHaveCount(1);
  await expect.poll(() => held.length).toBe(1);
  const loggedOut = page.waitForResponse((response) => response.url().endsWith('/auth/logout'));
  await logout(page);
  await loggedOut;
  await expect.poll(() => cancelled).toEqual(['net::ERR_ABORTED']);

  await login(page);
  const current = await token(page);
  // A late 401 of the ended session must not reach the shared interceptor that clears the session.
  await held[0]!.fulfill({ status: 401, contentType: 'application/json', body: JSON.stringify({ error: { code: 'UNAUTHORIZED' } }) });
  await expect(page.getByRole('button', { name: 'Новая продукция', exact: true })).toBeEnabled();
  expect(await token(page)).toBe(current);
  await expect(page).not.toHaveURL(/\/login/);
});
