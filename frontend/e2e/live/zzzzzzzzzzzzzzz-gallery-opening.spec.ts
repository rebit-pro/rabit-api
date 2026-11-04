import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';
import { login } from './helpers.js';
import { body, command, link } from './f2-links.js';

test('#232: завершение расписания перед реальным запуском worker', async ({ page }, testInfo) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await login(page);
  const fixture = JSON.parse(readFileSync('var/i232-opening.json', 'utf8')) as { groupId: string; galleryToken: string };
  let current = await link(page, fixture.groupId);
  expect([current.state, current.openingStatus, current.sentAt]).toEqual(['preparing', 'cancelled', null]);
  expect(current.manualSentAt).toBeTruthy();
  // Other browser scenarios may change the shared catalog signature after the first preparation.
  if (!current.prepared) {
    await command(page, fixture.groupId, 'link-preparations', {
      revision: current.revision,
      signature: current.signature,
      photosReviewed: true,
      conditionsReviewed: true,
      staffReviewed: true,
      confirmed: true
    });
    current = await link(page, fixture.groupId);
  }
  const openAt = new Date(Date.now() + 15000 + 3 * 3600000).toISOString().slice(0, 19) + '+03:00';
  const planned = (
    await command(page, fixture.groupId, 'opening-schedules', {
      revision: current.revision,
      signature: current.signature,
      openAt,
      confirmed: true
    })
  ).data;
  expect(planned.status).toBe('scheduled');
  const scheduled = await link(page, fixture.groupId);
  expect([scheduled.state, scheduled.sentAt, scheduled.openingStatus]).toEqual(['scheduled', null, 'scheduled']);
  const parent = (await body(await page.request.get('/api/v1/public/galleries/' + fixture.galleryToken))).data;
  expect(parent.state).toBe('scheduled');
  await page.goto('/g/' + fixture.galleryToken);
  await expect(page.getByTestId('gallery-scheduled')).toBeVisible();
  await expect(page.getByRole('button', { name: /Корзина/ })).toHaveCount(0);
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
  await page.screenshot({ path: testInfo.outputPath('issue232-scheduled-mobile.png'), fullPage: true, animations: 'disabled' });
});
