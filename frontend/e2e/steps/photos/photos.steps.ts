import { Given, Then } from '@cucumber/cucumber';
import { expect, type Page } from '@playwright/test';
import { mkdir } from 'node:fs/promises';
import { CustomWorld } from '../../support/world.js';
import type { PhotoState } from '../../../src/modules/morefoto/photos/types.js';
import { signOut } from '../../support/shell.js';
const route = '/cabinet/institutions/sun/shoots/sun-autumn-2026/photos';
const shootRoute = '/cabinet/institutions/sun/shoots/sun-autumn-2026';
function page(w: CustomWorld) {
  if (!w.page) throw new Error('Нет страницы');
  return w.page;
}
async function select(p: Page, id: string, name: string) {
  await p.getByTestId(id).locator('.v-field').click();
  await p.getByRole('option', { name, exact: true }).click();
}
async function login(p: Page, base: string, email = 'organizer@morefoto.test') {
  await p.goto(base + '/login', { waitUntil: 'networkidle' });
  await p.getByLabel('Email', { exact: true }).fill(email);
  await p.getByLabel('Пароль', { exact: true }).fill('morefoto-demo');
  await p.getByTestId('login-submit').click();
  await expect(p).toHaveURL(/cabinet/);
}
async function photoState(p: Page): Promise<PhotoState> {
  return p.evaluate(() => JSON.parse(localStorage.getItem('morefoto:demo:photos:v1') ?? '{"photos":[],"covers":{}}'));
}
async function localPhotos(p: Page) {
  return (await photoState(p)).photos.filter((photo) => photo.source === 'local');
}
async function file(p: Page, name: string, color = '#2762a3', width = 1600, height = 2400) {
  const data = await p.evaluate(
    ({ color, width, height }) => {
      const canvas = document.createElement('canvas');
      canvas.width = width;
      canvas.height = height;
      const context = canvas.getContext('2d')!;
      context.fillStyle = color;
      context.fillRect(0, 0, width, height);
      return canvas.toDataURL('image/png').split(',')[1]!;
    },
    { color, width, height }
  );
  return { name, mimeType: 'image/png', buffer: Buffer.from(data, 'base64') };
}
async function add(p: Page, count = 2) {
  const files = await Promise.all(
    Array.from({ length: count }, (_, i) => file(p, 'кадр-' + i + '.png', ['#2762a3', '#bb621a', '#598e24'][i]!))
  );
  await p.locator('input[type=file]').setInputFiles(files);
  return files;
}
async function start(p: Page) {
  await p.getByRole('button', { name: 'Начать подготовку', exact: true }).click();
  await expect(p.locator('.upload-row').filter({ hasText: 'В очереди' })).toHaveCount(0);
  await expect(p.getByRole('progressbar')).toHaveCount(0);
}
async function upload(p: Page, count = 2) {
  const files = await add(p, count);
  await start(p);
  await expect.poll(async () => (await localPhotos(p)).length).toBe(count);
  return files;
}
async function assign(p: Page, code = 'A') {
  for (const checkbox of await p.getByRole('checkbox').all()) await checkbox.check();
  await p.getByTestId('child-code').locator('input').fill(code);
  await p.getByRole('button', { name: 'Назначить ребёнку', exact: true }).click();
  await expect(p.getByRole('status').filter({ hasText: 'Кадры назначены' })).toBeVisible();
}
async function openMove(p: Page, code = 'A') {
  await select(p, 'photo-filter', 'Ребёнок ' + code);
  await p.getByRole('button', { name: 'Перенести весь набор', exact: true }).click();
  await expect(p.getByRole('dialog')).toBeVisible();
}
async function transfer(p: Page, code = 'A') {
  await p.getByTestId('move-code').locator('input').fill(code);
  await p.getByRole('dialog').getByRole('button', { name: 'Перенести набор', exact: true }).click();
}
Given('организатор открыл фотографии R08', async function (this: CustomWorld) {
  const p = page(this);
  await login(p, this.baseUrl);
  await p.goto(this.baseUrl + shootRoute, { waitUntil: 'networkidle' });
  await p.getByRole('button', { name: 'Новая группа', exact: true }).click();
  await p.getByLabel('Название группы', { exact: true }).fill('Ландыши');
  await p.getByRole('dialog').getByRole('button', { name: 'Сохранить', exact: true }).click();
  await expect(p.getByRole('dialog')).toHaveCount(0);
  await p.getByRole('link', { name: 'Фотографии', exact: true }).click();
  await expect(p.getByRole('heading', { name: 'Фотографии съёмки', level: 1 })).toBeVisible();
  await expect(p.locator('input[type=file]')).toBeEnabled();
});
Then('фотографии R08 сохраняются с кодами и обложкой', async function (this: CustomWorld) {
  const p = page(this);
  await upload(p);
  await assign(p);
  await p.getByRole('button', { name: 'Сделать обложкой', exact: true }).first().click();
  await expect(p.getByRole('status').filter({ hasText: 'Обложка группы сохранена' })).toBeVisible();
  const before = await localPhotos(p);
  expect(before.map((x) => x.code)).toEqual(['A001', 'A002']);
  const blobs = await p.evaluate(
    async (ids) => {
      const db = await new Promise<IDBDatabase>((resolve, reject) => {
        const r = indexedDB.open('morefoto-demo-photos-v1', 1);
        r.onsuccess = () => resolve(r.result);
        r.onerror = () => reject(r.error);
      });
      const result = [];
      for (const id of ids)
        for (const variant of ['thumb', 'preview']) {
          const blob = await new Promise<Blob>((resolve) => {
            const r = db
              .transaction('previews', 'readonly')
              .objectStore('previews')
              .get(id + ':' + variant);
            r.onsuccess = () => resolve(r.result);
          });
          const bitmap = await createImageBitmap(blob);
          result.push({ type: blob.type, max: Math.max(bitmap.width, bitmap.height), variant, size: blob.size });
          bitmap.close();
        }
      db.close();
      return result;
    },
    before.map((x) => x.id)
  );
  for (const blob of blobs) {
    expect(blob.type).toBe('image/webp');
    expect(blob.max).toBe(blob.variant === 'thumb' ? 320 : 1200);
    expect(blob.size).toBeGreaterThan(100);
  }
  await p.reload({ waitUntil: 'networkidle' });
  await expect(p.getByTestId('photo-card')).toHaveCount(2);
  await expect
    .poll(async () =>
      p
        .getByTestId('photo-card')
        .locator('img')
        .evaluateAll((imgs) => imgs.every((img) => (img as HTMLImageElement).naturalWidth > 0))
    )
    .toBe(true);
  expect(await localPhotos(p)).toEqual(before);
  await expect(p.getByText('Обложка группы выбрана', { exact: true })).toBeVisible();
});
Then('дубликаты R08 не создают второй кадр', async function (this: CustomWorld) {
  const p = page(this);
  const files = await upload(p, 1);
  await p.locator('input[type=file]').setInputFiles([{ ...files[0]!, name: 'переименован.png' }, await file(p, files[0]!.name, '#987123')]);
  await start(p);
  expect((await localPhotos(p)).length).toBe(2);
  await expect(p.getByText('Повтор файла', { exact: true })).toBeVisible();
});
Then('частичная ошибка R08 оставляет успешные кадры', async function (this: CustomWorld) {
  const p = page(this);
  await p
    .locator('input[type=file]')
    .setInputFiles([
      { name: 'повреждён.png', mimeType: 'image/png', buffer: Buffer.from('broken') },
      await file(p, 'успех.png'),
      { name: 'документ.svg', mimeType: 'image/svg+xml', buffer: Buffer.from('<svg/>') }
    ]);
  await start(p);
  expect((await localPhotos(p)).length).toBe(1);
  await expect(p.locator('.upload-row').filter({ hasText: 'Изображение не читается' })).toBeVisible();
  await expect(p.locator('.upload-row').filter({ hasText: 'Допустимы JPEG' })).toBeVisible();
});
Then('повтор R08 сохраняет файл единожды', async function (this: CustomWorld) {
  const p = page(this);
  await add(p);
  await p.locator('summary').filter({ hasText: 'Проверка демонстрации' }).click();
  await p.getByRole('button', { name: 'Ошибка следующего файла', exact: true }).click();
  await start(p);
  expect((await localPhotos(p)).length).toBe(1);
  await p.getByRole('button', { name: 'Повторить файл кадр-0.png', exact: true }).click();
  await expect.poll(async () => (await localPhotos(p)).length).toBe(2);
  await expect(p.getByTestId('upload-counts')).toContainText('Требуют внимания: 0');
});
Then('очередь R08 запрашивает исходный файл после обновления', async function (this: CustomWorld) {
  const p = page(this);
  const files = await add(p, 1);
  const modified = await p.evaluate(() => {
    const key = Object.keys(sessionStorage).find((key) => key.startsWith('morefoto:demo:uploads:'))!;
    return JSON.parse(sessionStorage.getItem(key)!)[0].modified as number;
  });
  await p.addInitScript((value) => Object.defineProperty(File.prototype, 'lastModified', { get: () => value }), modified);
  await p.reload({ waitUntil: 'networkidle' });
  await expect(p.getByText('Нужен исходный файл', { exact: true })).toBeVisible();
  await p.getByRole('button', { name: 'Повторить файл кадр-0.png' }).click();
  await expect(p.getByRole('alert').filter({ hasText: 'Выберите исходный файл снова' })).toBeVisible();
  await p.locator('input[type=file]').setInputFiles(files);
  await start(p);
  expect((await localPhotos(p)).length).toBe(1);
  await expect(p.locator('.upload-row')).toHaveCount(1);
});
Then('смена группы R08 не переносит загружаемые файлы', async function (this: CustomWorld) {
  const p = page(this);
  await add(p);
  await p.evaluate(() => (window as Window & { __MOREFOTO_MOCKS__?: { setDelay(ms: number): void } }).__MOREFOTO_MOCKS__?.setDelay(1800));
  await p.getByRole('button', { name: 'Начать подготовку', exact: true }).click();
  await select(p, 'photo-group', 'Ландыши · Подготовка');
  await expect.poll(async () => (await localPhotos(p)).length).toBe(2);
  expect((await localPhotos(p)).every((x) => x.groupId === 'sun-bees')).toBe(true);
  await expect(p.getByTestId('photo-card')).toHaveCount(0);
  await p.reload({ waitUntil: 'networkidle' });
  await expect(p.getByTestId('photo-group')).toContainText('Ландыши');
});
Then('перенос R08 перемещает полный набор и очищает обложку', async function (this: CustomWorld) {
  const p = page(this);
  await upload(p);
  await assign(p);
  await p.getByRole('button', { name: 'Сделать обложкой', exact: true }).first().click();
  await expect(p.getByRole('status').filter({ hasText: 'Обложка группы сохранена' })).toBeVisible();
  await p.getByRole('checkbox').first().check();
  await openMove(p);
  await transfer(p, 'B');
  await expect(p.getByRole('dialog')).toHaveCount(0);
  const photos = await localPhotos(p);
  expect(photos.map((x) => x.code)).toEqual(['B001', 'B002']);
  expect(photos.every((x) => x.groupId !== 'sun-bees' && x.originalGroupId === 'sun-bees')).toBe(true);
  expect((await photoState(p)).covers['sun-bees']).toBeUndefined();
  await select(p, 'photo-group', 'Ландыши · Подготовка');
  await expect(p.getByTestId('photo-card')).toHaveCount(2);
});
Then('конфликт кода R08 сохраняет оба набора', async function (this: CustomWorld) {
  const p = page(this);
  await upload(p, 1);
  await assign(p);
  await select(p, 'photo-group', 'Ландыши · Подготовка');
  await p.locator('input[type=file]').setInputFiles(await file(p, 'другой.png', '#b76b11'));
  await start(p);
  await assign(p);
  await openMove(p);
  const before = await localPhotos(p);
  await transfer(p);
  await expect(p.getByRole('dialog').getByRole('alert').filter({ hasText: 'код уже занят' })).toBeVisible();
  expect(await localPhotos(p)).toEqual(before);
  await p.getByRole('button', { name: 'Отмена', exact: true }).click();
});
Then('устаревший перенос R08 требует проверки набора', async function (this: CustomWorld) {
  const p = page(this);
  await upload(p);
  await assign(p);
  await openMove(p);
  await p.evaluate(() => {
    const key = 'morefoto:demo:photos:v1';
    const state = JSON.parse(localStorage.getItem(key)!);
    state.photos.find((x: { source: string }) => x.source === 'local').childCode = 'B';
    localStorage.setItem(key, JSON.stringify(state));
  });
  await transfer(p);
  await expect(p.getByRole('dialog').getByRole('alert').filter({ hasText: 'Состав набора изменился' })).toBeVisible();
  expect((await localPhotos(p)).every((x) => x.groupId === 'sun-bees')).toBe(true);
});
Then('предпросмотр R08 ограничен выбранным ребёнком', async function (this: CustomWorld) {
  const p = page(this);
  await upload(p);
  await assign(p);
  await p.getByRole('button', { name: 'Предпросмотр', exact: true }).click();
  const dialog = p.getByRole('dialog');
  await expect(dialog.getByTestId('preview-position')).toHaveText('A001 · 1 из 2');
  await dialog.getByRole('button', { name: 'Следующий кадр', exact: true }).click();
  await expect(dialog.getByTestId('preview-position')).toHaveText('A002 · 2 из 2');
  await expect(dialog.getByRole('button', { name: 'Следующий кадр', exact: true })).toBeDisabled();
  await expect(dialog.getByRole('button', { name: /корзин/i })).toHaveCount(0);
  await dialog.getByRole('button', { name: 'Закрыть просмотр', exact: true }).click();
  await expect(p.getByRole('button', { name: 'Предпросмотр', exact: true })).toBeFocused();
});
Then('неверный код R08 показывает ошибку без изменений', async function (this: CustomWorld) {
  const p = page(this);
  await upload(p, 1);
  await p.getByRole('checkbox').check();
  await p.getByTestId('child-code').locator('input').fill('А1');
  await expect(p.getByRole('button', { name: 'Назначить ребёнку', exact: true })).toBeDisabled();
  await expect(p.getByText('Код ребёнка — от 1 до 3 латинских букв.', { exact: false })).toBeVisible();
  expect((await localPhotos(p))[0]?.childCode).toBeNull();
});
Then('опубликованная группа R08 защищена от изменений', async function (this: CustomWorld) {
  const p = page(this);
  await p.goto(this.baseUrl + '/cabinet/institutions/sun/shoots/sun-summer-2026/photos', { waitUntil: 'networkidle' });
  await expect(p.getByText('Подборка уже опубликована.', { exact: false })).toBeVisible();
  await expect(p.locator('input[type=file]')).toHaveCount(0);
  await expect(p.getByRole('checkbox')).toHaveCount(0);
  await expect(p.getByRole('button', { name: 'Сделать обложкой' })).toHaveCount(0);
  await expect(p.getByRole('button', { name: 'Предпросмотр', exact: true })).toBeEnabled();
});
Then('уход R08 требует подтверждения и сохраняет очередь', async function (this: CustomWorld) {
  const p = page(this);
  await add(p);
  await p.evaluate(() => (window as Window & { __MOREFOTO_MOCKS__?: { setDelay(ms: number): void } }).__MOREFOTO_MOCKS__?.setDelay(3000));
  await p.getByRole('button', { name: 'Начать подготовку', exact: true }).click();
  const first = p.waitForEvent('dialog').then(async (warning) => {
    expect(warning.message()).toContain('Подготовка файлов ещё идёт');
    await warning.dismiss();
  });
  await Promise.all([first, p.locator('.mf-back').click()]);
  await expect(p).toHaveURL(new RegExp(route + '(\\?.*)?$'));
  const second = p.waitForEvent('dialog').then((warning) => warning.accept());
  await Promise.all([second, p.locator('.mf-back').click()]);
  await expect(p.getByRole('heading', { name: 'Осенняя съёмка · 2026', level: 1 })).toBeVisible();
  await p.getByRole('link', { name: 'Фотографии', exact: true }).click();
  await expect(p.getByText('Нужен исходный файл', { exact: true }).first()).toBeVisible();
});
Then('отсутствующее превью R08 восстанавливается без дубля', async function (this: CustomWorld) {
  const p = page(this);
  const files = await upload(p, 1);
  await p.evaluate(async () => {
    const db = await new Promise<IDBDatabase>((resolve) => {
      const r = indexedDB.open('morefoto-demo-photos-v1', 1);
      r.onsuccess = () => resolve(r.result);
    });
    await new Promise<void>((resolve) => {
      const tx = db.transaction('previews', 'readwrite');
      tx.objectStore('previews').clear();
      tx.oncomplete = () => resolve();
    });
    db.close();
  });
  await p.reload({ waitUntil: 'networkidle' });
  await expect(p.getByText('Кадр не загрузился', { exact: true })).toBeVisible();
  await p.locator('input[type=file]').setInputFiles(files);
  await start(p);
  await p.getByRole('button', { name: 'Повторить загрузку: Кадр кадр-0.png', exact: true }).click();
  await expect
    .poll(async () =>
      p
        .getByTestId('photo-card')
        .locator('img')
        .evaluate((img) => (img as HTMLImageElement).naturalWidth)
    )
    .toBeGreaterThan(0);
  expect((await localPhotos(p)).length).toBe(1);
});
Then('истёкшая сессия R08 не сохраняет фотографии', async function (this: CustomWorld) {
  const p = page(this);
  await add(p, 1);
  await p.evaluate(() => {
    for (const key of Object.keys(localStorage)) if (key.includes('session')) localStorage.removeItem(key);
  });
  await start(p);
  expect((await localPhotos(p)).length).toBe(0);
  await expect(p.locator('.upload-row')).toContainText(/сесси/i);
});
Then('сотрудник R08 {string} не получает редактор', async function (this: CustomWorld, email: string) {
  const p = page(this);
  await signOut(p);
  await login(p, this.baseUrl, email);
  await p.goto(this.baseUrl + route, { waitUntil: 'networkidle' });
  await expect(p.locator('input[type=file]')).toHaveCount(0);
  await expect(p.getByRole('heading', { name: 'Фотографии съёмки', level: 1 })).toHaveCount(0);
});
Then('неправильный адрес R08 показывает отсутствие съёмки', async function (this: CustomWorld) {
  const p = page(this);
  await p.goto(this.baseUrl + route.replace('/sun/', '/rainbow/'), { waitUntil: 'networkidle' });
  await expect(p.locator('input[type=file]')).toHaveCount(0);
  await expect(p.locator('#cabinet-main')).toContainText('Запись недоступна');
});
async function layout(p: Page, width: number, suffix = '') {
  await p.setViewportSize({ width, height: 920 });
  await upload(p);
  await assign(p);
  const overflow = () => p.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1);
  expect(await overflow()).toBe(false);
  await mkdir('reports/e2e/r08-visual', { recursive: true });
  await p.evaluate(() => window.scrollTo(0, 0));
  await p.screenshot({ path: 'reports/e2e/r08-visual/workspace-' + width + suffix + '.png', fullPage: true, animations: 'disabled' });
  await p.getByRole('button', { name: 'Предпросмотр', exact: true }).click();
  await expect(p.getByTestId('preview-position')).toContainText('из 2');
  expect(await overflow()).toBe(false);
  await expect(p.locator('.preview-card')).toBeVisible();
  await p.screenshot({ path: 'reports/e2e/r08-visual/preview-' + width + suffix + '.png', animations: 'disabled' });
  expect(await p.locator('.preview-card').evaluate((el) => el.scrollWidth <= el.clientWidth + 1)).toBe(true);
  await p.getByRole('button', { name: 'Закрыть просмотр', exact: true }).click();
  await openMove(p);
  expect(await overflow()).toBe(false);
  await p.screenshot({ path: 'reports/e2e/r08-visual/move-' + width + suffix + '.png', animations: 'disabled' });
  expect(
    await p
      .getByRole('dialog')
      .locator('.v-card')
      .evaluate((el) => el.scrollWidth <= el.clientWidth + 1)
  ).toBe(true);
}
Then('экран R08 доступен на ширине {int}', async function (this: CustomWorld, width: number) {
  await layout(page(this), width);
});
Then('экран R08 работает с увеличенным текстом', async function (this: CustomWorld) {
  const p = page(this);
  await p.addStyleTag({ content: 'html{font-size:200% !important}' });
  await layout(p, 390, '-200pct');
});
