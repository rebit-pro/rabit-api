import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';
import { login, password } from './helpers.js';
import { auth, body, command, key, link, moscow, preparedLinkPath, type PreparedLink } from './f2-links.js';

test('F2: воспитатель отмечает передачу, куратор исправляет дату на минуту подготовки', async ({ page, browser, baseURL }, testInfo) => {
  test.setTimeout(180000);
  await page.setViewportSize({ width: 1440, height: 1000 });
  await login(page);
  // Prepared by z-links-preparation at the start of group B; nothing in between may touch this group.
  const { groupId, shootId, suffix, prepareMinute, galleryToken, signature } = JSON.parse(
    readFileSync(preparedLinkPath, 'utf8')
  ) as PreparedLink;
  const current = await link(page, groupId);
  expect([current.revision, current.prepared, current.signature, current.state]).toEqual([3, true, signature, 'preparing']);
  const galleryPath = '/api/v1/public/galleries/' + galleryToken;
  const later = { revision: 2, confirmed: true, sentAt: moscow(Date.now()) + ':00+03:00' };

  const teacherContext = await browser.newContext({ baseURL });
  const curatorContext = await browser.newContext({ baseURL });
  const headContext = await browser.newContext({ baseURL });
  try {
    const head = await headContext.newPage();
    await login(head, 'head');
    expect((await command(head, groupId, 'link-transmissions', { ...later, revision: 3, signature }, 403)).error.code).toBe('FORBIDDEN');
    await head.goto('/cabinet/links');
    await expect(head.getByTestId('link-' + groupId)).toBeVisible();
    await expect(head.getByText('Только просмотр', { exact: true })).toBeVisible();
    await expect(head.getByRole('button', { name: 'Отметить передачу', exact: true })).toHaveCount(0);

    const teacher = await teacherContext.newPage();
    await teacher.setViewportSize({ width: 390, height: 844 });
    await login(teacher, 'teacher');
    await teacher.goto('/cabinet/links');
    const teacherCard = teacher.getByTestId('link-' + groupId);
    await teacherCard.getByRole('button', { name: 'Отметить передачу', exact: true }).click();
    // The delivery cannot be reported before the link existed, and the correction below goes back to that minute.
    // The preparation ran first in the group, so the minute is normally over already; the poll only guards it.
    await expect.poll(() => moscow(Date.now()) > prepareMinute, { timeout: 65000, intervals: [1000] }).toBe(true);
    const sentAt = moscow(Date.now());
    await teacher.getByTestId('admin-dialog').getByLabel('Дата и время передачи (МСК)', { exact: true }).fill(sentAt);
    await teacher.getByTestId('admin-dialog').getByLabel('Подтверждаю факт передачи и указанные сроки', { exact: true }).check();
    const transmitted = teacher.waitForResponse((r) => r.url().endsWith('/link-transmissions') && r.request().method() === 'POST');
    await teacher.getByTestId('admin-dialog').getByRole('button', { name: 'Отметить передачу ссылки', exact: true }).click();
    expect((await transmitted).status()).toBe(200);
    await expect(teacherCard.getByText('Приём открыт', { exact: true })).toBeVisible();
    expect(await teacher.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    await teacher.screenshot({ path: testInfo.outputPath('f2-mobile-open.png'), fullPage: true, animations: 'disabled' });
    expect(
      (await command(teacher, groupId, 'link-date-corrections', { ...later, revision: 4, signature, reason: 'Ошибка' }, 403)).error.code
    ).toBe('FORBIDDEN');

    const open = await link(page, groupId);
    expect(open.sentAt).toBe(sentAt + ':00+03:00');
    expect(Date.parse(open.closesAt!) - Date.parse(open.sentAt!)).toBe(7 * 86400000);
    expect(Date.parse(open.deliveryAt!) - Date.parse(open.closesAt!)).toBe(7 * 86400000);
    const gallery = (await body(await page.request.get(galleryPath))).data;
    expect([gallery.state, Date.parse(gallery.closesAt)]).toEqual(['open', Date.parse(open.closesAt!)]);
    const catalog = (await body(await page.request.get(galleryPath + '/catalog'))).data;
    const product = catalog.products.find((item: { name: string }) => item.name === 'F2 Печать ' + suffix);
    const assignmentId = gallery.children[0].photos[0].assignmentId as string;
    const quote = (
      await body(
        await page.request.post(galleryPath + '/quotes', { data: { lines: [{ assignmentId, productId: product.id, quantity: 2 }] } })
      )
    ).data;
    expect(quote.quote.total).toBeGreaterThan(0);
    const shootCard = (await body(await page.request.get('/api/v1/shoots/' + shootId, { headers: await auth(page) }))).data;
    expect(shootCard.groups.items[0].sentAt).toBe(open.sentAt);

    // A repeated report never moves deadlines, even with a new key and a stale form.
    const repeatKey = key();
    const repeat = (
      await command(page, groupId, 'link-transmissions', { ...later, revision: 1, signature: '0'.repeat(64) }, 200, repeatKey)
    ).data;
    expect([repeat.sentAt, repeat.closesAt]).toEqual([open.sentAt, open.closesAt]);
    expect(
      (await command(page, groupId, 'link-transmissions', { ...later, revision: 1, signature: '0'.repeat(64) }, 200, repeatKey)).data
    ).toEqual(repeat);
    expect(
      (await command(page, groupId, 'link-transmissions', { ...later, revision: 2, signature: '0'.repeat(64) }, 409, repeatKey)).error.code
    ).toBe('IDEMPOTENCY_CONFLICT');

    const curator = await curatorContext.newPage();
    await login(curator, 'curator');
    const before = { revision: open.revision, signature: open.signature, confirmed: true, reason: 'Передали раньше, чем отметили' };
    const earliest = Date.parse(prepareMinute + ':00+03:00') - 2 * 60000;
    expect(
      (await command(curator, groupId, 'link-date-corrections', { ...before, sentAt: moscow(earliest) + ':00+03:00' }, 422)).error.code
    ).toBe('SENT_AT_BEFORE_LINK');
    const corrected = (await command(curator, groupId, 'link-date-corrections', { ...before, sentAt: prepareMinute + ':00+03:00' })).data;
    expect(corrected.sentAt).toBe(prepareMinute + ':00+03:00');
    const history = (await link(page, groupId)).history!;
    expect(history.map((event) => event.kind)).toEqual(['prepared', 'prepared', 'transmitted', 'corrected']);
    expect([history[3]!.reason, history[3]!.previousSentAt]).toEqual(['Передали раньше, чем отметили', open.sentAt]);

    const foreign = await browser.newPage({ baseURL });
    await foreign.goto('/login');
    await foreign.getByRole('textbox', { name: 'Email', exact: true }).fill('another-teacher@example.invalid');
    await foreign.getByLabel('Пароль', { exact: true }).fill(password);
    await Promise.all([
      foreign.waitForResponse((r) => r.url().endsWith('/api/v1/me')),
      foreign.getByRole('button', { name: 'Войти', exact: true }).click()
    ]);
    expect(
      (await body(await foreign.request.get('/api/v1/groups/' + groupId + '/link', { headers: await auth(foreign) }), 404)).error.code
    ).toBe('GROUP_NOT_FOUND');
    await foreign.close();
  } finally {
    await teacherContext.close();
    await curatorContext.close();
    await headContext.close();
  }
});
