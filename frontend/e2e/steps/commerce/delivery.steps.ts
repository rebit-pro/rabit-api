import { Given, When, Then } from '@cucumber/cucumber';
import { expect, type Page, type Download } from '@playwright/test';
import { readFile, mkdir } from 'node:fs/promises';
import { execFileSync } from 'node:child_process';
import { CustomWorld } from '../../support/world.js';
function page(world: CustomWorld): Page {
  if (!world.page) throw new Error('Страница не создана');
  return world.page;
}
const message = 'Не удаётся скачать кадр. Прошу проверить доступ к фотографии.';
Given('покупатель оформил заказ R06 {string}', async function (this: CustomWorld, product: string) {
  const p = page(this);
  await p.goto(this.baseUrl + '/g/158-group-7bc93615c4e94fd18a207d560b3e1f82', { waitUntil: 'networkidle' });
  await p.getByRole('button', { name: 'Открыть кадр A001-01', exact: true }).click();
  await p.locator('.product-selector').getByRole('combobox').first().click();
  await p.getByRole('option', { name: product === 'Отпечатки с подарком' ? 'Отпечаток 10 × 15' : product, exact: true }).click();
  if (product === 'Отпечатки с подарком') await p.getByRole('spinbutton').fill('12');
  await p.getByTestId('add-to-cart').click();
  await expect(p.locator('.product-notice')).toBeVisible();
  await p.getByRole('link', { name: 'Посмотреть корзину', exact: true }).click();
  await p.getByRole('link', { name: 'Оформить заказ', exact: true }).click();
  await p.getByRole('textbox', { name: 'Имя покупателя', exact: true }).fill('Тестовый покупатель');
  await p.getByRole('textbox', { name: 'Телефон', exact: true }).fill('+79001234567');
  await p.getByRole('textbox', { name: 'Email', exact: true }).fill('parent@example.test');
  await p.getByRole('checkbox', { name: 'Состав и демонстрационные условия проверены', exact: true }).check();
  await p.getByTestId('create-order').click();
  await expect(p.getByRole('heading', { name: 'Заказ MF-000001', exact: true })).toBeVisible();
});
When('оплачивает заказ R06', async function (this: CustomWorld) {
  const p = page(this);
  await p.getByRole('link', { name: 'Перейти к тестовой оплате', exact: true }).click();
  await p.getByTestId('pay-demo').click();
  await expect(p.getByTestId('payment-status')).toContainText('Тестовая оплата подтверждена');
  await p.getByRole('link', { name: 'Вернуться к заказу', exact: true }).click();
  await expect(p.getByTestId('order-payment-status')).toHaveText('Оплачено · демонстрация');
});
async function bytes(download: Download) {
  expect(await download.failure()).toBeNull();
  const file = await download.path();
  if (!file) throw new Error('Нет скачанного файла');
  return { file, data: await readFile(file) };
}
Then('скачивает тестовый кадр R06 {string}', async function (this: CustomWorld, code: string) {
  const p = page(this);
  const pending = p.waitForEvent('download');
  await p.getByRole('button', { name: 'Скачать ' + code, exact: true }).click();
  const download = await pending;
  expect(download.suggestedFilename()).toBe(code + '-demo.webp');
  const { data } = await bytes(download);
  expect(data.subarray(0, 4).toString()).toBe('RIFF');
  expect(data.subarray(8, 12).toString()).toBe('WEBP');
  expect(data.length).toBeGreaterThan(1000);
});
Then('скачивает ZIP заказа R06 с {int} файлами', async function (this: CustomWorld, count: number) {
  const p = page(this);
  const pending = p.waitForEvent('download');
  await p.getByTestId('download-archive').click();
  const download = await pending;
  expect(download.suggestedFilename()).toBe('MF-000001-demo.zip');
  const { file } = await bytes(download);
  const expected = await p.evaluate(() =>
    JSON.parse(localStorage.getItem('morefoto:demo:orders:v1') || '[]')[0].digitalPhotos.map(
      (photo: { code: string }) => photo.code + '-demo.webp'
    )
  );
  expect(expected).toHaveLength(count);
  const report = execFileSync(
    'python3',
    [
      '-c',
      'import zipfile,sys,json; z=zipfile.ZipFile(sys.argv[1]); assert z.testzip() is None; assert z.namelist()==json.loads(sys.argv[2]); assert all(z.read(n)[:4]==b"RIFF" and z.read(n)[8:12]==b"WEBP" for n in z.namelist()); print("ok")',
      file,
      JSON.stringify(expected)
    ],
    { encoding: 'utf8' }
  );
  expect(report.trim()).toBe('ok');
});
Then('файлы R06 недоступны', async function (this: CustomWorld) {
  await expect(page(this).getByTestId('download-archive')).toHaveCount(0);
  await expect(page(this).getByRole('button', { name: /^Скачать A/ })).toHaveCount(0);
});
When('выбирает проверку R06 {string}', async function (this: CustomWorld, label: string) {
  const p = page(this);
  const details = p.locator('details.delivery-demo');
  if (!(await details.evaluate((el) => el.hasAttribute('open')))) await details.locator('summary').click();
  await details.getByRole('button', { name: label, exact: true }).click();
});
When('часы R06 установлены на {string}', async function (this: CustomWorld, value: string) {
  await page(this).evaluate((now) => {
    (window as Window & { __MOREFOTO_MOCKS__?: { setNow(value: string): void } }).__MOREFOTO_MOCKS__?.setNow(now);
  }, value);
});
When('файл R06 один раз возвращает ошибку', async function (this: CustomWorld) {
  await page(this).route('**/*-preview.webp', (route) => route.fulfill({ status: 503, body: 'Unavailable' }), { times: 1 });
});
When('нажимает скачивание ZIP R06', async function (this: CustomWorld) {
  await page(this).getByTestId('download-archive').click();
});
Then('ошибка R06 содержит {string}', async function (this: CustomWorld, value: string) {
  await expect(page(this).getByTestId('delivery-error')).toContainText(value);
});
When('срок R06 истекает во время подготовки файла', async function (this: CustomWorld) {
  const p = page(this);
  let downloads = 0;
  p.on('download', () => downloads++);
  await p.route(
    '**/*-preview.webp',
    async (route) => {
      const response = await route.fetch();
      await p.evaluate(() => {
        (window as Window & { __MOREFOTO_MOCKS__?: { setNow(value: string): void } }).__MOREFOTO_MOCKS__?.setNow('2026-10-07T09:00:00Z');
      });
      await route.fulfill({ response });
    },
    { times: 1 }
  );
  await p.getByTestId('download-archive').click();
  await expect(p.getByTestId('delivery-error')).toBeVisible();
  expect(downloads).toBe(0);
});
When('повторяет действие R06 {string}', async function (this: CustomWorld, label: string) {
  const p = page(this);
  const button = p.getByRole('button', { name: label, exact: true });
  await button.click();
  await expect(button).toBeEnabled();
});
Then('состояние чека R06 содержит {string}', async function (this: CustomWorld, value: string) {
  await expect(page(this).getByTestId('receipt-status')).toContainText(value);
});
When('выбирает канал чека R06 {string}', async function (this: CustomWorld, label: string) {
  await page(this).getByRole('radio', { name: label, exact: true }).check();
});
Then('повтор чека R06 заблокирован', async function (this: CustomWorld) {
  await expect(page(this).getByRole('button', { name: 'Повторить отправку чека', exact: true })).toBeDisabled();
});
When('отправляет обращение R06', async function (this: CustomWorld) {
  await page(this).getByTestId('send-support').click();
});
Then('ошибка поля обращения R06 видна и в фокусе', async function (this: CustomWorld) {
  await expect(page(this).getByText('Опишите вопрос: от 10 до 2000 символов.', { exact: true })).toBeVisible();
  await expect(page(this).getByRole('textbox', { name: 'Ваш вопрос', exact: true })).toBeFocused();
});
When('заполняет обращение R06', async function (this: CustomWorld) {
  const p = page(this);
  await p.getByRole('textbox', { name: 'Email для ответа', exact: true }).fill('fixed@example.test');
  await p.getByRole('textbox', { name: 'Ваш вопрос', exact: true }).fill(message);
  await p.getByTestId('support-photo').locator('.v-field').click();
  await p.getByRole('option', { name: 'A001-01', exact: true }).click();
});
Then('черновик обращения R06 сохранён', async function (this: CustomWorld) {
  await expect(page(this).getByRole('textbox', { name: 'Ваш вопрос', exact: true })).toHaveValue(message);
  await expect(page(this).getByRole('textbox', { name: 'Email для ответа', exact: true })).toHaveValue('fixed@example.test');
});
Then('сохранено {int} обращений R06 с контекстом заказа', async function (this: CustomWorld, count: number) {
  const p = page(this);
  await expect(p.getByTestId('support-history').locator('article')).toHaveCount(count);
  const result = await p.evaluate(() => {
    const order = JSON.parse(localStorage.getItem('morefoto:demo:orders:v1') || '[]')[0];
    return { id: order.id, groupId: order.groupId, request: order.supportRequests[0] };
  });
  expect(result.request).toMatchObject({
    orderId: result.id,
    orderNumber: 'MF-000001',
    groupId: result.groupId,
    curator: 'Рита',
    photoCode: 'A001-01',
    replyEmail: 'fixed@example.test',
    message
  });
});
When('ответ обращения R06 теряется', async function (this: CustomWorld) {
  await page(this).evaluate(() => localStorage.setItem('morefoto:demo:support:lose-response-once', 'true'));
});
When('отправляет обращение R06 в двух вкладках', async function (this: CustomWorld) {
  const p = page(this);
  const second = await p.context().newPage();
  try {
    await second.goto(p.url(), { waitUntil: 'networkidle' });
    await Promise.all([p.getByTestId('send-support').click(), second.getByTestId('send-support').click()]);
    await expect(second.getByTestId('support-history').locator('article')).toHaveCount(1);
    await expect(second.getByTestId('send-support')).toBeEnabled();
  } finally {
    await second.close();
  }
});
Then('помощь R06 не раскрывается', async function (this: CustomWorld) {
  await expect(page(this).getByTestId('support-history')).toHaveCount(0);
});
Then('интерфейс R06 проверен на ширине {int}', async function (this: CustomWorld, width: number) {
  const p = page(this);
  await p.setViewportSize({ width, height: 1000 });
  await expect(p.getByTestId('download-archive')).toBeVisible();
  await mkdir('reports/e2e/r06', { recursive: true });
  await p.screenshot({ path: `reports/e2e/r06/order-${width}.png`, fullPage: true });
  for (const size of ['16px', '32px']) {
    await p.evaluate((value) => (document.documentElement.style.fontSize = value), size);
    expect(await p.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    for (const button of await p.locator('#order-files .v-btn, #order-help .v-btn, .receipt .v-btn').all()) {
      const bounds = await button.boundingBox();
      expect(bounds?.height).toBeGreaterThanOrEqual(44);
      expect(await button.evaluate((el) => el.scrollWidth <= el.clientWidth + 1)).toBe(true);
    }
  }
  await p.evaluate(() => (document.documentElement.style.fontSize = '16px'));
  await p.getByRole('link', { name: 'Нужна помощь с фотографиями', exact: true }).click();
  await expect(p.locator('#order-help')).toBeFocused();
});
