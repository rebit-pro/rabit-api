import { existsSync, readFileSync, renameSync, writeFileSync } from 'node:fs';
import { test, expect, type APIResponse } from '@playwright/test';

// J2: the parent gets a letter with the link to the paid order. The J4 print order of zzzzzzzzzz-files is paid by the stand
// fixture; the stand has no cron and no mail worker, so the runner dispatches the letters and reads the link from the letter.
type Fixture = { print: { token: string } };
const fixture = JSON.parse(readFileSync('var/e4-fixture.json', 'utf8')) as Fixture;
const deliveries = '/api/v1/public/orders/current/file-deliveries';
const key = () => crypto.randomUUID().replace(/-/g, '');
let requests = 0;

async function body(response: APIResponse, status = 200) {
  expect(response.status(), await response.text()).toBe(status);
  return response.json();
}
/** Asks the runner to run app:files:order-emails and to read the link of the latest letter to the recipient. */
async function dispatch(recipient: string): Promise<{ created: number; queued: number; key: string | null }> {
  requests += 1;
  writeFileSync(`var/j2-request-${requests}.json.tmp`, JSON.stringify({ recipient }));
  renameSync(`var/j2-request-${requests}.json.tmp`, `var/j2-request-${requests}.json`);
  const answer = `var/j2-answer-${requests}.json`;
  await expect.poll(() => existsSync(answer), { timeout: 90000 }).toBe(true);
  const result = JSON.parse(readFileSync(answer, 'utf8'));
  expect(result.error).toBeUndefined();
  return result;
}

test.describe.configure({ mode: 'serial' });
test.use({ locale: 'ru-RU' });

test('J2-T08, J2-T09, J2-T11: the paid order gets one letter whose link opens the order on a phone', async ({
  page,
  browser,
  baseURL
}, testInfo) => {
  test.setTimeout(180000);
  const paid = JSON.parse(readFileSync('var/j1-files.json', 'utf8')) as { printCopy: { orderId: string; accessKey: string } };
  expect(paid.printCopy.accessKey, 'J4 must pay the print order first').not.toBe('');
  const buyer = { 'X-Order-Key': paid.printCopy.accessKey };
  const recipient = 'print.j4@example.test';

  // J2-DEC-06: with sending on, the checkout tells where the link goes.
  const catalog = (await body(await page.request.get('/api/v1/public/galleries/' + fixture.print.token + '/catalog'))).data;
  expect(catalog.capabilities.orderEmail).toBe(true);
  // Before the dispatcher the paid order has no letter yet, the address is the order email.
  const before = (await body(await page.request.get(deliveries, { headers: buyer }))).data;
  expect([before.enabled, before.recipient, before.latest, before.canResend]).toEqual([true, recipient, null, true]);

  const letter = await dispatch(recipient);
  expect(letter.created).toBeGreaterThanOrEqual(1);
  expect(letter.key).toMatch(/^[a-f0-9]{64}$/);
  expect(letter.key).not.toBe(paid.printCopy.accessKey);
  expect((await dispatch(recipient)).created, 'a repeated run adds no letter').toBe(0);

  // The link of the letter opens the paid order; the link the buyer saved at checkout keeps working.
  const email = { 'X-Order-Key': letter.key! };
  const order = (await body(await page.request.get('/api/v1/public/orders/current', { headers: email }))).data;
  expect([order.id, order.paymentStatus]).toEqual([paid.printCopy.orderId, 'paid']);
  expect((await body(await page.request.get('/api/v1/public/orders/current', { headers: buyer }))).data.id).toBe(paid.printCopy.orderId);
  const summary = (await body(await page.request.get(deliveries, { headers: email }))).data;
  expect([summary.latest.kind, summary.latest.status, summary.latest.recipient]).toEqual(['auto', 'pending', recipient]);
  expect((await body(await page.request.get(deliveries + '/' + summary.latest.id, { headers: email }))).data.status).toBe('pending');
  // J2-DEC-07: the repeat waits ten minutes after the letter; a foreign or a broken key sees nothing.
  const tooSoon = await body(
    await page.request.post(deliveries, { headers: { ...email, 'Idempotency-Key': key() }, data: { channel: 'email' } }),
    429
  );
  expect(tooSoon.error.code).toBe('DELIVERY_TOO_SOON');
  expect(
    (await body(await page.request.post(deliveries, { headers: { ...email, 'Idempotency-Key': key() }, data: { channel: 'sms' } }), 422))
      .error.code
  ).toBe('INVALID_DELIVERY');
  expect((await body(await page.request.get(deliveries, { headers: { 'X-Order-Key': key() + key() } }), 404)).error.code).toBe(
    'ORDER_NOT_FOUND'
  );
  expect((await body(await page.request.get(deliveries + '/' + crypto.randomUUID(), { headers: email }), 404)).error.code).toBe(
    'DELIVERY_NOT_FOUND'
  );

  for (const viewport of [
    { name: 'mobile', width: 390, height: 844 },
    { name: 'desktop', width: 1440, height: 1000 }
  ]) {
    const context = await browser.newContext({
      baseURL,
      viewport,
      isMobile: viewport.width < 500,
      hasTouch: viewport.width < 500,
      locale: 'ru-RU'
    });
    try {
      const parent = await context.newPage();
      await parent.goto('/orders/access/' + letter.key);
      await expect(parent.getByTestId('order-payment-status')).toHaveText('Оплачено');
      await expect(parent.getByTestId('order-files').getByTestId('files-item').first()).toBeVisible();
      const notice = parent.getByTestId('order-email');
      await expect(notice.getByTestId('order-email-text')).toHaveText('Отправляем ссылку на заказ на ' + recipient + '.');
      await expect(notice.getByTestId('order-email-resend')).toBeDisabled();
      await expect(notice.getByTestId('order-email-wait')).toContainText('Повторить можно после');
      expect(await parent.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
      await notice.scrollIntoViewIfNeeded();
      await parent.screenshot({
        path: testInfo.outputPath('j2-' + viewport.name + '-order-email.png'),
        fullPage: true,
        animations: 'disabled'
      });
    } finally {
      await context.close();
    }
  }

  writeFileSync(
    'var/j2-email.json',
    JSON.stringify({ orderId: paid.printCopy.orderId, buyerKey: paid.printCopy.accessKey, emailKey: letter.key })
  );
});
