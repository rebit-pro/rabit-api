import { test, expect, type APIResponse, type Page } from '@playwright/test';
import { login, token } from './helpers.js';

const png = Buffer.from(
  'iVBORw0KGgoAAAANSUhEUgAAAUAAAADICAIAAAAWZq/8AAABvElEQVR42u3TQQ0AMAgAsTE1CEMiAhHBi6SVcMlFVj/gpi8BGBgwMGBgMDBgYMDAgIHBwICBAQODgQEDAwYGDAwGBgwMGBgwMBgYMDBgYDAwYGDAwICBwcCAgQEDAwYGAwMGBgwMBgYMDBgYMDAYGDAwYGAwMGBgwMCAgcHAgIEBAwMGBgMDBgYMDAYGDAwYGDAwGBgwMGBgwMBgYMDAgIHBwICBAQMDBgYDAwYGDAwYGAwMGBgwMBgYMDBgYMDAYGDAwICBwcCAgQEDAwYGAwMGBgwMGBgMDBgYMDAYGDAwYGDAwGBgwMCAgQEDg4EBAwMGBgMDBgYMDBgYDAwYGDAwYGAwMGBgwMBgYMDAgIEBA4OBAQMDBgYDAwYGDAwYGAwMGBgwMGBgMDBgYMDAYGDAwICBAQODgQEDAwYGDAwGBgwMGBgMDBgYMDBgYDAwYGDAwGBgwMCAgQEDg4EBAwMGBgwMBgYMDBgYDAwYGDAwYGAwMGBgwMCAgcHAgIEBA4OBAQMDBgYMDAYGDAwYGDAwGBgwMGBgMDBgYMDAgIHBwICBAQODgQEDAwYGDAwGBgwMGBgwMBgYMDCwMUuEAtA7HouzAAAAAElFTkSuQmCC',
  'base64'
);
const problems = new WeakMap<Page, string[]>();

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
  const preview = await page.request.get(photo.previewSrc);
  expect(preview.status()).toBe(200);
  expect(preview.headers()['content-type']).toContain('image/webp');
  await page.screenshot({ path: testInfo.outputPath('d1-desktop-media.png'), fullPage: true });

  await page.getByTestId('photo-card').getByRole('checkbox').check();
  await page.getByLabel('Код ребёнка', { exact: true }).fill('A');
  await page.getByRole('button', { name: 'Назначить ребёнку', exact: true }).click();
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
