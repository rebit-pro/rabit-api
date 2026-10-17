import { existsSync, readFileSync, renameSync, writeFileSync } from 'node:fs';
import { test, expect, type APIResponse, type Page } from '@playwright/test';
import { login, token } from './helpers.js';

// G2: receipts of «Чеки от ЮKassa». The J4 print order paid through the fixture provider gets its prepayment receipt, the
// organizer marks the print handed over in the «Печать» block of the L1 group, and the closing receipt settles the
// prepayment. With the test shop keys the same is done for the G1 sandbox payment against the real test shop.
type Fixture = Record<'print' | 'open', { token: string; groupId: string }>;
type Item = { description: string; quantity: number; amount: number; paymentMode: string };
type Receipt = {
  id: string;
  kind: 'payment' | 'closing';
  status: 'pending' | 'sent' | 'failed';
  amount: number;
  items: Item[];
  fiscalDocumentNumber: string | null;
  handedAt: string | null;
};
type HandoverOrder = { orderId: string; orderNumber: string; state: string; receiptStatus: string | null; reason: string | null };
type Handovers = { enabled: boolean; orders: HandoverOrder[]; summary: { waiting: number; handed: number; blocked: number } };
type StandReceipt = { id: string; kind: string; provider: string; orderId: string; status: string; error: string | null };
type Closing = {
  paymentId: string;
  prepayment: number;
  key: string;
  email: string;
  items: [string, number, number, string, string, number][];
};
type Answer = { receipts: StandReceipt[]; closing: Closing[]; error?: string };

const fixture = JSON.parse(readFileSync('var/e4-fixture.json', 'utf8')) as Fixture;
const sandbox = process.env.E2E_YOOKASSA === '1';
const handovers = (groupId: string) => '/api/v1/groups/' + groupId + '/print-handovers';
const receiptsPath = '/api/v1/public/orders/current/receipt';
const record = { orderId: '', handedOrderIds: [] as string[], sandbox: { orderId: '', payment: '', closing: '' } };
let requests = 0;
let printOrder = { id: '', key: '', number: '' };

async function body(response: APIResponse, status = 200) {
  expect(response.status(), await response.text()).toBe(status);
  return response.json();
}
function save() {
  writeFileSync('var/g2-receipts.json', JSON.stringify(record));
}
/** Asks the runner to reconcile the open receipts on the stand and waits for its answer. */
async function stand(): Promise<Answer> {
  requests += 1;
  writeFileSync(`var/g2-request-${requests}.json.tmp`, JSON.stringify({ action: 'reconcile' }));
  renameSync(`var/g2-request-${requests}.json.tmp`, `var/g2-request-${requests}.json`);
  const answer = `var/g2-answer-${requests}.json`;
  await expect.poll(() => existsSync(answer), { timeout: 120000 }).toBe(true);
  const result = JSON.parse(readFileSync(answer, 'utf8')) as Answer;
  expect(result.error).toBeUndefined();
  return result;
}
const receipts = async (page: Page, key: string) =>
  (await body(await page.request.get(receiptsPath, { headers: { 'X-Order-Key': key } }))).data;
const staff = async (page: Page) => ({ Authorization: 'Bearer ' + (await token(page)) });
async function openPrint(page: Page, groupId: string) {
  await page.goto('/cabinet/links?group=' + groupId);
  await page
    .locator('[data-row-id="' + groupId + '"]')
    .filter({ visible: true })
    .getByRole('button', { name: /^Действия: / })
    .click();
  await page.getByTestId('links-print-archive-open').click();
  const dialog = page.getByTestId('print-archive');
  await expect(dialog).toBeVisible();
  return dialog;
}

test.describe.configure({ mode: 'serial' });
test.use({ locale: 'ru-RU' });

test.beforeAll(() => {
  const paid = JSON.parse(readFileSync('var/j1-files.json', 'utf8')) as { printCopy: { orderId: string; accessKey: string } };
  expect(paid.printCopy.orderId, 'J4 must pay the print order first').not.toBe('');
  printOrder = { id: paid.printCopy.orderId, key: paid.printCopy.accessKey, number: '' };
  record.orderId = printOrder.id;
  save();
});

test('G2-T15: the buyer sees the prepayment receipt of the paid print with its requisites', async ({ page }, testInfo) => {
  const before = await receipts(page, printOrder.key);
  expect([before.enabled, before.channel, before.receipts.length, before.receipts[0]?.status]).toEqual([true, 'email', 1, 'pending']);

  const answer = await stand();
  expect(answer.receipts.find((receipt) => receipt.orderId === printOrder.id && receipt.kind === 'payment')?.status).toBe('succeeded');
  const after = await receipts(page, printOrder.key);
  const payment = after.receipts[0] as Receipt;
  expect([payment.kind, payment.status, payment.fiscalDocumentNumber]).toEqual(['payment', 'sent', '1001']);
  expect(payment.items.every((item) => item.paymentMode === 'full_prepayment')).toBe(true);
  expect(payment.items.reduce((sum, item) => sum + item.amount * item.quantity, 0)).toBe(payment.amount);
  expect((await body(await page.request.get(receiptsPath, { headers: { 'X-Order-Key': 'b'.repeat(64) } }), 404)).error.code).toBe(
    'ORDER_NOT_FOUND'
  );

  for (const viewport of [
    { name: 'desktop', width: 1440, height: 1000 },
    { name: 'mobile', width: 390, height: 844 }
  ]) {
    await page.setViewportSize(viewport);
    await page.goto('/orders/access/' + printOrder.key);
    const block = page.getByTestId('order-receipts');
    await expect(block.getByTestId('order-receipt')).toHaveCount(1);
    await expect(block).toContainText('Чек оплаты');
    await expect(block).toContainText('Отправлен на e-mail');
    await expect(block).toContainText('Предоплата 100%');
    await expect(block.getByTestId('order-receipt-fiscal')).toContainText('1001');
    await expect(block).toContainText('После выдачи печати придёт закрывающий чек.');
    printOrder.number = (await page.getByTestId('order-number').textContent())?.replace('Заказ ', '').trim() ?? '';
    await block.scrollIntoViewIfNeeded();
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    await block.screenshot({ path: testInfo.outputPath('g2-' + viewport.name + '-order-receipts.png'), animations: 'disabled' });
  }
});

test('G2-T07, G2-T16: the organizer marks the print handed over and the closing receipt settles the prepayment', async ({
  page
}, testInfo) => {
  test.setTimeout(240000);
  await page.setViewportSize({ width: 1440, height: 1000 });
  await login(page);
  const path = handovers(fixture.print.groupId);
  const view = (await body(await page.request.get(path, { headers: await staff(page) }))).data as Handovers;
  const waiting = view.orders.find((order) => order.orderId === printOrder.id);
  expect([view.enabled, waiting?.state]).toEqual([true, 'waiting']);

  // The organizer sees the paid print in the «Печать» block and marks it handed over on the day of the handover.
  for (const viewport of [
    { name: 'desktop', width: 1440, height: 1000 },
    { name: 'mobile', width: 390, height: 844 }
  ]) {
    await page.setViewportSize(viewport);
    const dialog = await openPrint(page, fixture.print.groupId);
    const section = dialog.getByTestId('print-handover');
    await expect(section.getByTestId('print-handover-summary')).toContainText('Выдано 0 из');
    await section.getByTestId('print-handover-start').click();
    await expect(section.getByTestId('print-handover-order').filter({ hasText: printOrder.number })).toBeVisible();
    await expect(section).toContainText('Отметку нельзя отменить.');
    await section.scrollIntoViewIfNeeded();
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    await section.screenshot({ path: testInfo.outputPath('g2-' + viewport.name + '-print-handover.png'), animations: 'disabled' });
    if ('desktop' === viewport.name) {
      await page.keyboard.press('Escape');
      continue;
    }
    // Only the J4 order is marked; other waiting orders of the group stay for a later handover.
    for (const box of await section.getByTestId('print-handover-order').all())
      if (!(await box.textContent())?.includes(printOrder.number)) await box.getByRole('checkbox').uncheck();
    await section.getByTestId('print-handover-confirm').click();
    await expect(section).toContainText('Выдача отмечена: 1');
    await expect(section.getByTestId('print-handover-done').filter({ hasText: printOrder.number })).toContainText('Формируется');
  }
  record.handedOrderIds.push(printOrder.id);
  save();

  // A repeat changes nothing; a foreign or an empty list and an anonymous request are refused.
  const repeat = (
    await body(await page.request.post(path, { headers: await staff(page), data: { orderIds: [printOrder.id], confirmed: true } }))
  ).data as Handovers;
  expect(repeat.orders.find((order) => order.orderId === printOrder.id)?.state).toBe('handed');
  const refuse = async (data: unknown, status: number) =>
    (await body(await page.request.post(path, { headers: await staff(page), data }), status)).error.code;
  expect(await refuse({ orderIds: [crypto.randomUUID()], confirmed: true }, 409)).toBe('HANDOVER_CONFLICT');
  expect(await refuse({ orderIds: [], confirmed: true }, 422)).toBe('INVALID_ORDERS');
  expect(await refuse({ orderIds: [printOrder.id], confirmed: false }, 422)).toBe('CONFIRMATION_REQUIRED');
  expect((await page.request.get(path)).status()).toBe(401);

  // The stand registers the closing receipt: the print items in full payment against the prepayment of the same payment.
  const answer = await stand();
  const closing = answer.receipts.find((receipt) => receipt.orderId === printOrder.id && receipt.kind === 'closing');
  expect([closing?.status, closing?.error]).toEqual(['succeeded', null]);
  const buyer = await receipts(page, printOrder.key);
  const [payment, closed] = buyer.receipts as Receipt[];
  expect(answer.closing).toHaveLength(1);
  expect(answer.closing[0]!.prepayment).toBe(closed!.amount);
  expect(closed!.amount).toBe(payment!.amount);
  expect(answer.closing[0]!.items.every((item) => item[3] === 'full_payment')).toBe(true);
  expect([closed!.kind, closed!.status, closed!.fiscalDocumentNumber, closed!.handedAt === null]).toEqual([
    'closing',
    'sent',
    '1002',
    false
  ]);

  await page.goto('/orders/access/' + printOrder.key);
  const block = page.getByTestId('order-receipts');
  await expect(block.getByTestId('order-receipt')).toHaveCount(2);
  await expect(block.locator('[data-kind="closing"]')).toContainText('Полный расчёт');
  await expect(block.locator('[data-kind="closing"]')).toContainText('предоплата зачтена');

  // The registry shows the receipt next to the payment and the receipts in its card.
  await page.setViewportSize({ width: 1440, height: 1000 });
  await page.goto('/cabinet/payments?orderNumber=' + encodeURIComponent(printOrder.number));
  await expect(page.getByTestId('payment-receipt-status').first()).toHaveText('Чек отправлен');
  await page.getByRole('link', { name: printOrder.number }).first().click();
  await expect(page.getByTestId('payment-receipts')).toContainText('Закрывающий чек');
  await page
    .getByTestId('payment-receipts')
    .screenshot({ path: testInfo.outputPath('g2-desktop-payment-receipts.png'), animations: 'disabled' });
});

test('G2-T14: the test shop takes the receipt of the payment and the closing receipt of the handed print', async ({ page }, testInfo) => {
  test.setTimeout(300000);
  if (!sandbox) {
    // Without the test shop keys the stand never reaches YooKassa: the honest state is no receipt of a real payment.
    const answer = await stand();
    expect(answer.receipts.filter((receipt) => receipt.provider === 'yookassa')).toEqual([]);
    return;
  }
  const g1 = JSON.parse(readFileSync('var/g1-payments.json', 'utf8')) as { paidOrderId: string };
  record.sandbox.orderId = g1.paidOrderId;
  save();
  await login(page);
  const find = (answer: Answer, kind: string) =>
    answer.receipts.find((receipt) => receipt.orderId === g1.paidOrderId && receipt.kind === kind);
  // The test shop refuses a payment without a valid receipt, so the paid card payment of G1 already proves the receipt was
  // accepted. Its registration status is read back from the test shop, not assumed.
  let answer = await stand();
  await expect
    .poll(
      async () => {
        answer = await stand();
        return find(answer, 'payment')?.status;
      },
      { timeout: 120000, intervals: [10000] }
    )
    .toBe('succeeded')
    .catch(() => undefined);
  const payment = find(answer, 'payment');
  expect(payment?.provider).toBe('yookassa');
  expect(['pending', 'succeeded']).toContain(payment?.status);
  record.sandbox.payment = payment?.status ?? '';
  save();
  testInfo.annotations.push({ type: 'test shop payment receipt', description: record.sandbox.payment });
  const path = handovers(fixture.open.groupId);
  const view = (await body(await page.request.get(path, { headers: await staff(page) }))).data as Handovers;
  expect(view.orders.find((order) => order.orderId === g1.paidOrderId)?.state).toBe('waiting');
  await body(await page.request.post(path, { headers: await staff(page), data: { orderIds: [g1.paidOrderId], confirmed: true } }));
  answer = await stand();
  const closing = find(answer, 'closing');
  // The closing receipt waits for the registered payment receipt; once sent, the test shop must accept it.
  expect(closing?.error ?? null).toBeNull();
  expect(payment?.status === 'succeeded' ? ['pending', 'succeeded'] : ['queued']).toContain(closing?.status);
  record.sandbox.closing = closing?.status ?? '';
  save();
  testInfo.annotations.push({ type: 'test shop closing receipt', description: record.sandbox.closing });
});
