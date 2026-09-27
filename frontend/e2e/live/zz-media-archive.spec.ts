import { test, expect, type APIResponse, type Page, type Request, type TestInfo } from '@playwright/test';
import { crc32, login, pngVariant, token } from './helpers.js';

const problems = new WeakMap<Page, string[]>();

/** A stored ZIP, as the shoot archives are packed: every entry is a plain slice of the file. */
function zip(files: Record<string, Buffer>): Buffer {
  const u16 = (value: number) => Buffer.from([value & 0xff, value >>> 8]);
  const u32 = (value: number) => {
    const bytes = Buffer.alloc(4);
    bytes.writeUInt32LE(value);
    return bytes;
  };
  const locals: Buffer[] = [];
  const centrals: Buffer[] = [];
  let offset = 0;
  for (const [path, data] of Object.entries(files)) {
    const name = Buffer.from(path, 'utf8');
    const crc = crc32(data);
    const common = [u16(20), u16(0x800), u16(0), u32(0), u32(crc), u32(data.length), u32(data.length), u16(name.length), u16(0)];
    const local = Buffer.concat([u32(0x04034b50), ...common, name, data]);
    centrals.push(Buffer.concat([u32(0x02014b50), u16(20), ...common, u16(0), u16(0), u16(0), u32(0), u32(offset), name]));
    locals.push(local);
    offset += local.length;
  }
  const directory = Buffer.concat(centrals);
  const count = u16(centrals.length);
  const end = Buffer.concat([u32(0x06054b50), u16(0), u16(0), count, count, u32(directory.length), u32(offset), u16(0)]);
  return Buffer.concat([...locals, directory, end]);
}

async function result(response: APIResponse, status: number) {
  expect(response.status(), await response.text()).toBe(status);
  return response.json();
}

async function headers(page: Page) {
  return { Authorization: 'Bearer ' + (await token(page)), 'Idempotency-Key': crypto.randomUUID().replace(/-/g, '') };
}

async function prepareGroup(page: Page, label: string) {
  await login(page);
  const suffix = crypto.randomUUID().slice(0, 8);
  const institution = (
    await result(
      await page.request.post('/api/v1/institutions', {
        headers: await headers(page),
        data: { name: label + ' Детский сад ' + suffix, address: 'Москва' }
      }),
      201
    )
  ).data;
  const shoot = (
    await result(
      await page.request.post('/api/v1/institutions/' + institution.id + '/shoots', {
        headers: await headers(page),
        data: { name: label + ' Съёмка', date: '2026-10-20' }
      }),
      201
    )
  ).data;
  const group = (
    await result(
      await page.request.post('/api/v1/shoots/' + shoot.id + '/groups', {
        headers: await headers(page),
        data: { name: label + ' Ромашки', groupKind: 'regular' }
      }),
      201
    )
  ).data;
  const path = '/cabinet/institutions/' + institution.id + '/shoots/' + shoot.id + '/photos?group=' + group.id;
  await page.goto(path);
  await expect(page.getByText(label + ' Ромашки', { exact: true })).toBeVisible();

  return { shootId: shoot.id as string, groupId: group.id as string, path, suffix };
}

async function photosOf(page: Page, shootId: string, groupId: string, query: string) {
  return (
    await result(
      await page.request.get('/api/v1/shoots/' + shootId + '/photos?groupId=' + groupId + '&pageSize=100&' + query, {
        headers: { Authorization: 'Bearer ' + (await token(page)) }
      }),
      200
    )
  ).data;
}

async function screenshot(page: Page, testInfo: TestInfo, name: string) {
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBe(true);
  await testInfo.attach(name, { body: await page.screenshot({ fullPage: true }), contentType: 'image/png' });
}

function uploadCounter(page: Page, shootId: string) {
  const sent: string[] = [];
  page.on('request', (request: Request) => {
    if (request.method() === 'POST' && new URL(request.url()).pathname === '/api/v1/shoots/' + shootId + '/photos')
      sent.push(request.url());
  });
  return sent;
}

test.beforeEach(async ({ page, request }) => {
  expect(await (await request.get('/__e2e')).json()).toEqual({ fixture: 'rabit-real-e2e' });
  const errors: string[] = [];
  problems.set(page, errors);
  page.on('pageerror', (error) => errors.push(error.message));
  page.on('console', (message) => {
    if (message.type() === 'error' && !message.text().startsWith('Failed to load resource:')) errors.push(message.text());
  });
});

test.afterEach(async ({ page }) => {
  expect(problems.get(page)).toEqual([]);
});

test('#150: архивы по папкам детей размечаются сами, групповые кадры достаются всем детям', async ({ page }, testInfo) => {
  test.setTimeout(180000);
  await page.setViewportSize({ width: 1440, height: 1000 });
  const { shootId, groupId, path, suffix } = await prepareGroup(page, 'Z150');
  const parts = [
    {
      name: 'z150_part01.zip',
      mimeType: 'application/zip',
      buffer: zip({
        'A/A001.png': pngVariant(suffix + '-a1'),
        'A/A002.png': pngVariant(suffix + '-a2'),
        'B/B001.png': pngVariant(suffix + '-b1'),
        'A/notes.txt': Buffer.from('not a photo')
      })
    },
    {
      name: 'z150_part02.zip',
      mimeType: 'application/zip',
      buffer: zip({ 'F/F001.png': pngVariant(suffix + '-f1'), 'GROUP-1/G001.png': pngVariant(suffix + '-g1') })
    }
  ];
  const sent = uploadCounter(page, shootId);

  await page.getByTestId('upload-mode-archive').click();
  await page.locator('input[type="file"][aria-label="Выбрать ZIP-архивы"]').setInputFiles(parts);
  const plan = page.getByTestId('archive-plan');
  await expect(plan.getByTestId('archive-summary')).toContainText('2 архива · 5 фото');
  await expect(plan.locator('[data-folder="F"]')).toContainText('Ребёнок F');
  await expect(plan.locator('[data-folder="GROUP-1"]')).toContainText('Всем детям группы');
  await expect(plan).toContainText('Не будут загружены: 1');
  // Shoot 158 marked its group folders only in the CSV: the switch fixes such a folder without repacking.
  await plan.getByLabel('Групповые кадры: папка F').click();
  await expect(plan.locator('[data-folder="F"]')).toContainText('Всем детям группы');
  await expect(plan.getByTestId('archive-summary')).toContainText('детей: 2 · групповых папок: 2 (2 фото, получат детей: 2)');
  await screenshot(page, testInfo, 'archive-plan-desktop');

  await plan.getByTestId('archive-start').click();
  const progress = page.getByTestId('archive-progress');
  await expect(progress).toContainText('Отправлено 5 из 5', { timeout: 60000 });
  await expect(progress).toContainText('Превью готовы 5 из 5', { timeout: 60000 });
  expect(sent).toHaveLength(5);
  await expect(page.locator('[data-upload-id]').filter({ hasText: 'G001.png' })).toContainText('Групповой кадр · детей: 2');
  await screenshot(page, testInfo, 'archive-progress-desktop');

  const childA = await photosOf(page, shootId, groupId, 'childCode=A');
  const childB = await photosOf(page, shootId, groupId, 'childCode=B');
  const unassigned = await photosOf(page, shootId, groupId, 'assigned=false');
  expect(childA.summary.children).toEqual(['A', 'B']);
  expect(unassigned.items).toHaveLength(0);
  // Frames of a child keep the archive order, group frames close every child's set.
  const codes = (list: { items: { filename: string; assignments: { childCode: string; code: string }[] }[] }, child: string) =>
    Object.fromEntries(list.items.map((item) => [item.filename, item.assignments.find((entry) => entry.childCode === child)?.code]));
  expect(codes(childA, 'A')).toEqual({ 'A001.png': 'A001', 'A002.png': 'A002', 'F001.png': 'A003', 'G001.png': 'A004' });
  expect(codes(childB, 'B')).toEqual({ 'B001.png': 'B001', 'F001.png': 'B002', 'G001.png': 'B003' });

  // A child labelled later gets the group frames when the same archives are chosen again; own frames are not resent.
  const own = await page.request.post('/api/v1/shoots/' + shootId + '/photos', {
    headers: { Authorization: 'Bearer ' + (await token(page)) },
    multipart: { groupId, childCodes: 'C', file: { name: 'C001.png', mimeType: 'image/png', buffer: pngVariant(suffix + '-c1') } }
  });
  const accepted = await result(own, 202);
  expect(accepted.data.childCodes).toEqual(['C']);
  await expect
    .poll(async () => (await photosOf(page, shootId, groupId, 'childCode=C')).summary.children, { timeout: 30000 })
    .toEqual(['A', 'B', 'C']);
  // A new tab has no queue draft, as after a closed tab: the browser progress still skips the accepted own frames.
  const again = await page.context().newPage();
  const resent = uploadCounter(again, shootId);
  await again.goto(path);
  await again.getByTestId('upload-mode-archive').click();
  await again.locator('input[type="file"][aria-label="Выбрать ZIP-архивы"]').setInputFiles(parts);
  const replan = again.getByTestId('archive-plan');
  await replan.getByLabel('Групповые кадры: папка F').click();
  await expect(replan.getByTestId('archive-summary')).toContainText('получат детей: 3');
  await replan.getByTestId('archive-start').click();
  const reprogress = again.getByTestId('archive-progress');
  await expect(reprogress).toContainText('Отправлено ранее и пропущено: 3');
  await expect(reprogress).toContainText('Превью готовы 5 из 5', { timeout: 60000 });
  expect(resent).toHaveLength(2);
  const childC = await photosOf(page, shootId, groupId, 'childCode=C');
  expect(codes(childC, 'C')).toEqual({ 'C001.png': 'C001', 'F001.png': 'C002', 'G001.png': 'C003' });
  // Every repeated send leaves a duplicate record; the frames themselves stay six.
  expect((await photosOf(page, shootId, groupId, 'status=ready')).meta.total).toBe(6);
  expect((await photosOf(page, shootId, groupId, 'status=duplicate')).meta.total).toBe(2);
});

test('#150: сбои сервера и связи повторяются сами, экран сверки помещается на телефоне', async ({ page }, testInfo) => {
  test.setTimeout(180000);
  await page.setViewportSize({ width: 390, height: 844 });
  const { shootId, groupId, suffix } = await prepareGroup(page, 'Z150R');
  const archive = {
    name: 'z150_retry.zip',
    mimeType: 'application/zip',
    buffer: zip({ 'A/1.png': pngVariant(suffix + '-r1'), 'B/1.png': pngVariant(suffix + '-r2'), 'А/1.png': pngVariant(suffix + '-r3') })
  };
  await page.getByTestId('upload-mode-archive').click();
  await page.locator('input[type="file"][aria-label="Выбрать ZIP-архивы"]').setInputFiles(archive);
  const plan = page.getByTestId('archive-plan');
  await expect(plan.locator('[data-folder="А"]')).toContainText('набрано кириллицей. Нужна латинская буква: A.');
  await expect(plan.getByTestId('archive-start')).toBeDisabled();
  await screenshot(page, testInfo, 'archive-plan-mobile-problem');

  const fixed = { ...archive, buffer: zip({ 'A/1.png': pngVariant(suffix + '-r1'), 'B/1.png': pngVariant(suffix + '-r2') }) };
  await plan.getByRole('button', { name: 'Выбрать другие архивы' }).click();
  await page.locator('input[type="file"][aria-label="Выбрать ZIP-архивы"]').setInputFiles(fixed);
  await screenshot(page, testInfo, 'archive-plan-mobile');

  // The first two sends of the queue meet a busy server; the queue repeats them without a click.
  let failures = 0;
  await page.route('**/api/v1/shoots/' + shootId + '/photos', async (route) => {
    if (route.request().method() === 'POST' && failures < 2) {
      failures++;
      await route.fulfill({ status: 503, contentType: 'application/json', body: '{"error":{"code":"SERVICE_UNAVAILABLE"}}' });
      return;
    }
    await route.fallback();
  });
  await plan.getByTestId('archive-start').click();
  await expect(page.locator('[data-upload-id]').first()).toContainText('Сервер не ответил. Повторим через 2 с');
  const progress = page.getByTestId('archive-progress');
  await expect(progress).toContainText('Превью готовы 2 из 2', { timeout: 60000 });
  expect(failures).toBe(2);
  await screenshot(page, testInfo, 'archive-progress-mobile');
  expect((await photosOf(page, shootId, groupId, 'assigned=false')).items).toHaveLength(0);
});
