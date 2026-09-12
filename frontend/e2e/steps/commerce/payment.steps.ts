import { When, Then } from '@cucumber/cucumber';
import { expect, type Page } from '@playwright/test';
import { CustomWorld } from '../../support/world.js';
function page(world: CustomWorld): Page {
  if (!world.page) throw new Error('Страница не создана');
  return world.page;
}
When('переходит к тестовой оплате', async function (this: CustomWorld) {
  await page(this).getByRole('link', { name: 'Перейти к тестовой оплате', exact: true }).click();
  await expect(page(this).getByRole('heading', { name: 'Демонстрационная оплата', exact: true })).toBeVisible();
});
When('выбирает сценарий оплаты {string}', async function (this: CustomWorld, name: string) {
  await page(this).getByRole('radio', { name, exact: true }).check();
});
When('запускает тестовую оплату', async function (this: CustomWorld) {
  await page(this).getByTestId('pay-demo').click();
});
Then('состояние оплаты {string} и попыток {int}', async function (this: CustomWorld, status: string, count: number) {
  await expect
    .poll(() =>
      page(this).evaluate(() => {
        const order = JSON.parse(localStorage.getItem('morefoto:demo:orders:v1') || '[]')[0];
        return { status: order?.paymentStatus, count: order?.paymentAttempts?.length ?? 0 };
      })
    )
    .toEqual({ status, count });
  if (status === 'paid' || status === 'pending') await expect(page(this).getByTestId('pay-demo')).toHaveCount(0);
});
Then('повторная оплата отсутствует', async function (this: CustomWorld) {
  await expect(page(this).getByTestId('pay-demo')).toHaveCount(0);
});
Then('запуск оплаты заблокирован', async function (this: CustomWorld) {
  await expect(page(this).getByTestId('pay-demo')).toBeDisabled();
});
When('возвращается из оплаты к заказу', async function (this: CustomWorld) {
  await page(this).getByRole('link', { name: 'Вернуться к заказу', exact: true }).click();
  await expect(page(this).getByRole('heading', { name: 'Заказ MF-000001', exact: true })).toBeVisible();
});
Then('собственный заказ оплачен и изготовление не начато', async function (this: CustomWorld) {
  await expect(page(this).getByTestId('order-payment-status')).toHaveText('Оплачено · демонстрация');
  await expect(page(this).getByTestId('order-production-status')).toHaveText('Не начато');
});
When('проверяет статус оплаты', async function (this: CustomWorld) {
  await page(this).getByRole('button', { name: 'Проверить статус оплаты', exact: true }).click();
});
When('имитирует подтверждение оплаты', async function (this: CustomWorld) {
  await page(this).getByRole('button', { name: 'Имитировать подтверждение', exact: true }).click();
});
When('имитирует отказ оплаты', async function (this: CustomWorld) {
  await page(this).getByRole('button', { name: 'Имитировать отказ', exact: true }).click();
});
When('переводит оплату за закрытие группы', async function (this: CustomWorld) {
  await page(this).getByText('Время и закрытие группы', { exact: true }).click();
  await page(this).getByRole('button', { name: 'После закрытия группы', exact: true }).click();
});
When('подтверждает новый итог оплаты', async function (this: CustomWorld) {
  await page(this).getByRole('checkbox', { name: 'Новый состав и итог проверены', exact: true }).check();
});
Then('сумма последней попытки {int} рублей', async function (this: CustomWorld, total: number) {
  expect(
    await page(this).evaluate(() => JSON.parse(localStorage.getItem('morefoto:demo:orders:v1') || '[]')[0].paymentAttempts.at(-1).amount)
  ).toBe(total * 100);
});
When('ответ запуска оплаты теряется', async function (this: CustomWorld) {
  await page(this).evaluate(() => localStorage.setItem('morefoto:demo:payment:lose-response-once', 'true'));
});
When('запускает оплату в двух вкладках', async function (this: CustomWorld) {
  const p = page(this);
  const second = await p.context().newPage();
  try {
    await second.goto(p.url(), { waitUntil: 'networkidle' });
    for (const tab of [p, second]) {
      await tab.getByRole('radio', { name: 'Ожидание подтверждения', exact: true }).check();
      await tab.evaluate(() => {
        (window as Window & { __MOREFOTO_MOCKS__?: { setDelay(ms: number): void } }).__MOREFOTO_MOCKS__?.setDelay(1200);
      });
    }
    await Promise.all([p.getByTestId('pay-demo').click(), second.getByTestId('pay-demo').click()]);
    await expect(p.getByTestId('payment-status')).toContainText('Ожидаем подтверждение');
    await expect(second.getByTestId('payment-status')).toContainText('Ожидаем подтверждение');
    await expect(second.getByTestId('pay-demo')).toHaveCount(0);
  } finally {
    await second.close();
  }
});
When('открывает оплату по номеру заказа', async function (this: CustomWorld) {
  await page(this).goto(this.baseUrl + '/orders/access/MF-000001/payment', { waitUntil: 'networkidle' });
});
