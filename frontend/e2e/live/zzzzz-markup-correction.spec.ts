import { readFileSync } from 'node:fs';
import { test, expect, type APIResponse } from '@playwright/test';
import { login, token } from './helpers.js';

/** D4: a transmitted group without orders; the organizer corrects its markup while the gallery pauses buying. */
type Fixture = Record<'markup', { token: string; groupId: string }>;
const fixture = (JSON.parse(readFileSync('var/e4-fixture.json', 'utf8')) as Fixture).markup;
const gallery = '/api/v1/public/galleries/' + fixture.token;

async function body(response: APIResponse, status = 200) {
  expect(response.status(), await response.text()).toBe(status);
  return response.json();
}

for (const viewport of [
  { name: 'desktop', width: 1440, height: 1000, code: 'C' },
  { name: 'mobile', width: 390, height: 844, code: 'D' }
]) {
  test('D4: the organizer corrects the markup of a transmitted group on ' + viewport.name, async ({ page, browser }, info) => {
    await page.setViewportSize(viewport);
    const errors: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));
    await login(page);
    const auth = { Authorization: 'Bearer ' + (await token(page)) };
    const link = (await body(await page.request.get('/api/v1/groups/' + fixture.groupId + '/link', { headers: auth }))).data;
    expect([link.state, link.markupCorrectable, link.markupCorrectionAt]).toEqual(['open', true, null]);
    const sentAt = link.sentAt as string;

    await page.goto('/cabinet/institutions/' + link.institutionId + '/shoots/' + link.shootId + '/photos?group=' + fixture.groupId);
    await expect(page.getByTestId('markup-correction-available')).toBeVisible();
    await expect(page.getByLabel('Код ребёнка', { exact: true })).toHaveCount(0);
    await page.getByTestId('markup-correction-start').click();
    await expect(page.getByTestId('markup-correction-active')).toBeVisible();

    // The buyer still sees the frames, but nothing is priced or ordered until the correction is finished.
    const buyer = await browser.newPage({ viewport });
    await buyer.goto('/g/' + fixture.token);
    await expect(buyer.getByTestId('gallery-updating')).toBeVisible();
    const paused = await buyer.request.post(gallery + '/quotes', { data: { lines: [] } });
    expect(paused.status()).toBe(409);
    expect((await paused.json()).error.code).toBe('GALLERY_UPDATING');
    expect(await buyer.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    await buyer.screenshot({
      path: info.outputPath('d4-' + viewport.name + '-gallery-updating.png'),
      fullPage: true,
      animations: 'disabled'
    });

    await page.getByTestId('photo-card').first().getByRole('checkbox').check();
    await page.getByLabel('Код ребёнка', { exact: true }).fill(viewport.code);
    await page.getByRole('button', { name: 'Назначить ребёнку', exact: true }).click();
    await expect(page.getByRole('status').filter({ hasText: 'Кадры назначены ребёнку.' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Перенести весь набор', exact: true })).toHaveCount(0);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    await page.screenshot({
      path: info.outputPath('d4-' + viewport.name + '-workspace-correction.png'),
      fullPage: true,
      animations: 'disabled'
    });
    await page.getByTestId('markup-correction-finish').click();
    await expect(page.getByTestId('markup-correction-available')).toBeVisible();

    // The corrected markup is published at once; T0 and the deadlines are those of the original transmission.
    await buyer.reload();
    await expect(buyer.getByTestId('gallery-updating')).toHaveCount(0);
    const published = (await body(await buyer.request.get(gallery))).data;
    expect(published.updating).toBe(false);
    expect(published.children.map((child: { code: string }) => child.code)).toContain(viewport.code);
    const after = (await body(await page.request.get('/api/v1/groups/' + fixture.groupId + '/link', { headers: auth }))).data;
    expect([after.sentAt, after.markupCorrectionAt]).toEqual([sentAt, null]);
    expect(after.history.slice(-2).map((event: { kind: string }) => event.kind)).toEqual(['markup_started', 'markup_finished']);
    await buyer.close();
    expect(errors).toEqual([]);
  });
}
