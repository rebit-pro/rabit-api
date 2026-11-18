import { test, expect, type APIResponse, type Page, type Request, type TestInfo } from '@playwright/test';
import { command, link } from './f2-links.js';
import { login, pngVariant, storedZip as zip, token } from './helpers.js';

const problems = new WeakMap<Page, string[]>();

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

test('ZIP учреждения: два класса, возобновление и общая ссылка одной съёмки', async ({ page, context, browser }, testInfo) => {
  test.setTimeout(180000);
  page.setDefaultTimeout(12000);
  await login(page);
  const tag = 'ZIP ' + crypto.randomUUID().slice(0, 8);
  async function create(path: string, data: Record<string, unknown>) {
    return (await result(await page.request.post('/api/v1/' + path, { headers: await headers(page), data }), 201)).data.id as string;
  }
  const institution = await create('institutions', { name: tag, address: 'Москва' });
  const path = '/cabinet/institutions/' + institution;
  await page.goto(path);
  await page.getByRole('button', { name: 'Загрузить ZIP по группам' }).click();
  await expect(page.getByRole('dialog').getByRole('button', { name: 'Новая съёмка' })).toBeVisible();
  await page.getByRole('dialog').getByRole('button', { name: 'Закрыть' }).click();
  const shoot = await create('institutions/' + institution + '/shoots', { name: 'Весна ' + tag, date: '2026-04-02' });
  const other = await create('institutions/' + institution + '/shoots', { name: 'Осень ' + tag, date: '2026-09-10' });
  const group = await create('shoots/' + shoot + '/groups', { name: '1А', groupKind: 'regular' });
  const foreign = await create('shoots/' + other + '/groups', { name: '1Б', groupKind: 'regular' });
  const endpoint = '/api/v1/institutions/' + institution + '/shoots/' + shoot + '/import-groups';
  const post = async (groups: unknown[], key: string) =>
    page.request.post(endpoint, { headers: { ...(await headers(page)), 'Idempotency-Key': key }, data: { groups } });
  const payload = ['1А', '7А'];
  const firstKey = crypto.randomUUID().replace(/-/g, '');
  const [one, two] = await Promise.all([post(payload, firstKey), post(payload, firstKey)]);
  const synced = (await result(one, 200)).data.groupIds as string[];
  expect((await result(two, 200)).data.groupIds).toEqual(synced);
  const independent = (await result(await post(payload, crypto.randomUUID().replace(/-/g, '')), 200)).data.groupIds as string[];
  expect(new Set([group, ...synced, ...independent]).size).toBe(5);
  expect((await post([{ name: '1Б', groupId: foreign }], crypto.randomUUID().replace(/-/g, ''))).status()).toBe(422);
  expect((await post(['8Г'], firstKey)).status()).toBe(409);
  const input = {
    name: 'Школа.zip',
    mimeType: 'application/zip',
    buffer: zip({
      '1А/A/Аня.png': pngVariant(tag + 'a'),
      '1А/GROUP1/Класс.png': pngVariant(tag + 'all'),
      '1Б/A/Дима.png': pngVariant(tag + 'b'),
      '1Б/заметки.txt': Buffer.from('note')
    })
  };
  async function choose() {
    await page.goto(path);
    await page.getByRole('button', { name: 'Загрузить ZIP по группам' }).click();
    const wizard = page.getByRole('dialog');
    await wizard.getByRole('combobox', { name: 'Съёмка', exact: true }).press('Enter');
    await page.getByRole('option', { name: /Весна/ }).click();
    const fileChooser = page.waitForEvent('filechooser');
    await wizard.getByRole('button', { name: 'Выбрать ZIP', exact: true }).click();
    await (await fileChooser).setFiles(input);
    await expect(wizard.getByText('1А', { exact: true })).toBeVisible();
    return wizard;
  }
  let wizard = await choose();
  await expect(wizard.getByRole('button', { name: 'Подтвердить и загрузить' })).toBeEnabled();
  await wizard.getByRole('button', { name: 'Закрыть', exact: true }).click();
  const listGroups = async () =>
    (await result(await page.request.get('/api/v1/shoots/' + shoot + '?pageSize=100', { headers: await headers(page) }), 200)).data.groups
      .items as { name: string; id: string }[];
  expect((await listGroups()).some((g) => g.name === '1Б')).toBe(false);
  wizard = await choose();
  await screenshot(page, testInfo, 'institution-zip-review');
  await page.setViewportSize({ width: 390, height: 844 });
  await screenshot(page, testInfo, 'institution-zip-mobile');
  await page.setViewportSize({ width: 1280, height: 800 });
  const preview = wizard.locator('.import-row').filter({ has: page.getByText('1А', { exact: true }) });
  await preview.getByText('Серии и групповые кадры', { exact: false }).click();
  await preview.getByText('A · 1 фото', { exact: true }).click();
  await expect(preview.getByText('Аня.png', { exact: true })).toBeVisible();
  const createdGroups = () =>
    page.waitForResponse((response) => response.url().endsWith(endpoint) && response.request().method() === 'POST');
  const created = createdGroups();
  await wizard.getByRole('button', { name: 'Подтвердить и загрузить' }).click();
  const [first, second] = (await (await created).json()).data.groupIds as [string, string];
  expect(new Set([group, foreign, first, second, ...synced, ...independent]).size).toBe(8);
  await expect(wizard).toContainText('Превью готовы: 3 из 3', { timeout: 60000 });
  expect((await photosOf(page, shoot, group, '')).items).toHaveLength(0);
  expect((await photosOf(page, shoot, first, 'childCode=A')).items.map((p: { filename: string }) => p.filename).sort()).toEqual(
    ['Аня.png', 'Класс.png'].sort()
  );
  expect((await photosOf(page, shoot, second, 'childCode=A')).items.map((p: { filename: string }) => p.filename)).toEqual(['Дима.png']);
  expect((await photosOf(page, other, foreign, '')).items).toHaveLength(0);
  await wizard.getByRole('button', { name: 'Закрыть', exact: true }).click();
  wizard = await choose();
  const sent = uploadCounter(page, shoot);
  const resumed = createdGroups();
  await wizard.getByRole('button', { name: 'Подтвердить и загрузить' }).click();
  await expect(wizard).toContainText('Превью готовы: 3 из 3', { timeout: 60000 });
  expect((await (await resumed).json()).data.groupIds).toEqual([first, second]);
  expect(sent).toHaveLength(1);
  await wizard.getByRole('button', { name: 'Закрыть', exact: true }).click();
  const product = await create('catalog/products', {
    name: tag,
    description: '',
    kind: 'physical',
    price: 15000,
    printCount: 1,
    format: '10×15',
    unit: 'шт.',
    staffDiscount: false,
    active: true
  });
  const readLink = (id: string) => link(page, id);
  const prepare = async (id: string) => {
    const current = await readLink(id);
    await command(page, id, 'link-preparations', {
      revision: current.revision,
      signature: current.signature,
      photosReviewed: true,
      conditionsReviewed: true,
      staffReviewed: true,
      confirmed: true
    });
  };
  const openWithPortrait = async (shootId: string, groupId: string) => {
    await result(
      await page.request.post('/api/v1/shoots/' + shootId + '/photos', {
        headers: await headers(page),
        multipart: {
          groupId,
          childCodes: 'A',
          file: { name: 'Портрет.png', mimeType: 'image/png', buffer: pngVariant(tag + groupId) }
        }
      }),
      202
    );
    await expect.poll(async () => (await readLink(groupId)).problems, { timeout: 45000 }).toEqual([]);
    await prepare(groupId);
    const prepared = await readLink(groupId);
    const minute = new Date(Date.now() + 3 * 3600000).toISOString().slice(0, 16) + ':00+03:00';
    await command(page, groupId, 'link-transmissions', {
      revision: prepared.revision,
      signature: prepared.signature,
      sentAt: minute,
      confirmed: true
    });
  };
  await openWithPortrait(other, foreign);
  await prepare(first);
  await prepare(second);
  const current = await readLink(second);
  const openAt = new Date(Date.now() + 86400000 + 3 * 3600000).toISOString().slice(0, 19) + '+03:00';
  await command(page, second, 'opening-schedules', { revision: current.revision, signature: current.signature, openAt, confirmed: true });
  await context.grantPermissions(['clipboard-read', 'clipboard-write']);
  await page.goto('/cabinet/links?shoot=' + shoot);
  const shootRow = page.locator('tr[data-row-id="' + shoot + '"]');
  await expect(shootRow.locator('strong')).toHaveText('Весна ' + tag);
  await expect(shootRow.locator('small')).toHaveText(tag);
  const scheduledActions = page.locator('tr[data-row-id="' + second + '"] .links-actions');
  expect(await scheduledActions.evaluate((element) => element.scrollWidth <= element.clientWidth + 1)).toBe(true);
  await screenshot(page, testInfo, 'institution-links-desktop');
  await shootRow.getByRole('button', { name: 'Скопировать общую ссылку' }).click();
  await expect(page.getByRole('status').filter({ hasText: 'Скопировано ссылок: 1.' })).toBeVisible();
  const url = await page.evaluate(() => navigator.clipboard.readText());
  expect(new URL(url).pathname).toMatch(/^\/g\/s\/[a-f0-9]{32}$/);
  const publicApi = '/api/v1/public/galleries/shoots/' + url.split('/').pop();
  const guest = await browser.newContext();
  try {
    const parent = await guest.newPage();
    await parent.goto(url);
    await expect(parent.getByRole('heading', { name: 'Фотографии ещё готовятся' })).toBeVisible();
    expect((await readLink(first)).sentAt).toBeNull();
    await shootRow.getByRole('button', { name: 'Отметить передачу' }).click();
    const dialog = page.getByRole('dialog');
    await expect(dialog).toContainText('Требует проверки');
    await expect(dialog).toContainText('Открытие по расписанию');
    await dialog.getByRole('checkbox').check();
    // Сервер принял первую передачу, но браузер потерял ответ.
    await page.route(
      '**/link-transmissions',
      async (route) => {
        expect((await route.fetch()).status()).toBe(200);
        await route.abort('failed');
      },
      { times: 1 }
    );
    await dialog.getByRole('button', { name: /Отметить передачу/ }).click();
    await expect(dialog.locator('li').filter({ hasText: 'Передача отмечена' })).toHaveCount(1);
    await dialog.getByRole('button', { name: /Повторить передачу/ }).click();
    await expect(dialog.locator('li').filter({ hasText: 'Передача отмечена' })).toHaveCount(2);
    await dialog.getByRole('button', { name: 'Закрыть' }).click();
    expect((await readLink(second)).sentAt).toBeNull();
    expect((await readLink(second)).manualSentAt).toBeTruthy();
    await parent.reload();
    const tile = parent.getByRole('link', { name: '1А', exact: true });
    await expect(tile).toBeVisible();
    await expect(parent.getByRole('link', { name: '1Б', exact: true })).toHaveCount(0);
    await expect.poll(() => tile.locator('img').evaluate((img: HTMLImageElement) => img.naturalWidth)).toBeGreaterThan(0);
    const foreignLink = await readLink(foreign);
    expect(foreignLink.sentAt).toBeTruthy();
    const otherGallery = await result(
      await parent.request.get(new URL('/api/v1/public/galleries/' + foreignLink.galleryAlias, url).href),
      200
    );
    expect(otherGallery.data.groupId).toBe(foreign);
    const branch = (await result(await parent.request.get(new URL(publicApi, url).href), 200)).data;
    expect(branch.groups.map((g: { name: string }) => g.name)).toEqual(['1А']);
    for (const id of [second, foreign])
      expect((await parent.request.get(new URL(publicApi + '/groups/' + id + '/cover', url).href)).status()).toBe(404);
    await screenshot(parent, testInfo, 'institution-gallery-desktop');
    await parent.setViewportSize({ width: 390, height: 844 });
    await screenshot(parent, testInfo, 'institution-gallery-mobile');
    await tile.click();
    await expect(parent).toHaveURL(new URL(branch.groups[0].groupPath, url).href);
    await expect(parent.getByRole('link', { name: 'К группам' })).toBeVisible();
    await parent.getByRole('link', { name: 'Открыть корзину' }).click();
    await parent.getByRole('link', { name: 'К фотографиям' }).first().click();
    await expect(parent.getByRole('link', { name: 'К группам' })).toBeVisible();
    const direct = await readLink(first);
    expect(direct.history?.filter((e) => e.kind === 'transmitted')).toHaveLength(1);
    for (const key of [direct.galleryAlias, direct.galleryToken]) {
      const snapshot = (await result(await parent.request.get(new URL('/api/v1/public/galleries/' + key, url).href), 200)).data;
      expect(snapshot.groupId).toBe(first);
    }
    expect((await parent.request.get(new URL('/api/v1/public/galleries/' + url.split('/').pop() + '/catalog', url).href)).status()).toBe(
      404
    );
    await parent.getByRole('link', { name: 'К группам' }).click();
    await expect(parent).toHaveURL(url);
    await openWithPortrait(shoot, synced[1]!);
    await parent.reload();
    await expect(parent.getByRole('link', { name: '7А', exact: true })).toBeVisible();
    await expect(parent.getByRole('link', { name: '1А', exact: true })).toBeVisible();
    await expect(parent.getByRole('link', { name: '1Б', exact: true })).toHaveCount(0);
    await screenshot(parent, testInfo, 'two-classes-mobile');
    await parent.setViewportSize({ width: 1280, height: 800 });
    await screenshot(parent, testInfo, 'two-classes-desktop');
    await page.setViewportSize({ width: 390, height: 844 });
    await expect
      .poll(() =>
        page.evaluate(() => {
          const width = document.documentElement.clientWidth;
          return document.documentElement.scrollWidth <= width
            ? []
            : Array.from(document.querySelectorAll('main *'))
                .filter((el) => el.getBoundingClientRect().right > width + 1)
                .slice(0, 12)
                .map((el) => el.tagName + '.' + el.className);
        })
      )
      .toEqual([]);
    await screenshot(page, testInfo, 'institution-links-mobile');
  } finally {
    await guest.close();
  }
  expect((await page.request.delete('/api/v1/institutions/' + institution, { headers: await headers(page) })).status()).toBe(204);
  expect((await page.request.get(publicApi)).status()).toBe(404);
  expect((await page.request.delete('/api/v1/catalog/products/' + product, { headers: await headers(page) })).status()).toBe(204);
});

test('ZIP учреждения: новые одноимённые группы, общий кадр для 101 серии и межгрупповой дубль', async ({ page, browser }, testInfo) => {
  test.setTimeout(240000);
  page.setDefaultTimeout(12000);
  const fixture = await prepareGroup(page, 'ZIP101');
  const institution = fixture.path.split('/')[3]!;
  const name = 'ZIP101 Ромашки';
  const groupPath = '/api/v1/shoots/' + fixture.shootId + '/groups';
  const addGroup = async (label: string, groupKind = 'regular') =>
    (await result(await page.request.post(groupPath, { headers: await headers(page), data: { name: label, groupKind } }), 201)).data
      .id as string;
  const peer = await addGroup(name),
    otherName = '1Б';
  const previousSecond = await addGroup(otherName);
  const staff = await addGroup('Сотрудники', 'staff');
  const endpoint = '/api/v1/institutions/' + institution + '/shoots/' + fixture.shootId + '/import-groups';
  for (const groups of [[{ name, groupId: null }], [{ name: 'Сотрудники', groupId: staff }]]) {
    expect((await page.request.post(endpoint, { headers: await headers(page), data: { groups } })).status()).toBe(422);
  }
  const role = await browser.newContext({ baseURL: new URL(page.url()).origin });
  try {
    const teacher = await role.newPage();
    await login(teacher, 'teacher');
    const h = await headers(teacher);
    expect((await teacher.request.post(endpoint, { headers: h, data: { groups: [name] } })).status()).toBe(403);
    expect((await teacher.request.get('/api/v1/shoots/' + fixture.shootId + '/gallery-link', { headers: h })).status()).toBe(403);
  } finally {
    await role.close();
  }
  const codes = Array.from({ length: 100 }, (_, i) => String.fromCharCode(65 + Math.floor(i / 26), 65 + (i % 26)));
  const source = pngVariant(fixture.suffix + 'source');
  const files: Record<string, Buffer> = {};
  for (const [index, code] of [...codes, 'ZZZ'].entries()) {
    files[name + '/' + code + '/портрет.png'] = index === 0 ? source : pngVariant(fixture.suffix + code);
  }
  files[name + '/GROUP1/вместе.png'] = pngVariant(fixture.suffix + 'shared');
  files[otherName + '/A/тот-же.png'] = source;
  const archive = { name: '101.zip', mimeType: 'application/zip', buffer: zip(files) };
  await page.goto('/cabinet/institutions/' + institution);
  await page.getByRole('button', { name: 'Загрузить ZIP по группам' }).click();
  const wizard = page.getByRole('dialog');
  await wizard.locator('input[type=file]').setInputFiles(archive);
  const start = wizard.getByRole('button', { name: 'Подтвердить и загрузить' });
  await expect(start).toBeEnabled();
  await expect(wizard.getByRole('combobox', { name: 'Выберите группу' })).toHaveCount(0);
  const groups = page.waitForResponse((response) => response.url().endsWith(endpoint) && response.request().method() === 'POST');
  await start.click();
  const [created, second] = (await (await groups).json()).data.groupIds as [string, string];
  expect(new Set([fixture.groupId, peer, previousSecond, staff, created, second]).size).toBe(6);
  await expect(wizard.getByRole('heading', { name: 'Результат загрузки' })).toBeVisible({ timeout: 190000 });
  await expect(wizard).toContainText('Оригинал уже находится в другом классе');
  await screenshot(page, testInfo, 'institution-import-conflict');
  expect((await photosOf(page, fixture.shootId, created, 'childCode=ZZZ')).items.map((p: { filename: string }) => p.filename)).toContain(
    'вместе.png'
  );
  expect((await photosOf(page, fixture.shootId, second, 'childCode=A')).items).toHaveLength(0);
  for (const id of [fixture.groupId, peer, previousSecond, staff])
    expect((await photosOf(page, fixture.shootId, id, '')).items).toHaveLength(0);
  await wizard.getByRole('button', { name: 'Закрыть', exact: true }).click();
  expect((await page.request.delete('/api/v1/institutions/' + institution, { headers: await headers(page) })).status()).toBe(204);
});

test('TC-13/15: удалённые группы не дают ложного успеха, новый импорт переживает потерю ответа', async ({ page }, testInfo) => {
  test.setTimeout(150000);
  await login(page);
  const tag = crypto.randomUUID();
  const create = async (path: string, data: Record<string, string>) =>
    (await result(await page.request.post('/api/v1/' + path, { headers: await headers(page), data }), 201)).data.id as string;
  const institution = await create('institutions', { name: 'Повтор ZIP ' + tag, address: 'Москва' });
  const shoot = await create('institutions/' + institution + '/shoots', { name: 'Весна', date: '2026-04-02' });
  const path = '/cabinet/institutions/' + institution;
  const endpoint = '/api/v1/institutions/' + institution + '/shoots/' + shoot + '/import-groups';
  const archive = {
    name: 'Классы.zip',
    mimeType: 'application/zip',
    buffer: zip({ '1А/A/портрет.png': pngVariant(tag + 'a'), '1Б/A/портрет.png': pngVariant(tag + 'b') })
  };
  const choose = async () => {
    await page.goto(path);
    await page.getByRole('button', { name: 'Загрузить ZIP по группам' }).click();
    const wizard = page.getByRole('dialog');
    await wizard.locator('input[type=file]').setInputFiles(archive);
    await expect(wizard.getByRole('button', { name: 'Подтвердить и загрузить' })).toBeEnabled();
    return wizard;
  };
  let wizard = await choose();
  const first = page.waitForResponse((response) => response.url().endsWith(endpoint));
  await wizard.getByRole('button', { name: 'Подтвердить и загрузить' }).click();
  const original = (await (await first).json()).data.groupIds as string[];
  await expect(wizard).toContainText('Превью готовы: 2 из 2', { timeout: 60000 });
  await wizard.getByRole('button', { name: 'Закрыть', exact: true }).click();
  expect((await page.request.delete('/api/v1/groups/' + original[0], { headers: await headers(page) })).status()).toBe(204);
  wizard = await choose();
  const refused = page.waitForResponse((response) => response.url().endsWith(endpoint));
  await wizard.getByRole('button', { name: 'Подтвердить и загрузить' }).click();
  expect((await refused).status()).toBe(409);
  await expect(wizard).toContainText('Часть групп предыдущего импорта больше недоступна');
  await expect(wizard.getByText('Превью готовы:', { exact: false })).toHaveCount(0);
  expect((await photosOf(page, shoot, original[1]!, '')).meta.total).toBe(1);
  await screenshot(page, testInfo, 'removed-import-desktop');
  await page.setViewportSize({ width: 390, height: 844 });
  await screenshot(page, testInfo, 'removed-import-mobile');
  await page.setViewportSize({ width: 1280, height: 800 });
  expect((await page.request.delete('/api/v1/groups/' + original[1], { headers: await headers(page) })).status()).toBe(204);
  let replacement: string[] = [];
  let replacementKey = '';
  await page.route(
    '**' + endpoint,
    async (route) => {
      replacementKey = route.request().headers()['idempotency-key']!;
      const response = await route.fetch();
      expect(response.status()).toBe(200);
      replacement = (await response.json()).data.groupIds;
      await route.abort('failed');
    },
    { times: 1 }
  );
  await wizard.getByRole('button', { name: 'Начать новый импорт' }).click();
  await expect(wizard.getByRole('button', { name: 'Подтвердить и загрузить' })).toBeEnabled();
  // Повтор через два HTTP-запроса использует настоящий Bearer, без повторного нажатия «новый импорт».
  const h = { Authorization: 'Bearer ' + (await token(page)), 'Idempotency-Key': replacementKey };
  const repeated = await Promise.all([0, 1].map(() => page.request.post(endpoint, { headers: h, data: { groups: ['1А', '1Б'] } })));
  for (const response of repeated) expect((await result(response, 200)).data.groupIds).toEqual(replacement);
  await wizard.getByRole('button', { name: 'Закрыть', exact: true }).click();
  wizard = await choose();
  const restored = page.waitForResponse((response) => response.url().endsWith(endpoint));
  await wizard.getByRole('button', { name: 'Подтвердить и загрузить' }).click();
  expect((await (await restored).json()).data.groupIds).toEqual(replacement);
  expect(replacement.every((id) => !original.includes(id))).toBe(true);
  await expect(wizard).toContainText('Превью готовы: 2 из 2', { timeout: 60000 });
  const snapshot = (await result(await page.request.get('/api/v1/shoots/' + shoot, { headers: h }), 200)).data;
  expect(snapshot.groups.items).toHaveLength(2);
  await wizard.getByRole('button', { name: 'Закрыть', exact: true }).click();
  expect((await page.request.delete('/api/v1/institutions/' + institution, { headers: await headers(page) })).status()).toBe(204);
});

test('TC-14: ожидание превью между партиями защищает выход, завершение снимает защиту', async ({ page }) => {
  test.setTimeout(240000);
  await login(page);
  const tag = crypto.randomUUID();
  const create = async (path: string, data: Record<string, string>) =>
    (await result(await page.request.post('/api/v1/' + path, { headers: await headers(page), data }), 201)).data.id as string;
  const institution = await create('institutions', { name: 'Выход ZIP ' + tag, address: 'Москва' });
  const shoot = await create('institutions/' + institution + '/shoots', { name: 'Весна', date: '2026-04-02' });
  const contents: Record<string, Buffer> = {};
  for (let i = 0; i < 201; i++) contents['1А/A/' + i + '.png'] = pngVariant(tag + i);
  const path = '/cabinet/institutions/' + institution;
  await page.goto(path);
  await page.getByRole('button', { name: 'Загрузить ZIP по группам' }).click();
  const wizard = page.getByRole('dialog');
  await wizard.locator('input[type=file]').setInputFiles({ name: '201.zip', mimeType: 'application/zip', buffer: zip(contents) });
  let release!: () => void;
  const pending = new Promise<void>((resolve) => {
    release = resolve;
  });
  // Сервер обрабатывает настоящие кадры; задерживается только доставка ответов об их статусах браузеру.
  await page.route(/\/api\/v1\/photos\/[a-f0-9-]{36}$/, async (route) => {
    const response = await route.fetch();
    await pending;
    await route.fulfill({ response });
  });
  const sent = uploadCounter(page, shoot);
  const unloadProtected = () => page.evaluate(() => !window.dispatchEvent(new Event('beforeunload', { cancelable: true })));
  try {
    await wizard.getByRole('button', { name: 'Подтвердить и загрузить' }).click();
    await expect.poll(() => sent.length, { timeout: 60000 }).toBe(200);
    await expect(wizard.getByRole('button', { name: 'Приостановить', exact: true })).toHaveCount(0);
    expect(await unloadProtected()).toBe(true);
    const dialog = page.waitForEvent('dialog');
    await page.evaluate(() => window.history.back());
    await (await dialog).dismiss();
    await expect(page).toHaveURL(new RegExp(path + '$'));
    await expect(wizard).toBeVisible();
  } finally {
    release();
  }
  await expect(wizard).toContainText('Превью готовы: 201 из 201', { timeout: 180000 });
  expect(await unloadProtected()).toBe(false);
  expect(sent).toHaveLength(201);
  await wizard.getByRole('button', { name: 'Закрыть', exact: true }).click();
  expect((await page.request.delete('/api/v1/institutions/' + institution, { headers: await headers(page) })).status()).toBe(204);
});
