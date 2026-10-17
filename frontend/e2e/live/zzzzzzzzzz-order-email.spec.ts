import { existsSync, readFileSync, renameSync, writeFileSync } from 'node:fs';
import { test, expect, type APIResponse } from '@playwright/test';

// J2/J5: the parent gets the originals of the paid order in letters, each with the link to the order. The J4 print order of
// zzzzzzzzzz-files is paid by the stand fixture; the stand has no cron, no mail worker and no SMTP, so the runner dispatches
// the letters, sends them through the real Bitrix mail into files and reads them back. The stand limits a letter to one byte
// of originals, so every frame goes in its own letter (J5-DEC-02).
type Frame = { photoId: string; sha256: string };
type Fixture = { print: { token: string; frames: Record<'A1' | 'A2' | 'B1' | 'B2', Frame> } };
type Attachment = { name: string; contentType: string; bytes: number; sha256: string };
type Letter = { to: string; subject: string; key: string | null; attachments: Attachment[] };
const fixture = JSON.parse(readFileSync('var/e4-fixture.json', 'utf8')) as Fixture;
const deliveries = '/api/v1/public/orders/current/file-deliveries';
const key = () => crypto.randomUUID().replace(/-/g, '');
let requests = 0;

async function body(response: APIResponse, status = 200) {
  expect(response.status(), await response.text()).toBe(status);
  return response.json();
}
/** Asks the runner to act for cron and the mail worker: dispatch the letters, deliver them or make the letters older. */
async function stand<T>(action: 'dispatch' | 'deliver' | 'age', recipient: string): Promise<T> {
  requests += 1;
  writeFileSync(`var/j2-request-${requests}.json.tmp`, JSON.stringify({ action, recipient }));
  renameSync(`var/j2-request-${requests}.json.tmp`, `var/j2-request-${requests}.json`);
  const answer = `var/j2-answer-${requests}.json`;
  await expect.poll(() => existsSync(answer), { timeout: 90000 }).toBe(true);
  const result = JSON.parse(readFileSync(answer, 'utf8'));
  expect(result.error).toBeUndefined();
  return result as T;
}
const dispatch = (recipient: string) => stand<{ created: number; queued: number; key: string | null }>('dispatch', recipient);
const deliver = (recipient: string) => stand<{ delivered: number; letters: Letter[] }>('deliver', recipient);

test.describe.configure({ mode: 'serial' });
test.use({ locale: 'ru-RU' });

test('J2-T08, J2-T09, J5-T10, J5-T11, J5-T13: the paid order sends its originals in letters with the link to the order', async ({
  page,
  browser,
  baseURL
}, testInfo) => {
  test.setTimeout(240000);
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
  // The frames of the order right now: the letters carry exactly these originals, the neighbour B2 never.
  const files = (await body(await page.request.get('/api/v1/public/orders/current/files', { headers: buyer }))).data;
  const items = files.items as { photoId: string; filename: string }[];
  expect(items.length).toBeGreaterThanOrEqual(2);
  const sha = Object.fromEntries(Object.values(fixture.print.frames).map((frame) => [frame.photoId, frame.sha256]));
  const expected = items.map((item) => ({ name: item.filename, sha256: sha[item.photoId] })).sort((a, b) => a.name.localeCompare(b.name));
  expect(expected.map((item) => item.sha256)).not.toContain(fixture.print.frames.B2.sha256);

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
  const queued = (await body(await page.request.get(deliveries, { headers: email }))).data.latest;
  expect([queued.kind, queued.status, queued.recipient]).toEqual(['auto', 'pending', recipient]);
  expect([queued.letters, queued.acceptedLetters, queued.photos, queued.linkOnly]).toEqual([items.length, 0, items.length, 0]);
  expect((await body(await page.request.get(deliveries + '/' + queued.id, { headers: email }))).data.status).toBe('pending');

  // J5-T10: the real MIME letters — one frame each, the bytes are the originals, the link is in every letter.
  const mail = await deliver(recipient);
  const checkLetters = (letters: Letter[]) => {
    expect(letters).toHaveLength(items.length);
    letters.forEach((sent, index) => {
      expect(sent.to).toBe(recipient);
      expect(sent.subject).toBe(`Заказ №${order.number} оплачен — ваши фото, письмо ${index + 1} из ${items.length}`);
      expect(sent.key).toBe(letter.key);
      expect(sent.attachments).toHaveLength(1);
      // METHOD stays unset: a plain attachment part, not a calendar part with `method=` in its Content-Type.
      expect(sent.attachments.map((file) => file.contentType)).toEqual([expect.stringMatching(/^image\/(jpeg|png|webp); name="[^"]+"$/)]);
    });
    const attached = letters.flatMap((sent) => sent.attachments).map(({ name, sha256 }) => ({ name, sha256 }));
    expect(attached.sort((a, b) => a.name.localeCompare(b.name))).toEqual(expected);
  };
  expect(mail.delivered).toBe(items.length);
  checkLetters(mail.letters);
  const accepted = (await body(await page.request.get(deliveries, { headers: email }))).data.latest;
  expect([accepted.status, accepted.letters, accepted.acceptedLetters]).toEqual(['accepted', items.length, items.length]);

  // J2-DEC-07: the repeat waits ten minutes after the letters; a foreign or a broken key sees nothing.
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
      await expect(notice.getByTestId('order-email-text')).toContainText(`Фото и ссылка на заказ отправлены на ${recipient} `);
      await expect(notice.getByTestId('order-email-text')).toContainText(`— ${items.length} письма, ${items.length} фото. Если писем нет`);
      await expect(notice.getByTestId('order-email-resend')).toBeDisabled();
      await expect(notice.getByTestId('order-email-wait')).toContainText('Повторить можно после');
      expect(await parent.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
      await notice.scrollIntoViewIfNeeded();
      await parent.screenshot({
        path: testInfo.outputPath('j5-' + viewport.name + '-order-email.png'),
        fullPage: true,
        animations: 'disabled'
      });
    } finally {
      await context.close();
    }
  }

  // J5-T11, J5-DEC-03: ten minutes later the repeat sends every letter again with the same originals.
  expect((await stand<{ aged: number }>('age', recipient)).aged).toBeGreaterThanOrEqual(1);
  const repeat = (
    await body(await page.request.post(deliveries, { headers: { ...email, 'Idempotency-Key': key() }, data: { channel: 'email' } }), 202)
  ).data;
  expect([repeat.kind, repeat.letters, repeat.photos]).toEqual(['manual', items.length, items.length]);
  const again = await deliver(recipient);
  expect(again.delivered).toBe(items.length);
  checkLetters(again.letters);
  const resent = (await body(await page.request.get(deliveries + '/' + repeat.id, { headers: email }))).data;
  expect([resent.status, resent.acceptedLetters]).toEqual(['accepted', items.length]);
  expect(
    (await body(await page.request.post(deliveries, { headers: { ...email, 'Idempotency-Key': key() }, data: { channel: 'email' } }), 429))
      .error.code
  ).toBe('DELIVERY_TOO_SOON');

  writeFileSync(
    'var/j2-email.json',
    JSON.stringify({
      orderId: paid.printCopy.orderId,
      buyerKey: paid.printCopy.accessKey,
      emailKey: letter.key,
      letters: items.length,
      deliveries: [queued.id, repeat.id]
    })
  );
});
