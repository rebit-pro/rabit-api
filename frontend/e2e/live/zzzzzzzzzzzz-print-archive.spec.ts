import { existsSync, readFileSync, renameSync, writeFileSync } from 'node:fs';
import { test, expect, type APIResponse, type Page } from '@playwright/test';
import { login, token } from './helpers.js';

// L1: the print archive of the J4 paid print order. The spec runs last in group B: closing print sales of the J4 group
// must not change any other scenario. The stand has no cron, so the runner closes the sales and runs the archive on request.
type Fixture = { print: { token: string; groupId: string } };
type Version = {
  id: string;
  number: number;
  status: string;
  lines: number;
  files: number;
  prints: number;
  products: { name: string; files: number; prints: number }[];
  archiveUrl: string | null;
  exportUrl: string;
};
type Archive = {
  state: string;
  canManage: boolean;
  outsideLines: number;
  versions: Version[];
};
const fixture = JSON.parse(readFileSync('var/e4-fixture.json', 'utf8')) as Fixture;
const groupId = fixture.print.groupId;
const production = '/api/v1/groups/' + groupId + '/production';
const key = () => crypto.randomUUID().replace(/-/g, '');
let requests = 0;

async function body(response: APIResponse, status = 200) {
  expect(response.status(), await response.text()).toBe(status);
  return response.json();
}
const bearer = async (page: Page, idempotencyKey = key()) => ({
  Authorization: 'Bearer ' + (await token(page)),
  'Idempotency-Key': idempotencyKey
});
const archive = async (page: Page): Promise<Archive> =>
  (await body(await page.request.get(production, { headers: await bearer(page) }))).data;
/** Asks the runner to act on the stand (close print sales or run app:production:run) and waits for its answer. */
async function stand(
  action:
    | 'close'
    | 'run'
    | 'institution-setup'
    | 'institution-run'
    | 'institution-add-open'
    | 'institution-close-new'
    | 'institution-revoke'
    | 'institution-grant'
): Promise<Record<string, unknown>> {
  requests += 1;
  writeFileSync(`var/l1-request-${requests}.json.tmp`, JSON.stringify({ action, groupId }));
  renameSync(`var/l1-request-${requests}.json.tmp`, `var/l1-request-${requests}.json`);
  const answer = `var/l1-answer-${requests}.json`;
  await expect.poll(() => existsSync(answer), { timeout: 90000 }).toBe(true);
  const result = JSON.parse(readFileSync(answer, 'utf8')) as Record<string, unknown>;
  expect(result.error).toBeUndefined();
  return result;
}
/** The row of the group in «Ссылки и сроки»; the desktop row and the mobile card share the group ID. */
const row = (page: Page) => page.locator('[data-row-id="' + groupId + '"]').filter({ visible: true });
async function openDialog(page: Page) {
  await page.goto('/cabinet/links?group=' + groupId);
  await row(page)
    .getByRole('button', { name: /^Действия: / })
    .click();
  await page.getByTestId('links-print-archive-open').click();
  const dialog = page.getByTestId('print-archive');
  await expect(dialog).toBeVisible();
  return dialog;
}

test.describe.configure({ mode: 'serial' });
test.use({ locale: 'ru-RU', acceptDownloads: true });

test('L1-T01, T06, T08…T11, T16: the paid print of a closed group becomes a ZIP for the lab with its own link', async ({
  page,
  browser,
  baseURL
}, testInfo) => {
  test.setTimeout(300000);
  const paid = JSON.parse(readFileSync('var/j1-files.json', 'utf8')) as {
    printCopy: { orderId: string };
  };
  expect(paid.printCopy.orderId, 'J4 must pay the print order first').not.toBe('');
  await page.setViewportSize({ width: 1440, height: 1000 });
  await login(page);

  // While print sales run there is nothing to build; other roles neither see nor build the archive.
  const open = await archive(page);
  expect([open.state, open.versions.length, open.canManage]).toEqual(['open', 0, true]);
  expect(
    (
      await body(
        await page.request.post(production + '/versions', {
          headers: await bearer(page)
        }),
        409
      )
    ).error.code
  ).toBe('PRINT_SALES_OPEN');
  expect(
    (
      await body(
        await page.request.get('/api/v1/production/groups', {
          headers: await bearer(page),
          params: { groupIds: groupId }
        })
      )
    ).data
  ).toEqual([{ groupId, state: 'open', version: null }]);
  for (const account of ['teacher', 'curator']) {
    const context = await browser.newContext({ baseURL });
    try {
      const other = await context.newPage();
      await login(other, account);
      const denied = await body(
        await other.request.post(production + '/versions', {
          headers: await bearer(other)
        }),
        403
      );
      expect(denied.error.code).toBe('FORBIDDEN');
      if (account === 'teacher')
        expect(
          (
            await body(
              await other.request.get(production, {
                headers: await bearer(other)
              }),
              403
            )
          ).error.code
        ).toBe('FORBIDDEN');
    } finally {
      await context.close();
    }
  }

  // Print sales close right after the paid order: it stays in print, the automatic build comes an hour later.
  await stand('close');
  let dialog = await openDialog(page);
  await expect(row(page).getByTestId('links-print-archive')).toHaveText('архив соберётся сам');
  await expect(dialog).toContainText('Архив соберётся сам после');
  await dialog.getByTestId('print-archive-build').click();
  await expect(dialog.getByTestId('print-archive-version')).toContainText('Собирается');
  // Groups of other scenarios may close in the same run; this one is built at least.
  expect((await stand('run')).built as number).toBeGreaterThanOrEqual(1);
  await expect(dialog.getByTestId('print-archive-version')).toContainText('Готов', { timeout: 20000 });
  await expect(dialog.getByTestId('print-archive-download')).toBeVisible();
  await expect(dialog.getByText('Архив собирается', { exact: false })).toHaveCount(0);
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
  await page.screenshot({
    path: testInfo.outputPath('l1-desktop-print-archive.png'),
    animations: 'disabled'
  });

  const ready = await archive(page);
  const version = ready.versions[0]!;
  expect([ready.state, version.number, version.status, version.files]).toEqual(['ready', 1, 'ready', version.lines]);
  expect(version.lines).toBeGreaterThanOrEqual(2);
  expect(version.prints).toBe(version.products.reduce((sum, product) => sum + product.prints, 0));
  expect(version.archiveUrl).toMatch(/^\/api\/v1\/public\/production\/archives\/[A-Za-z0-9_-]{43}$/);

  // The lab opens the archive without login; the copied link is the same absolute address.
  await page.context().grantPermissions(['clipboard-read', 'clipboard-write']);
  await dialog.getByTestId('print-archive-copy').click();
  await expect(dialog.getByRole('status').filter({ hasText: 'Ссылка на архив скопирована.' })).toBeVisible();
  expect(await page.evaluate(() => navigator.clipboard.readText())).toBe(new URL(page.url()).origin + version.archiveUrl);
  const [download] = await Promise.all([page.waitForEvent('download'), dialog.getByTestId('print-archive-download').click()]);
  expect(download.suggestedFilename()).toBe('print-' + groupId.slice(0, 8) + '-v1.zip');
  const zip = readFileSync(await download.path());
  expect(zip.subarray(0, 4).toString('hex')).toBe('504b0304');
  for (const product of version.products) expect(zip.includes(Buffer.from(product.name + '/'))).toBe(true);
  expect(zip.includes(Buffer.from('состав.csv'))).toBe(true);
  const anonymous = await page.request.get(version.archiveUrl!);
  expect([anonymous.status(), anonymous.headers()['content-type'], anonymous.headers()['cache-control']]).toEqual([
    200,
    'application/zip',
    'no-store'
  ]);
  expect((await anonymous.body()).length).toBe(zip.length);

  // PRD-07: the composition in CSV from the cabinet.
  const csv = await page.request.get(version.exportUrl, {
    headers: await bearer(page)
  });
  expect([csv.status(), csv.headers()['content-type']]).toEqual([200, 'text/csv; charset=utf-8']);
  const rows = (await csv.text()).trim().split('\r\n');
  expect(rows[0]).toContain('"Продукция";"Формат";"Файл"');
  expect(rows.length - 1).toBe(version.lines);

  // A renewed link revokes the one given to the lab; a wrong token says nothing about the reason.
  await dialog.getByTestId('print-archive-renew').click();
  await expect(dialog.getByRole('status').filter({ hasText: 'Новая ссылка готова.' })).toBeVisible();
  const renewed = (await archive(page)).versions[0]!;
  expect(renewed.archiveUrl).not.toBe(version.archiveUrl);
  for (const revoked of [
    version.archiveUrl!,
    '/api/v1/public/production/archives/' + 'A'.repeat(43),
    '/api/v1/public/production/archives/short'
  ]) {
    expect((await body(await page.request.get(revoked), 404)).error.code).toBe('ARCHIVE_NOT_FOUND');
  }
  expect((await page.request.get(renewed.archiveUrl!)).status()).toBe(200);

  // Phone: the same block fits a narrow screen.
  await page.setViewportSize({ width: 390, height: 844 });
  dialog = await openDialog(page);
  await expect(dialog.getByTestId('print-archive-version')).toContainText('Готов');
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
  await page.screenshot({
    path: testInfo.outputPath('l1-mobile-print-archive.png'),
    animations: 'disabled'
  });

  writeFileSync(
    'var/l1-archive.json',
    JSON.stringify({
      groupId,
      versionId: version.id,
      token: renewed.archiveUrl!.split('/').pop(),
      revokedToken: version.archiveUrl!.split('/').pop()
    })
  );
});

test('T09–T13/T15 institution: cumulative ZIP, previous download during a new shoot, current rights and desktop/mobile', async ({
  page,
  browser,
  baseURL
}, testInfo) => {
  test.setTimeout(300000);
  const setup = await stand('institution-setup');
  const institutionId = String(setup.institutionId);
  const endpoint = '/api/v1/institutions/' + institutionId + '/production';
  type InstitutionVersion = {
    id: string;
    number: number;
    kind: string;
    status: string;
    prints: number;
    lines: number;
    files: number;
  };
  type InstitutionState = {
    state: string;
    canManage: boolean;
    blockers: { id: string }[];
    versions: InstitutionVersion[];
  };
  const state = async (actor: Page): Promise<InstitutionState> =>
    (await body(await actor.request.get(endpoint, { headers: await bearer(actor) }))).data;
  await login(page);
  expect((await page.request.get(endpoint)).status()).toBe(401);
  expect((await state(page)).versions).toHaveLength(0);
  await stand('institution-run');
  const firstState = await state(page);
  expect(firstState.state).toBe('ready');
  const first = firstState.versions.find((version) => version.kind === 'full')!;
  expect(first.lines).toBeGreaterThanOrEqual(4);

  await page.setViewportSize({ width: 1440, height: 1000 });
  await page.goto('/cabinet/institutions/' + institutionId);
  await page.getByRole('button', { name: 'Архив с печатью', exact: true }).click();
  let dialog = page.getByTestId('institution-print-archive');
  await expect(dialog.getByTestId('institution-archive-version')).toContainText('Полный архив');
  const [download] = await Promise.all([page.waitForEvent('download'), dialog.getByTestId('institution-archive-download').click()]);
  expect(download.suggestedFilename()).toBe('institution-print-' + institutionId.slice(0, 8) + '-v1.zip');
  const firstZip = readFileSync(await download.path());
  expect(firstZip.subarray(0, 4).toString('hex')).toBe('504b0304');
  expect(firstZip.includes(Buffer.from('Архив учреждения E2E/Группа архива 1/'))).toBe(true);
  expect(firstZip.includes(Buffer.from('Первая съёмка архива/'))).toBe(false);

  const added = await stand('institution-add-open');
  await dialog.getByRole('button', { name: 'Обновить архив учреждения' }).click();
  await expect(dialog.getByTestId('institution-archive-blockers')).toContainText('Новая группа архива');
  await expect(dialog.getByTestId('institution-archive-previous')).toBeVisible();
  await expect(dialog.getByTestId('institution-archive-download')).toBeVisible();
  expect(
    (
      await body(
        await page.request.post(endpoint + '/supplements', {
          headers: await bearer(page)
        }),
        409
      )
    ).error.code
  ).toBe('PRINT_SALES_OPEN');
  const previous = (await state(page)).versions[0]!;
  expect([previous.id, previous.prints, previous.status]).toEqual([first.id, first.prints, 'ready']);

  for (const account of ['curator', 'c4-curator', 'teacher', 'head']) {
    const context = await browser.newContext({ baseURL });
    try {
      const actor = await context.newPage();
      await login(actor, account);
      if (account === 'curator') {
        expect((await state(actor)).canManage).toBe(false);
        expect(
          (
            await actor.request.post(endpoint + '/supplements', {
              headers: await bearer(actor)
            })
          ).status()
        ).toBe(403);
        expect(
          (
            await actor.request.post('/api/v1/production/institution-archives/' + first.id + '/renewal', { headers: await bearer(actor) })
          ).status()
        ).toBe(403);
        const link = (
          await body(
            await actor.request.post('/api/v1/production/institution-archives/' + first.id + '/download', { headers: await bearer(actor) })
          )
        ).data as { url: string };
        expect((await actor.request.get(link.url)).status()).toBe(200);
        await stand('institution-revoke');
        expect((await actor.request.get(link.url)).status()).toBe(404);
        await stand('institution-grant');
      } else {
        expect((await actor.request.get(endpoint, { headers: await bearer(actor) })).status()).toBe(account === 'c4-curator' ? 404 : 403);
      }
    } finally {
      await context.close();
    }
  }

  await stand('institution-close-new');
  await stand('institution-run');
  const updated = await state(page);
  expect(updated.state).toBe('ready');
  const full = updated.versions.filter((version) => version.kind === 'full');
  expect(full).toHaveLength(2);
  expect(full[1]!.prints).toBe(first.prints + first.prints / 2);
  expect(full[0]!.id).toBe(first.id);
  await stand('institution-run');
  expect((await state(page)).versions).toHaveLength(2);
  await dialog.getByRole('button', { name: 'Обновить архив учреждения' }).click();
  await expect(dialog.getByTestId('institution-archive-version')).toHaveCount(2);
  await expect(
    dialog
      .getByText('Содержит прежнюю печать вместе с новыми заданиями.', {
        exact: false
      })
      .first()
  ).toBeVisible();
  expect(await dialog.evaluate((element) => element.scrollWidth <= element.clientWidth)).toBe(true);
  await page.screenshot({
    path: testInfo.outputPath('institution-desktop-print-archive.png'),
    animations: 'disabled'
  });
  await dialog.getByRole('button', { name: 'Закрыть', exact: true }).click();
  await page.setViewportSize({ width: 390, height: 844 });
  await page.getByRole('button', { name: 'Архив с печатью', exact: true }).click();
  dialog = page.getByTestId('institution-print-archive');
  await expect(dialog.getByTestId('institution-archive-version')).toHaveCount(2);
  expect(await dialog.evaluate((element) => element.scrollWidth <= element.clientWidth)).toBe(true);
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
  await page.screenshot({
    path: testInfo.outputPath('institution-mobile-print-archive.png'),
    animations: 'disabled'
  });
  // These fixture orders keep the same checkout/key/consent invariants as every other order in the gate.
  const orders = JSON.parse(readFileSync('var/e5-orders.json', 'utf8')) as { orderIds: string[] };
  for (const id of added.orderIds as string[]) if (!orders.orderIds.includes(id)) orders.orderIds.push(id);
  writeFileSync('var/e5-orders.json', JSON.stringify(orders));
  writeFileSync(
    'var/institution-production.json',
    JSON.stringify({
      institutionId,
      firstId: first.id,
      latestId: full[1]!.id,
      firstPrints: first.prints
    })
  );
});
