import { test, expect, type Page } from '@playwright/test';
import { crc32, deflateSync } from 'node:zlib';
import { login, logout, token } from './helpers.js';

// B3: avatars with photos. The MySQL + file verifier (tools/e2e/verify-avatar.php) checks what stays afterwards:
// the teacher keeps version 1 of the 1200 × 800 portrait, the organizer's own avatar is removed.
function png(width: number, height: number, seed: number): Buffer {
  const raw = Buffer.alloc((width * 3 + 1) * height);
  for (let y = 0; y < height; y++) {
    for (let x = 0; x < width; x++) {
      const at = y * (width * 3 + 1) + 1 + x * 3;
      raw[at] = (seed * 53 + (x >> 3)) & 255;
      raw[at + 1] = (seed * 97 + (y >> 3)) & 255;
      raw[at + 2] = (seed * 31 + ((x + y) >> 4)) & 255;
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

const selfie = png(640, 640, 5);
const portrait = png(1200, 800, 9);

const pageErrors = new WeakMap<object, string[]>();
test.beforeEach(async ({ page }) => {
  const errors: string[] = [];
  pageErrors.set(page, errors);
  page.on('pageerror', (error) => errors.push(error.message));
});
test.afterEach(async ({ page }) => {
  expect(pageErrors.get(page)).toEqual([]);
});

function sidebarPhoto(page: Page) {
  return page.locator('.mf-sidebar__user').getByTestId('avatar-photo');
}

async function upload(page: Page, scope: ReturnType<Page['getByTestId']>, file: Buffer, name: string) {
  const response = page.waitForResponse((r) => r.url().includes('/avatar') && r.request().method() === 'PUT');
  await scope.getByTestId('avatar-file').setInputFiles({ name, mimeType: 'image/png', buffer: file });
  const result = await response;
  expect(result.status()).toBe(200);
  return (await result.json()).data as { userId: number; avatar: { version: number; thumbUrl: string; fullUrl: string } };
}

test('B3: сотрудник ставит своё фото в профиле, тот же файл не меняет версию', async ({ page }) => {
  await login(page);
  await page.goto('/cabinet/profile');
  const editor = page.getByTestId('avatar-editor');

  const first = await upload(page, editor, selfie, 'selfie.png');
  expect(first.avatar.version).toBe(1);
  expect(first.avatar.thumbUrl).toMatch(/\/avatar\/64\?v=1$/);
  await expect(sidebarPhoto(page)).toBeVisible();
  await expect(editor.getByTestId('avatar-photo')).toBeVisible();

  const again = await upload(page, editor, selfie, 'selfie-again.png');
  expect(again.avatar.version).toBe(1);

  await page.getByLabel('Основная навигация').getByRole('link', { name: 'Сотрудники', exact: true }).click();
  const row = page.getByRole('button', { name: 'Редактировать сотрудника organizer', exact: true });
  await expect(row.getByTestId('avatar-photo')).toBeVisible();
});

test('B3: организатор ставит фото учителю, учитель не меняет чужое, кеш отвечает 304', async ({ page }) => {
  await login(page);
  const organizerId = (await (await page.request.get('/api/v1/me', { headers: { Authorization: 'Bearer ' + (await token(page)) } })).json())
    .data.id as number;
  await page.getByLabel('Основная навигация').getByRole('link', { name: 'Сотрудники', exact: true }).click();
  await page.getByRole('button', { name: 'Редактировать сотрудника teacher', exact: true }).click();
  const dialog = page.getByTestId('admin-dialog');
  const saved = await upload(page, dialog.getByTestId('avatar-editor'), portrait, 'portrait.png');
  expect(saved.avatar.version).toBe(1);
  await dialog.getByRole('button', { name: 'Закрыть редактор', exact: true }).click();
  await expect(dialog).not.toBeVisible();
  await expect(
    page.getByRole('button', { name: 'Редактировать сотрудника teacher', exact: true }).getByTestId('avatar-photo')
  ).toBeVisible();

  await logout(page);
  await login(page, 'teacher');
  await expect(sidebarPhoto(page)).toBeVisible();
  const auth = { Authorization: 'Bearer ' + (await token(page)) };
  const file = { name: 'foreign.png', mimeType: 'image/png', buffer: selfie };
  expect((await page.request.put('/api/v1/users/' + organizerId + '/avatar', { headers: auth, multipart: { file } })).status()).toBe(403);
  const notMultipart = await page.request.put('/api/v1/me/avatar', { headers: auth, data: { file: 'x' } });
  expect(notMultipart.status()).toBe(400);
  const text = await page.request.put('/api/v1/me/avatar', {
    headers: auth,
    multipart: { file: { name: 'note.txt', mimeType: 'text/plain', buffer: Buffer.from('not a photo') } }
  });
  expect(text.status()).toBe(422);
  expect((await text.json()).error.message).toBe('UNSUPPORTED_AVATAR_FORMAT');

  const image = await page.request.get(saved.avatar.thumbUrl, { headers: auth });
  expect(image.status()).toBe(200);
  expect(image.headers()['content-type']).toBe('image/webp');
  expect(image.headers()['cache-control']).toContain('immutable');
  expect(image.headers()['cache-control']).not.toContain('no-store');
  const etag = image.headers()['etag'];
  expect(etag).toBe('"' + saved.userId + '-1-64"');
  const cached = await page.request.get(saved.avatar.thumbUrl, { headers: { ...auth, 'If-None-Match': etag! } });
  expect(cached.status()).toBe(304);
  expect((await cached.body()).length).toBe(0);
  const stale = await page.request.get(saved.avatar.thumbUrl.replace('v=1', 'v=2'), { headers: auth });
  expect(stale.status()).toBe(404);
});

test('B3: удаление фото возвращает инициалы', async ({ page }) => {
  await login(page);
  await page.goto('/cabinet/profile');
  await expect(sidebarPhoto(page)).toBeVisible();
  const removed = page.waitForResponse((r) => r.url().endsWith('/api/v1/me/avatar') && r.request().method() === 'DELETE');
  await page.getByTestId('avatar-editor').getByRole('button', { name: 'Удалить фото', exact: true }).click();
  expect((await removed).status()).toBe(204);
  await expect(sidebarPhoto(page)).toHaveCount(0);
  await expect(page.locator('.mf-sidebar__user .mf-avatar__letters')).toBeVisible();
});
