import { createHash } from 'node:crypto';
import { existsSync, readFileSync, renameSync, writeFileSync } from 'node:fs';
import { test, expect, type APIResponse, type Response, type Page } from '@playwright/test';
import { login, token } from './helpers.js';

type Archive = {
  id: string;
  status: string;
  files: number;
  error: string | null;
  bytes: number | null;
  capturedAt: string;
  expiresAt: string | null;
};
type Setup = {
  institutionId: string;
  orderIds: string[];
  oldKey: string;
  groups: string[];
  expectedPhotos: Record<string, string>;
  excludedPhotos: Record<string, string>;
};
const fixture = JSON.parse(readFileSync('var/e4-fixture.json', 'utf8')) as { print: { groupId: string } };
const key = () => crypto.randomUUID().replace(/-/g, '');
let requests = 0;
async function stand(action: string): Promise<Setup> {
  requests += 1;
  writeFileSync(`var/digital-request-${requests}.json.tmp`, JSON.stringify({ action, sourceGroup: fixture.print.groupId }));
  renameSync(`var/digital-request-${requests}.json.tmp`, `var/digital-request-${requests}.json`);
  const answer = `var/digital-answer-${requests}.json`;
  await expect.poll(() => existsSync(answer), { timeout: 90000 }).toBe(true);
  const result = JSON.parse(readFileSync(answer, 'utf8'));
  expect(result.error).toBeUndefined();
  return result as Setup;
}
async function body(response: APIResponse | Response, status = 200) {
  expect(response.status(), await response.text()).toBe(status);
  return response.json();
}
const bearer = async (page: Page) => ({ Authorization: 'Bearer ' + (await token(page)) });

test.use({ locale: 'ru-RU', acceptDownloads: true });
test('T14/T12/T15 digital institution: purchased history, exact ZIP, current rights and desktop/mobile', async ({
  page,
  browser,
  baseURL
}, testInfo) => {
  test.setTimeout(240000);
  const setup = await stand('setup');
  const orders = JSON.parse(readFileSync('var/e5-orders.json', 'utf8')) as { orderIds: string[] };
  orders.orderIds.push(...setup.orderIds);
  writeFileSync('var/e5-orders.json', JSON.stringify(orders));
  await page.setViewportSize({ width: 1440, height: 1000 });
  await login(page);
  const endpoint = '/api/v1/institutions/' + setup.institutionId + '/digital-archives';
  const headers = await bearer(page);
  await body(await page.request.get(endpoint), 401);
  const oldBuyer = { 'X-Order-Key': setup.oldKey };
  await body(await page.request.get('/api/v1/public/orders/current/files', { headers: oldBuyer }), 404);

  await page.goto('/cabinet/institutions/' + setup.institutionId);
  await page.getByRole('button', { name: 'Скачать заказанные электронные кадры', exact: true }).click();
  const dialog = page.getByTestId('institution-digital-archive');
  await expect(dialog).toBeVisible();
  await expect(dialog.getByTestId('digital-archive-no-releases')).toBeVisible();
  const creating = page.waitForResponse((response) => response.url().endsWith(endpoint) && response.request().method() === 'POST');
  await dialog.getByRole('button', { name: 'Создать выгрузку', exact: true }).click();
  const response = await creating;
  const first = (await body(response, 202)).data as Archive;
  const idempotencyKey = response.request().headers()['idempotency-key'];
  expect(idempotencyKey).toBeTruthy();
  if (!idempotencyKey) throw new Error('The archive request must carry an idempotency key.');
  const state = async (): Promise<{ canCreate: boolean; items: Archive[] }> =>
    (await body(await page.request.get(endpoint, { headers }))).data;
  await expect.poll(async () => (await state()).items.find((item) => item.id === first.id)?.status, { timeout: 60000 }).toBe('ready');
  const ready = (await state()).items.find((item) => item.id === first.id)!;
  expect(ready.files).toBe(Object.keys(setup.expectedPhotos).length);
  expect(ready.files).toBe(4);
  expect(ready.error).toBeNull();
  expect((await body(await page.request.post(endpoint, { headers: { ...headers, 'Idempotency-Key': idempotencyKey } }), 202)).data.id).toBe(
    first.id
  );
  expect((await state()).items).toHaveLength(1);
  const archive = '/api/v1/institution-digital-archives/' + first.id;
  const link = (await body(await page.request.get(archive + '/download', { headers }))).data as { url: string; expiresAt: string };
  const downloaded = await page.request.get(link.url);
  expect(downloaded.status()).toBe(200);
  const bytes = await downloaded.body();
  expect(bytes.subarray(0, 4).toString('hex')).toBe('504b0304');
  expect(bytes.length).toBe(ready.bytes);
  await expect(dialog.getByRole('button', { name: 'Скачать ZIP', exact: true })).toBeVisible();
  const uiDownload = page.waitForEvent('download');
  await dialog.getByRole('button', { name: 'Скачать ZIP', exact: true }).click();
  const file = await uiDownload;
  expect(await file.failure()).toBeNull();
  const stream = await file.createReadStream();
  const digest = createHash('sha256');
  for await (const chunk of stream) digest.update(chunk);
  const sha256 = createHash('sha256').update(bytes).digest('hex');
  expect(digest.digest('hex')).toBe(sha256);
  const csv = await page.request.get(archive + '/manifest', { headers });
  expect(csv.status()).toBe(200);
  expect(await csv.text()).toContain('0001.JPG');
  for (const viewport of [
    { name: 'desktop', width: 1440, height: 1000 },
    { name: 'mobile', width: 390, height: 844 }
  ]) {
    await page.setViewportSize(viewport);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    expect(await dialog.evaluate((element) => element.scrollWidth <= element.clientWidth)).toBe(true);
    const notice = dialog.getByRole('status').filter({ hasText: 'Архив передан браузеру для скачивания.' });
    expect(await notice.evaluate((element) => element.clientHeight >= element.scrollHeight)).toBe(true);
    await page.screenshot({ path: testInfo.outputPath('institution-digital-' + viewport.name + '.png'), animations: 'disabled' });
    if (viewport.name === 'mobile') {
      const close = dialog.getByRole('button', { name: 'Закрыть', exact: true });
      await close.scrollIntoViewIfNeeded();
      await expect(close).toBeInViewport();
      await page.screenshot({ path: testInfo.outputPath('institution-digital-mobile-footer.png'), animations: 'disabled' });
    }
  }
  const teacherContext = await browser.newContext({ baseURL });
  const curatorContext = await browser.newContext({ baseURL });
  try {
    const teacher = await teacherContext.newPage();
    await login(teacher, 'teacher');
    await body(await teacher.request.get(endpoint, { headers: await bearer(teacher) }), 403);
    await body(await teacher.request.get(archive + '/manifest', { headers: await bearer(teacher) }), 403);
    const curator = await curatorContext.newPage();
    await login(curator, 'curator');
    const curatorHeaders = await bearer(curator);
    expect((await body(await curator.request.get(endpoint, { headers: curatorHeaders }))).data.canCreate).toBe(false);
    await body(await curator.request.post(endpoint, { headers: { ...curatorHeaders, 'Idempotency-Key': key() } }), 403);
    const curatorLink = (await body(await curator.request.get(archive + '/download', { headers: curatorHeaders }))).data.url as string;
    expect((await curator.request.get(curatorLink)).status()).toBe(200);
    await stand('revoke');
    try {
      await body(await curator.request.get(endpoint, { headers: curatorHeaders }), 404);
      await body(await curator.request.get(curatorLink), 404);
    } finally {
      await stand('grant');
    }
  } finally {
    await teacherContext.close();
    await curatorContext.close();
  }
  await stand('hide');
  let failedId = '';
  try {
    const failing = page.waitForResponse((result) => result.url().endsWith(endpoint) && result.request().method() === 'POST');
    await dialog.getByRole('button', { name: 'Создать выгрузку', exact: true }).click();
    const failed = (await body(await failing, 202)).data as Archive;
    failedId = failed.id;
    await expect.poll(async () => (await state()).items.find((item) => item.id === failed.id)?.status, { timeout: 60000 }).toBe('failed');
    expect((await state()).items.find((item) => item.id === failed.id)?.error).toBe('ORIGINAL_MISSING');
    await expect(dialog.getByText('Ошибка сборки', { exact: true })).toBeVisible({ timeout: 10000 });
    await body(await page.request.get('/api/v1/institution-digital-archives/' + failed.id + '/download', { headers }), 404);
  } finally {
    await stand('restore');
  }
  await body(await page.request.get('/api/v1/public/orders/current/files', { headers: oldBuyer }), 404);
  writeFileSync(
    'var/institution-digital.json',
    JSON.stringify({ institutionId: setup.institutionId, archiveId: first.id, failedId, sha256, bytes: bytes.length })
  );
});
