import { createHash } from 'node:crypto';
import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { test, expect, type APIResponse, type Page } from '@playwright/test';
import { orderConsents, payOnProvider } from './helpers.js';

// J1: purchased originals of a paid order. The order is paid on the YooKassa test page like in G1; without the test
// shop keys the spec proves only the honest unpaid state, and the gate accepts no skipped test.
type Frame = { photoId: string; sha256: string };
type Fixture = Record<'open', { token: string; groupId: string; photoId: string; sha256: string }> & {
  print: { token: string; groupId: string; frames: Record<'A1' | 'A2' | 'B1' | 'B2', Frame> };
};
type Created = { id: string; number: string; accessKey: string };
const fixture = JSON.parse(readFileSync('var/e4-fixture.json', 'utf8')) as Fixture;
const gallery = '/api/v1/public/galleries/' + fixture.open.token;
const sandbox = process.env.E2E_YOOKASSA === '1';
const key = () => crypto.randomUUID().replace(/-/g, '');
const files = '/api/v1/public/orders/current/files';
const downloads = '/api/v1/public/orders/current/downloads';
const record = {
  sandbox,
  orderId: '',
  accessKey: '',
  fileDownloadId: '',
  archiveDownloadId: '',
  archiveBytes: 0,
  // J4: the print order paid by the stand fixture (J4-DEC-04).
  printCopy: { orderId: '', accessKey: '', fileDownloadId: '', archiveDownloadId: '', archiveBytes: 0 }
};
let paid: Created | null = null;

async function body(response: APIResponse, status = 200) {
  expect(response.status(), await response.text()).toBe(status);
  return response.json();
}
const asBuyer = (orderKey: string) => ({ 'X-Order-Key': orderKey });
const sha256 = (buffer: Buffer) => createHash('sha256').update(buffer).digest('hex');
function save() {
  writeFileSync('var/j1-files.json', JSON.stringify(record));
}
/** The E5 verifier accounts for every order of the run. */
function remember(created: Created, idempotencyKey: string) {
  const path = 'var/e5-orders.json';
  const orders = existsSync(path)
    ? JSON.parse(readFileSync(path, 'utf8'))
    : { accessKeys: [], idempotencyKeys: [], galleryToken: '', orderIds: [] };
  orders.orderIds.push(created.id);
  orders.accessKeys.push(created.accessKey);
  orders.idempotencyKeys.push(idempotencyKey);
  mkdirSync('var', { recursive: true });
  writeFileSync(path, JSON.stringify(orders));
}
type Line = { assignmentId: string; productId: string; quantity: number };
/** Places the order of the priced lines; the gifts are the child codes whose print reached the threshold. */
async function placeOrder(page: Page, path: string, lines: Line[], email: string): Promise<Created & { gifts: string[] }> {
  const priced = (await body(await page.request.post(path + '/quotes', { data: { lines } }))).data;
  const idempotencyKey = key();
  const created = (
    await body(
      await page.request.post(path + '/orders', {
        headers: { 'Idempotency-Key': idempotencyKey },
        data: { lines, buyer: { email, comment: '', reviewed: true }, quoteToken: priced.quoteToken, consents: await orderConsents(page) }
      }),
      201
    )
  ).data as Created;
  remember(created, idempotencyKey);
  return { ...created, gifts: priced.quote.gifts };
}
/** A full set of child A: the bundle grants every current photo of the child. */
async function placeBundleOrder(page: Page): Promise<Created> {
  const catalog = (await body(await page.request.get(gallery + '/catalog'))).data;
  const bundle = catalog.products.find((item: { kind: string; active: boolean }) => item.kind === 'bundle');
  const child = (await body(await page.request.get(gallery))).data.children.find((item: { code: string }) => item.code === 'A');
  const lines = [{ assignmentId: child.photos[0].assignmentId, productId: bundle.id, quantity: 1 }];
  const created = await placeOrder(page, gallery, lines, 'files.j1@example.test');
  record.orderId = created.id;
  record.accessKey = created.accessKey;
  save();
  return created;
}
/** G1 pays through the real provider page; the server confirms the result, the files block only reads it. */
async function payByCard(page: Page, created: Created) {
  const quote = (await body(await page.request.get('/api/v1/public/orders/current/payment-quote', { headers: asBuyer(created.accessKey) })))
    .data;
  const attempt = (
    await body(
      await page.request.post('/api/v1/public/orders/current/payment-attempts', {
        headers: { ...asBuyer(created.accessKey), 'Idempotency-Key': key() },
        data: {
          orderVersion: quote.orderVersion,
          quoteToken: quote.quoteToken,
          precedingAttemptId: quote.precedingAttemptId,
          paymentMethod: 'bank_card'
        }
      }),
      202
    )
  ).data;
  await page.goto(attempt.redirectUrl);
  await payOnProvider(page, '5555555555554444');
  await page.waitForURL(/\/checkout\/payments\/v2\/success/, { timeout: 60000 });
  await expect
    .poll(
      async () =>
        (
          await body(
            await page.request.get('/api/v1/public/orders/current/payment-attempts/' + attempt.id, { headers: asBuyer(created.accessKey) })
          )
        ).data.status,
      { timeout: 60000, intervals: [2000] }
    )
    .toBe('succeeded');
}

test.describe.configure({ mode: 'serial' });
test.use({ locale: 'ru-RU', acceptDownloads: true });

test('J1-T04, J1-T13: an unpaid order shows no originals and refuses a download', async ({ page }, testInfo) => {
  await page.setViewportSize({ width: 1440, height: 1000 });
  const created = await placeBundleOrder(page);
  const state = (await body(await page.request.get(files, { headers: asBuyer(created.accessKey) }))).data;
  expect(state).toEqual({ state: 'unpaid', expiresAt: null, canDownload: false, totalBytes: 0, items: [] });
  const refused = await body(
    await page.request.post(downloads, { headers: { ...asBuyer(created.accessKey), 'Idempotency-Key': key() }, data: { kind: 'zip' } }),
    409
  );
  expect(refused.error.code).toBe('FILES_UNAVAILABLE');
  await body(await page.request.get(files), 404);
  await page.goto('/orders/access/' + created.accessKey);
  await expect(page.getByTestId('files-state-unpaid')).toBeVisible();
  await page.screenshot({ path: testInfo.outputPath('j1-desktop-files-unpaid.png'), fullPage: true, animations: 'disabled' });
  if (sandbox) paid = created;
});

const printGallery = '/api/v1/public/galleries/' + fixture.print.token;
type Product = { id: string; kind: string; active: boolean; price: number; name: string };
type Child = { code: string; photos: { id: string; assignmentId: string; code: string }[] };

test('J4-T06, J4-T07, J4-T08: a paid print opens each printed frame once and never its neighbour', async ({ page }, testInfo) => {
  test.setTimeout(240000);
  const catalog = (await body(await page.request.get(printGallery + '/catalog'))).data as { products: Product[] };
  const prints = catalog.products.filter((item) => item.kind === 'physical' && item.active).sort((left, right) => left.price - right.price);
  const digital = catalog.products.find((item) => item.kind === 'digital' && item.active);
  expect([prints.length > 0, !!digital]).toEqual([true, true]);
  const cheapest = prints[0]!;
  const children = (await body(await page.request.get(printGallery))).data.children as Child[];
  const frames = fixture.print.frames;
  const photo = (code: string, frame: Frame) =>
    children.find((child) => child.code === code)!.photos.find((item) => item.id === frame.photoId)!;

  // J4-DEC-03: a digital product of a frame already printed in the cart says the copy comes with the print.
  await page.goto('/g/' + fixture.print.token);
  await page.evaluate(({ groupId, line }) => localStorage.setItem('morefoto:cart:v1:' + groupId, JSON.stringify([line])), {
    groupId: fixture.print.groupId,
    line: { assignmentId: photo('B', frames.B1).assignmentId, productId: cheapest.id, quantity: 1 }
  });
  for (const viewport of [
    { name: 'desktop', width: 1440, height: 1000 },
    { name: 'mobile', width: 390, height: 844 }
  ]) {
    await page.setViewportSize(viewport);
    await page.goto('/g/' + fixture.print.token);
    await page.getByRole('button', { name: 'Открыть кадр ' + photo('B', frames.B1).code, exact: true }).click();
    await page.locator('.product-selector .v-select .v-field').click();
    await page.getByRole('option', { name: digital!.name, exact: true }).click();
    await expect(page.getByTestId('product-print-copy')).toHaveText('Электронная копия этого кадра входит в печать вашей корзины.');
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    await page.screenshot({
      path: testInfo.outputPath('j4-' + viewport.name + '-print-copy-hint.png'),
      fullPage: true,
      animations: 'disabled'
    });
  }
  await page.evaluate((groupId) => localStorage.removeItem('morefoto:cart:v1:' + groupId), fixture.print.groupId);

  // A1 in up to two formats, the first one three times; B1 once in the cheapest format, below the gift threshold.
  const a1 = photo('A', frames.A1).assignmentId;
  const created = await placeOrder(
    page,
    printGallery,
    [
      ...prints.slice(0, 2).map((print, index) => ({ assignmentId: a1, productId: print.id, quantity: index ? 1 : 3 })),
      { assignmentId: photo('B', frames.B1).assignmentId, productId: cheapest.id, quantity: 1 }
    ],
    'print.j4@example.test'
  );
  expect(created.gifts).not.toContain('B');
  const headers = asBuyer(created.accessKey);
  Object.assign(record.printCopy, { orderId: created.id, accessKey: created.accessKey });
  save();
  // J4-T07: until the payment is confirmed the print gives no originals.
  expect((await body(await page.request.get(files, { headers }))).data.state).toBe('unpaid');
  const request = (data: Record<string, unknown>, status = 202) =>
    page.request.post(downloads, { headers: { ...headers, 'Idempotency-Key': key() }, data }).then((response) => body(response, status));
  expect((await request({ kind: 'zip' }, 409)).error.code).toBe('FILES_UNAVAILABLE');

  // J4-DEC-04: the stand pays the order through the G1 code with the fixture provider; the runner watches this request.
  writeFileSync('var/j4-pay-request.json', JSON.stringify({ orderKey: created.accessKey }));
  await expect.poll(() => existsSync('var/j4-paid.json'), { timeout: 60000 }).toBe(true);
  expect(JSON.parse(readFileSync('var/j4-paid.json', 'utf8')).error).toBeUndefined();
  // Printed frames once each; the print threshold keeps granting the whole set of its child (J1-DEC-01).
  const expected = [frames.A1, frames.B1, ...(created.gifts.includes('A') ? [frames.A2] : [])].map((frame) => frame.photoId);
  const state = (await body(await page.request.get(files, { headers }))).data;
  expect(state.state).toBe('available');
  expect(state.items.map((item: { photoId: string }) => item.photoId).sort()).toEqual(expected.sort());
  expect((await request({ kind: 'file', photoIds: [frames.B2.photoId] }, 422)).error.code).toBe('PHOTO_NOT_ENTITLED');
  expect((await body(await page.request.get(files, { headers: asBuyer(key() + key()) }), 404)).error.code).toBe('ORDER_NOT_FOUND');

  // The single files are the exact originals of the printed frames.
  const original = async (frame: Frame) => {
    const ready = (await request({ kind: 'file', photoIds: [frame.photoId] })).data;
    expect(ready.status).toBe('ready');
    const link = (await body(await page.request.get(downloads + '/' + ready.id, { headers }))).data.contentUrl as string;
    const bytes = await (await page.request.get(link)).body();
    expect(sha256(bytes)).toBe(frame.sha256);
    return { id: ready.id as string, bytes };
  };
  const printedA = await original(frames.A1);
  const printedB = await original(frames.B1);
  record.printCopy.fileDownloadId = printedA.id;

  for (const viewport of [
    { name: 'desktop', width: 1440, height: 1000 },
    { name: 'mobile', width: 390, height: 844 }
  ]) {
    await page.setViewportSize(viewport);
    await page.goto('/orders/access/' + created.accessKey);
    const block = page.getByTestId('order-files');
    await expect(block.getByTestId('files-deadline')).toContainText('Доступны до');
    await expect(block.getByTestId('files-item')).toHaveCount(expected.length);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    await block.scrollIntoViewIfNeeded();
    await page.screenshot({
      path: testInfo.outputPath('j4-' + viewport.name + '-print-files.png'),
      fullPage: true,
      animations: 'disabled'
    });
  }
  // The archive stores the originals without compression and nothing of the neighbour.
  const [archive] = await Promise.all([
    page.waitForEvent('download', { timeout: 120000 }),
    page.getByTestId('order-files').getByTestId('files-archive').click()
  ]);
  const zip = readFileSync(await archive.path());
  expect(zip.subarray(0, 4).toString('hex')).toBe('504b0304');
  expect([zip.indexOf(printedA.bytes) > 0, zip.indexOf(printedB.bytes) > 0]).toEqual([true, true]);
  record.printCopy.archiveBytes = zip.length;
  save();
});

if (sandbox) {
  test('J1-T13: a paid bundle opens the original and a ZIP of the exact bytes', async ({ page }, testInfo) => {
    test.setTimeout(240000);
    const created = paid!;
    await payByCard(page, created);
    await page.setViewportSize({ width: 1440, height: 1000 });
    await page.goto('/orders/access/' + created.accessKey);
    const block = page.getByTestId('order-files');
    await expect(block.getByTestId('files-deadline')).toContainText('Доступны до');
    await expect(block.getByTestId('files-item')).toHaveCount(1);
    await expect(block.getByTestId('files-summary')).toContainText('1 файл');
    await page.screenshot({ path: testInfo.outputPath('j1-desktop-files-available.png'), fullPage: true, animations: 'disabled' });

    const [single] = await Promise.all([page.waitForEvent('download'), block.getByTestId('files-download').click()]);
    const original = readFileSync(await single.path());
    expect(sha256(original)).toBe(fixture.open.sha256);
    expect(single.suggestedFilename()).toMatch(/^morefoto-.+\.jpg$/);

    const [archive] = await Promise.all([page.waitForEvent('download', { timeout: 120000 }), block.getByTestId('files-archive').click()]);
    const zip = readFileSync(await archive.path());
    expect(archive.suggestedFilename()).toMatch(/^morefoto-.+\.zip$/);
    expect(zip.subarray(0, 4).toString('hex')).toBe('504b0304');
    // Stored without compression: the archive carries the original bytes unchanged.
    expect(zip.indexOf(original)).toBeGreaterThan(0);
    record.archiveBytes = zip.length;

    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/orders/access/' + created.accessKey);
    await expect(page.getByTestId('files-item')).toHaveCount(1);
    await page.getByTestId('order-files').scrollIntoViewIfNeeded();
    await page.screenshot({ path: testInfo.outputPath('j1-mobile-files-available.png'), fullPage: true, animations: 'disabled' });
    save();
  });

  test('J1-T05, J1-T08: idempotency, entitlement, link signature and a foreign key', async ({ page }) => {
    const created = paid!;
    const state = (await body(await page.request.get(files, { headers: asBuyer(created.accessKey) }))).data;
    expect(state.state).toBe('available');
    const photoId = state.items[0].photoId as string;
    expect(photoId).toBe(fixture.open.photoId);

    const requestKey = key();
    const request = (data: Record<string, unknown>, idempotency = requestKey) =>
      page.request.post(downloads, { headers: { ...asBuyer(created.accessKey), 'Idempotency-Key': idempotency }, data });
    const first = (await body(await request({ kind: 'file', photoIds: [photoId] }), 202)).data;
    expect(first.status).toBe('ready');
    expect((await body(await request({ kind: 'file', photoIds: [photoId] }), 202)).data.id).toBe(first.id);
    expect((await body(await request({ kind: 'zip' }), 409)).error.code).toBe('IDEMPOTENCY_CONFLICT');
    expect((await body(await request({ kind: 'file', photoIds: [crypto.randomUUID()] }, key()), 422)).error.code).toBe(
      'PHOTO_NOT_ENTITLED'
    );
    expect((await body(await request({ kind: 'file' }, key()), 422)).error.code).toBe('INVALID_DOWNLOAD');
    record.fileDownloadId = first.id;

    const zip = (await body(await request({ kind: 'zip' }, key()), 202)).data;
    record.archiveDownloadId = zip.id;
    save();
    const status = (id: string) => page.request.get(downloads + '/' + id, { headers: asBuyer(created.accessKey) });
    await expect.poll(async () => (await body(await status(zip.id))).data.status, { timeout: 60000 }).toBe('ready');
    const ready = (await body(await status(zip.id))).data;
    expect(ready.contentUrl).toMatch(
      new RegExp('^/api/v1/public/orders/current/downloads/' + zip.id + '/content\\?token=\\d+\\.[a-f0-9]{64}$')
    );

    const content = await page.request.get(ready.contentUrl);
    expect(content.status()).toBe(200);
    expect(content.headers()['content-disposition']).toMatch(/^attachment; filename="morefoto-.+\.zip"$/);
    expect(content.headers()['cache-control']).toContain('no-store');
    const range = await page.request.get(ready.contentUrl, { headers: { Range: 'bytes=0-3' } });
    expect(range.status()).toBe(206);
    expect((await range.body()).toString('hex')).toBe('504b0304');

    const tampered = ready.contentUrl.replace(/token=(\d+)\./, (_: string, expires: string) => 'token=' + (Number(expires) + 60) + '.');
    expect((await body(await page.request.get(tampered), 403)).error.code).toBe('INVALID_DOWNLOAD_TOKEN');
    expect((await body(await page.request.get(ready.contentUrl.replace(zip.id, first.id)), 403)).error.code).toBe('INVALID_DOWNLOAD_TOKEN');
    expect((await body(await page.request.get(downloads + '/' + zip.id + '/content'), 403)).error.code).toBe('INVALID_DOWNLOAD_TOKEN');
    expect((await body(await page.request.get(downloads + '/' + zip.id, { headers: asBuyer(key() + key()) }), 404)).error.code).toBe(
      'ORDER_NOT_FOUND'
    );
  });
}
